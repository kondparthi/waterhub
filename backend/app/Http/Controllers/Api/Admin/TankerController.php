<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\BookingAssignment;
use App\Models\TankerPricing;
use App\Models\TankerProfile;
use App\Models\Vehicle;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Tanker profile, vehicle and pricing review. */
class TankerController extends Controller
{
    public function __construct(private NotificationService $notifier) {}

    public function index(Request $request)
    {
        $q = TankerProfile::with('user:id,name,mobile,email,status')->withCount('vehicles', 'drivers')->latest();
        if ($request->filled('status')) $q->where('verification_status', $request->status);
        if ($s = $request->get('search')) $q->where(fn ($w) => $w->where('owner_name', 'like', "%{$s}%")->orWhere('business_name', 'like', "%{$s}%")->orWhereHas('user', fn ($u) => $u->where('mobile', 'like', "%{$s}%")));
        return $this->ok($q->paginate($this->perPage()));
    }

    public function show(TankerProfile $tanker)
    {
        $tanker->load(['user:id,name,mobile,email,status,status_reason,created_at', 'drivers', 'vehicles.driver:id,name', 'vehicles.pricing' => fn ($q) => $q->latest('id')]);
        $stats = BookingAssignment::whereIn('vehicle_id', $tanker->vehicles->pluck('id'))->selectRaw("assignment_status, count(*) c")->groupBy('assignment_status')->pluck('c', 'assignment_status');
        return $this->ok(['profile' => $tanker, 'assignment_stats' => $stats]);
    }

