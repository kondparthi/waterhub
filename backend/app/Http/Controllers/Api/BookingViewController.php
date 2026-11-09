<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingService;
use App\Services\TrackingService;
use Illuminate\Http\Request;

/** Shared booking detail + tracking for the owning customer and admin. */
class BookingViewController extends Controller
{
    public function __construct(private BookingService $bookings, private TrackingService $tracking) {}

    public function show(Request $request, Booking $booking)
    {
        $this->authorize('view', $booking);
        $user = $request->user();
        $booking->load(['items', 'statusHistory.actor:id,name,role', 'payments' => fn ($q) => $q->where('status', 'received')]);
        if ($user->isAdmin()) {
            $booking->load(['customer:id,name,mobile,email', 'borewell', 'enquiry:id,enquiry_number', 'assignments.vehicle:id,registration_number,capacity_litres', 'assignments.driver:id,name,mobile', 'assignments.assignedBy:id,name', 'payments.receiver:id,name']);
        }
        $data = $booking->toArray();
        $active = $booking->assignments()->whereIn('assignment_status', ['assigned', 'accepted', 'in_transit', 'arrived', 'delivered', 'completed'])->with('vehicle', 'driver', 'vehicle.tankerProfile.user:id,mobile')->first();
        $data['current_assignment'] = $active ? [
            'id' => $active->id, 'status' => $active->assignment_status,
            'vehicle' => $active->vehicle?->registration_number, 'capacity_litres' => $active->vehicle?->capacity_litres,
            // Driver contact is shared with the customer only once the driver has accepted.
            'driver_name' => $active->driver?->name ?? $active->vehicle?->tankerProfile?->owner_name,
            'driver_mobile' => ($user->isAdmin() || $active->assignment_status !== 'assigned') ? ($active->driver?->mobile ?? $active->vehicle?->tankerProfile?->user?->mobile) : null,
            'response_overdue' => $active->response_overdue, 'response_due_at' => $active->response_due_at,
            'has_proof' => $active->has_proof, 'proof_notes' => $active->proof_notes, 'delivered_litres' => $active->delivered_litres,
        ] : null;
        if (! $user->isAdmin()) {
            $data['source'] = $booking->borewell ? ['name' => $booking->borewell->name] : null;
            unset($data['borewell']);
        }
        $data['balance_due'] = $booking->balanceDue();
        $data['can_cancel'] = $user->isAdmin() ? $this->bookings->canTransition($booking->status, 'cancelled', 'admin') : $this->bookings->customerCanCancel($booking);
        $data['cancellation_fee_if_cancelled'] = $this->bookings->cancellationFee($booking);
        $data['trackable'] = in_array($booking->status, ['in_transit', 'arrived'], true);
        return $this->ok($data);
    }

    public function tracking(Request $request, Booking $booking)
    {
        $this->authorize('track', $booking);
        return $this->ok($this->tracking->forBooking($booking, $request->user()->isAdmin()));
    }
}
