<?php

namespace App\Http\Controllers\Api\Tanker;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\PricingRequest;
use App\Http\Requests\VehicleRequest;
use App\Models\BookingAssignment;
use App\Models\TankerPricing;
use App\Models\Vehicle;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function __construct(private NotificationService $notifier) {}

    private function owned(Request $request, Vehicle $vehicle): Vehicle
    {
        abort_unless($vehicle->tanker_profile_id === $request->user()->operatingTankerProfile()?->id, 404);
        return $vehicle;
    }

    private function ownerProfile(Request $request)
    {
        $p = $request->user()->tankerProfile;
        if (! $p) throw new BusinessRuleException('Only the tanker owner can manage vehicles.', 403);
        return $p;
    }

    public function index(Request $request)
    {
        $p = $request->user()->operatingTankerProfile();
        return $this->ok($p->vehicles()->with(['driver:id,name,mobile', 'pricing' => fn ($q) => $q->latest('id')])->latest()->get());
    }

    public function store(VehicleRequest $request)
    {
        $p = $this->ownerProfile($request);
        $data = $request->safe()->except('image', 'rc_document');
        if (! empty($data['driver_profile_id'])) abort_unless($p->drivers()->whereKey($data['driver_profile_id'])->exists(), 422, 'Invalid driver');
        $data['driver_profile_id'] = $data['driver_profile_id'] ?? $p->drivers()->where('is_owner', true)->value('id');
        $vehicle = $p->vehicles()->create($data + ['status' => 'pending_review', 'vehicle_type' => $data['vehicle_type'] ?? 'water_tanker']);
        $this->storeFiles($request, $vehicle);
        $this->notifier->notifyAdmins('Vehicle submitted for review', "{$vehicle->registration_number} ({$vehicle->capacity_litres} L) by {$p->owner_name}", 'vehicle.submitted', ['vehicle_id' => $vehicle->id]);
        return $this->created($vehicle->fresh(), 'Vehicle submitted for admin review');
    }

    public function show(Request $request, Vehicle $vehicle)
    {
        return $this->ok($this->owned($request, $vehicle)->load('driver', 'pricing'));
    }

    public function update(VehicleRequest $request, Vehicle $vehicle)
    {
        $this->ownerProfile($request);
        $this->owned($request, $vehicle);
        $data = $request->safe()->except('image', 'rc_document');
        // Material changes send an approved vehicle back for review.
        $material = (isset($data['registration_number']) && $data['registration_number'] !== $vehicle->registration_number)
            || (isset($data['capacity_litres']) && (int) $data['capacity_litres'] !== $vehicle->capacity_litres)
            || $request->hasFile('rc_document');
        if ($material || in_array($vehicle->status, ['rejected', 'correction_requested'], true)) {
            if (BookingAssignment::where('vehicle_id', $vehicle->id)->whereIn('assignment_status', BookingAssignment::ACTIVE)->exists()) {
                throw new BusinessRuleException('Registration/capacity cannot change while the vehicle has an active delivery.');
            }
            $data['status'] = 'pending_review';
        }
        $vehicle->update($data);
        $this->storeFiles($request, $vehicle);
        if (($data['status'] ?? null) === 'pending_review') {
            $this->notifier->notifyAdmins('Vehicle updated — review needed', "{$vehicle->registration_number} was updated.", 'vehicle.submitted', ['vehicle_id' => $vehicle->id]);
        }
        return $this->ok($vehicle->fresh(), ($data['status'] ?? null) === 'pending_review' ? 'Vehicle updated and sent for review' : 'Vehicle updated');
    }

    public function availability(Request $request, Vehicle $vehicle)
    {
        $this->owned($request, $vehicle);
        $data = $request->validate(['availability' => 'required|in:available,unavailable']);
        $busy = BookingAssignment::where('vehicle_id', $vehicle->id)->whereIn('assignment_status', ['accepted', 'in_transit', 'arrived'])->exists();
        if ($busy && $data['availability'] === 'available') {
            throw new BusinessRuleException('Vehicle has an active delivery; it will become available after delivery.');
        }
        $vehicle->update($data);
        return $this->ok($vehicle, 'Availability updated');
    }

    public function pricing(Request $request, Vehicle $vehicle)
    {
        return $this->ok($this->owned($request, $vehicle)->pricing()->latest('id')->get());
    }

    /** Submit a new / changed rate. Requires admin approval before it is used. */
    public function storePricing(PricingRequest $request, Vehicle $vehicle)
    {
        $this->ownerProfile($request);
        $this->owned($request, $vehicle);
        $vehicle->pricing()->where('pricing_type', $request->pricing_type)->where('approval_status', 'pending')->update(['approval_status' => 'superseded', 'review_notes' => 'Replaced by a newer submission']);
        $pricing = $vehicle->pricing()->create($request->validated() + [
            'approval_status' => 'pending', 'submitted_by' => $request->user()->id,
            'delivery_charge' => $request->delivery_charge ?? 0, 'partial_load_rule' => $request->partial_load_rule ?? 'full_trip',
        ]);
        $this->notifier->notifyAdmins('Pricing submitted', "{$vehicle->registration_number}: ₹{$pricing->rate} " . str_replace('_', ' ', $pricing->pricing_type) . ' from ' . $pricing->effective_from->format('d M Y'), 'pricing.submitted', ['pricing_id' => $pricing->id]);
        return $this->created($pricing, 'Rate submitted for admin approval');
    }

    private function storeFiles(Request $request, Vehicle $vehicle): void
    {
        $upd = [];
        if ($request->hasFile('image')) $upd['image_path'] = $request->file('image')->store('vehicles/' . $vehicle->id, 'local');
        if ($request->hasFile('rc_document')) $upd['rc_path'] = $request->file('rc_document')->store('vehicles/' . $vehicle->id, 'local');
        if ($upd) $vehicle->update($upd);
    }
}