    /** action: approve | reject | request_correction | suspend */
    public function review(Request $request, TankerProfile $tanker)
    {
        $data = $request->validate(['action' => 'required|in:approve,reject,request_correction,suspend', 'notes' => 'required_unless:action,approve|nullable|string|max:500']);
        $status = ['approve' => 'approved', 'reject' => 'rejected', 'request_correction' => 'correction_requested', 'suspend' => 'suspended'][$data['action']];
        $before = $tanker->only('verification_status', 'review_notes');
        DB::transaction(function () use ($tanker, $status, $data, $request) {
            $tanker->update(['verification_status' => $status, 'review_notes' => $data['notes'] ?? null, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            if ($status === 'suspended') $tanker->user->update(['status' => 'suspended', 'status_reason' => $data['notes']]);
            if ($status === 'approved' && $tanker->user->status === 'suspended') $tanker->user->update(['status' => 'active', 'status_reason' => null]);
        });
        AuditLogger::log('tanker.' . $data['action'], $tanker, $before, $tanker->only('verification_status', 'review_notes'));
        $msg = ['approved' => 'Your tanker profile is approved. Approved vehicles can now receive bookings.', 'rejected' => 'Your tanker profile was rejected: ' . ($data['notes'] ?? ''), 'correction_requested' => 'Please correct your profile: ' . ($data['notes'] ?? ''), 'suspended' => 'Your tanker account is suspended: ' . ($data['notes'] ?? '')][$status];
        $this->notifier->notify($tanker->user_id, 'Profile ' . str_replace('_', ' ', $status), $msg, 'tanker.reviewed', ['tanker_profile_id' => $tanker->id]);
        return $this->ok($tanker->fresh('user'), 'Tanker profile ' . str_replace('_', ' ', $status));
    }

    public function vehicles(Request $request)
    {
        $q = Vehicle::with('tankerProfile:id,owner_name,business_name,verification_status', 'driver:id,name,mobile')->latest();
        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->filled('availability')) $q->where('availability', $request->availability);
        if ($s = $request->get('search')) $q->where('registration_number', 'like', '%' . strtoupper($s) . '%');
        return $this->ok($q->paginate($this->perPage()));
    }

    public function vehicle(Vehicle $vehicle)
    {
        return $this->ok($vehicle->load('tankerProfile.user:id,name,mobile', 'driver', 'pricing'));
    }

    /** action: approve | reject | request_correction | suspend | reactivate */
    public function reviewVehicle(Request $request, Vehicle $vehicle)
    {
        $data = $request->validate(['action' => 'required|in:approve,reject,request_correction,suspend,reactivate', 'notes' => 'required_if:action,reject,request_correction,suspend|nullable|string|max:500']);
        $status = ['approve' => 'approved', 'reject' => 'rejected', 'request_correction' => 'correction_requested', 'suspend' => 'suspended', 'reactivate' => 'approved'][$data['action']];
        if (in_array($status, ['suspended', 'rejected'], true) && BookingAssignment::where('vehicle_id', $vehicle->id)->whereIn('assignment_status', ['accepted', 'in_transit', 'arrived'])->exists()) {
            throw new BusinessRuleException('Vehicle has an active delivery. Reassign it first.');
        }
        $before = $vehicle->only('status', 'review_notes');
        $vehicle->update(['status' => $status, 'review_notes' => $data['notes'] ?? null]);
        AuditLogger::log('vehicle.' . $data['action'], $vehicle, $before, $vehicle->only('status', 'review_notes'));
        $this->notifier->notify($vehicle->tankerProfile->user_id, 'Vehicle ' . $vehicle->registration_number . ' ' . str_replace('_', ' ', $status), $data['notes'] ?? 'Your vehicle has been approved.', 'vehicle.reviewed', ['vehicle_id' => $vehicle->id]);
        return $this->ok($vehicle, 'Vehicle ' . str_replace('_', ' ', $status));
    }

    public function pricing(Request $request)
    {
        $q = TankerPricing::with('vehicle:id,registration_number,capacity_litres,tanker_profile_id', 'vehicle.tankerProfile:id,owner_name')->latest('id');
        $q->where('approval_status', $request->get('status', 'pending'));
        return $this->ok($q->paginate($this->perPage()));
    }

    /** Approve (optionally adjusting effective dates) or reject a submitted rate. Approval closes the previous approved rate. */
    public function reviewPricing(Request $request, TankerPricing $pricing)
    {
        $data = $request->validate([
            'action' => 'required|in:approve,reject', 'notes' => 'required_if:action,reject|nullable|string|max:255',
            'effective_from' => 'nullable|date', 'effective_to' => 'nullable|date|after_or_equal:effective_from',
        ]);
        if ($pricing->approval_status !== 'pending') throw new BusinessRuleException('Only pending rates can be reviewed.');
        DB::transaction(function () use ($pricing, $data, $request) {
            if ($data['action'] === 'reject') {
                $pricing->update(['approval_status' => 'rejected', 'review_notes' => $data['notes']]);
                return;
            }
            $from = $data['effective_from'] ?? $pricing->effective_from->toDateString();
            // End-date the currently approved rate(s) of the same type so history stays continuous.
            TankerPricing::where('vehicle_id', $pricing->vehicle_id)->where('pricing_type', $pricing->pricing_type)->where('approval_status', 'approved')
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from))
                ->get()->each(function ($old) use ($from) {
                    $end = \Illuminate\Support\Carbon::parse($from)->subDay();
                    $old->effective_from->gt($end) ? $old->update(['approval_status' => 'superseded']) : $old->update(['effective_to' => $end->toDateString()]);
                });
            $pricing->update([
                'approval_status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now(),
                'effective_from' => $from, 'effective_to' => $data['effective_to'] ?? $pricing->effective_to, 'review_notes' => $data['notes'] ?? null,
            ]);
        });
        AuditLogger::log('pricing.' . $data['action'], $pricing, null, $pricing->fresh()->toArray());
        $v = $pricing->vehicle;
        $this->notifier->notify($v->tankerProfile->user_id, 'Rate ' . ($data['action'] === 'approve' ? 'approved' : 'rejected'), "{$v->registration_number}: ₹{$pricing->rate} " . str_replace('_', ' ', $pricing->pricing_type) . (! empty($data['notes']) ? ' — ' . $data['notes'] : ''), 'pricing.reviewed', ['pricing_id' => $pricing->id]);
        return $this->ok($pricing->fresh(), 'Rate ' . ($data['action'] === 'approve' ? 'approved' : 'rejected'));
    }

    /** Admin can set a rate directly (pre-approved). */
    public function storePricing(\App\Http\Requests\PricingRequest $request, Vehicle $vehicle)
    {
        $pricing = $vehicle->pricing()->create($request->validated() + ['approval_status' => 'pending', 'submitted_by' => $request->user()->id, 'delivery_charge' => $request->delivery_charge ?? 0, 'partial_load_rule' => $request->partial_load_rule ?? 'full_trip']);
        $request->merge(['action' => 'approve']);
        return $this->reviewPricing($request, $pricing);
    }
}
