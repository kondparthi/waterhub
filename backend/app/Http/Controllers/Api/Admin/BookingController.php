<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuoteRequest;
use App\Models\Booking;
use App\Models\Borewell;
use App\Services\BookingService;
use App\Services\PricingService;
use App\Services\TankerAssignmentService;
use App\Support\Geo;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(private BookingService $bookings, private TankerAssignmentService $assignments, private PricingService $pricing) {}

    public function index(Request $request)
    {
        $q = Booking::with('customer:id,name,mobile', 'borewell:id,name', 'activeAssignment.vehicle:id,registration_number')->latest();
        if ($request->filled('status')) $q->whereIn('status', explode(',', $request->status));
        if ($request->filled('payment_status')) $q->where('payment_status', $request->payment_status);
        if ($request->filled('borewell_id')) $q->where('borewell_id', $request->borewell_id);
        if ($request->filled('date')) $q->whereDate('preferred_date', $request->date);
        if ($request->filled('from')) $q->whereDate('preferred_date', '>=', $request->from);
        if ($request->filled('to')) $q->whereDate('preferred_date', '<=', $request->to);
        if ($request->filled('customer_id')) $q->where('customer_id', $request->customer_id);
        if ($s = $request->get('search')) $q->where(fn ($w) => $w->where('booking_number', 'like', "%{$s}%")->orWhere('area', 'like', "%{$s}%")->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%")->orWhere('mobile', 'like', "%{$s}%")));
        return $this->ok($q->paginate($this->perPage()));
    }

    public function review(Request $request, Booking $booking)
    {
        return $this->ok($this->bookings->markUnderReview($booking, $request->user(), $request->input('notes')), 'Booking under review');
    }

    /** Active sources compatible with the booking's water type, nearest first. */
    public function sources(Booking $booking)
    {
        $date = $booking->preferred_date->toDateString();
        $rows = Borewell::orderBy('name')->get()->map(function ($s) use ($booking, $date) {
            $remaining = $s->track_capacity && $s->daily_capacity_litres ? $s->daily_capacity_litres - $s->committedLitres($date, $booking->id) : null;
            $issues = [];
            if ($s->status !== 'active') $issues[] = ucfirst($s->status);
            if (! $s->supports($booking->water_type)) $issues[] = 'Does not supply ' . $booking->water_type;
            if ($remaining !== null && $remaining < $booking->quantity_litres) $issues[] = "Only {$remaining} L left";
            return $s->only('id', 'name', 'source_code', 'source_type', 'area', 'status', 'latitude', 'longitude', 'water_types', 'opening_time', 'closing_time') + [
                'distance_km' => round(Geo::distanceKm($s->latitude, $s->longitude, $booking->latitude, $booking->longitude), 2),
                'remaining_litres' => $remaining, 'issues' => $issues, 'selectable' => ! $issues,
            ];
        })->sortBy([['selectable', 'desc'], ['distance_km', 'asc']])->values();
        return $this->ok($rows);
    }

    public function selectSource(Request $request, Booking $booking)
    {
        $data = $request->validate(['borewell_id' => 'required|integer|exists:borewells,id']);
        return $this->ok($this->bookings->selectSource($booking, $data['borewell_id'], $request->user())->load('borewell'), 'Source selected');
    }

    public function quote(QuoteRequest $request, Booking $booking)
    {
        if ($request->boolean('preview')) {
            return $this->ok($this->pricing->quoteForBooking($booking, $request->validated()), 'Price preview');
        }
        return $this->ok($this->bookings->quote($booking, $request->validated(), $request->user()), 'Quotation sent to customer');
    }

    public function confirm(Request $request, Booking $booking)
    {
        $data = $request->validate(['notes' => 'nullable|string|max:500', 'customer_accepted_offline' => 'nullable|boolean']);
        return $this->ok($this->bookings->confirm($booking, $request->user(), $data['notes'] ?? null, (bool) ($data['customer_accepted_offline'] ?? false)), 'Booking confirmed');
    }

    public function eligibleTankers(Booking $booking)
    {
        return $this->ok($this->assignments->suggestions($booking));
    }

    public function assign(Request $request, Booking $booking)
    {
        $data = $request->validate(['vehicle_id' => 'required|integer|exists:vehicles,id', 'driver_profile_id' => 'nullable|integer|exists:driver_profiles,id', 'notes' => 'nullable|string|max:255']);
        $a = $this->assignments->assign($booking, $data['vehicle_id'], $request->user(), $data['driver_profile_id'] ?? null, $data['notes'] ?? null);
        return $this->ok($a->load('vehicle:id,registration_number', 'driver:id,name,mobile'), 'Tanker assigned; waiting for driver response');
    }

    public function cancel(Request $request, Booking $booking)
    {
        $data = $request->validate(['reason' => 'required|string|max:255', 'cancellation_fee' => 'nullable|numeric|min:0']);
        return $this->ok($this->bookings->cancel($booking, $request->user(), $data['reason'], isset($data['cancellation_fee']) ? (float) $data['cancellation_fee'] : null), 'Booking cancelled');
    }

    public function reject(Request $request, Booking $booking)
    {
        $data = $request->validate(['reason' => 'required|string|max:255']);
        return $this->ok($this->bookings->reject($booking, $request->user(), $data['reason']), 'Booking rejected');
    }

    public function complete(Request $request, Booking $booking)
    {
        $data = $request->validate(['notes' => 'nullable|string|max:500']);
        return $this->ok($this->bookings->complete($booking, $request->user(), $data['notes'] ?? null), 'Booking completed');
    }
}
