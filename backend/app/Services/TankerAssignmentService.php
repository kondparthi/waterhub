<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\DriverProfile;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Geo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Nearest-eligible-tanker suggestions + manual assignment (admin decides in V1.0),
 * and the driver-side assignment lifecycle (accept → start → arrive → deliver).
 */
class TankerAssignmentService
{
    public function __construct(private BookingService $bookings, private NotificationService $notifier, private PricingService $pricing) {}

    /** Returns ['eligible' => [...], 'excluded' => [...]] sorted by distance. */
    public function suggestions(Booking $booking): array
    {
        $vehicles = Vehicle::with(['tankerProfile.user', 'driver', 'pricing' => fn ($q) => $q->where('approval_status', 'approved')])->get();
        $eligible = [];
        $excluded = [];
        foreach ($vehicles as $v) {
            $row = $this->evaluate($booking, $v);
            if ($row['reasons']) $excluded[] = $row; else $eligible[] = $row;
        }
        $sort = fn ($a, $b) => ($a['distance_km'] ?? INF) <=> ($b['distance_km'] ?? INF);
        usort($eligible, $sort);
        usort($excluded, $sort);
        return ['eligible' => $eligible, 'excluded' => $excluded];
    }

    public function evaluate(Booking $booking, Vehicle $v): array
    {
        $reasons = [];
        $profile = $v->tankerProfile;
        $date = $booking->preferred_date->toDateString();

        if (! $v->isAssignable()) $reasons[] = 'Vehicle status: ' . str_replace('_', ' ', $v->status);
        if (! $profile || ! $profile->isApproved()) $reasons[] = 'Tanker profile not approved';
        if ($profile && $profile->user?->status !== 'active') $reasons[] = 'Owner account not active';
        if ($v->availability === 'unavailable') $reasons[] = 'Marked unavailable';
        if ($booking->requested_capacity && $v->capacity_litres < $booking->requested_capacity) {
            $reasons[] = "Capacity {$v->capacity_litres} L below requested {$booking->requested_capacity} L";
        }
        if (! empty($v->water_types) && ! in_array($booking->water_type, $v->water_types, true)) {
            $reasons[] = 'Does not carry ' . $booking->water_type;
        }
        if ($v->driver && $v->driver->status !== 'active') $reasons[] = 'Assigned driver inactive';

        $conflict = BookingAssignment::where('vehicle_id', $v->id)->whereIn('assignment_status', BookingAssignment::ACTIVE)
            ->where('booking_id', '!=', $booking->id)
            ->whereHas('booking', fn ($q) => $q->whereDate('preferred_date', $date))->with('booking:id,booking_number')->first();
        if ($conflict) $reasons[] = 'Busy with ' . $conflict->booking->booking_number . ' on the same date';

        // Location: latest GPS (if within 24h) else registered base location.
        $locSource = 'none';
        $lat = $lng = null;
        if ($v->last_latitude && $v->last_location_at && $v->last_location_at->gt(now()->subDay())) {
            [$lat, $lng, $locSource] = [$v->last_latitude, $v->last_longitude, 'gps'];
        } elseif ($v->base_latitude) {
            [$lat, $lng, $locSource] = [$v->base_latitude, $v->base_longitude, 'base'];
        } elseif ($profile?->base_latitude) {
            [$lat, $lng, $locSource] = [$profile->base_latitude, $profile->base_longitude, 'base'];
        }
        $distance = $lat !== null ? round(Geo::distanceKm($lat, $lng, $booking->latitude, $booking->longitude), 2) : null;

        // Service area: explicit area names match, otherwise radius from base.
        $areas = array_filter((array) $v->service_areas);
        $areaMatch = $booking->area && collect($areas)->contains(fn ($a) => stripos($booking->area, $a) !== false || stripos($a, $booking->area) !== false);
        $radius = $v->service_radius_km ?: (int) SettingsService::get('default_service_radius_km');
        $baseLat = $v->base_latitude ?? $profile?->base_latitude;
        $baseLng = $v->base_longitude ?? $profile?->base_longitude;
        if (! $areaMatch && $baseLat && $radius) {
            $fromBase = Geo::distanceKm($baseLat, $baseLng, $booking->latitude, $booking->longitude);
            if ($fromBase > $radius) $reasons[] = 'Outside service radius (' . round($fromBase, 1) . " km > {$radius} km)";
        } elseif (! $areaMatch && $areas && ! $baseLat) {
            $reasons[] = 'Area not in service areas';
        }

        $prices = [];
        foreach (['per_litre', 'per_trip'] as $type) {
            $p = $v->effectivePricing($type, $date);
            if (! $p) continue;
            try {
                $calc = $this->pricing->calculate([
                    'pricing_type' => $type, 'rate' => $p->rate, 'quantity_litres' => $booking->quantity_litres,
                    'vehicle_capacity' => $v->capacity_litres, 'minimum_quantity' => $p->minimum_quantity,
                    'delivery_charge' => $p->delivery_charge, 'partial_load_rule' => $p->partial_load_rule,
                ]);
                $prices[$type] = ['pricing_id' => $p->id, 'rate' => (float) $p->rate, 'delivery_charge' => (float) $p->delivery_charge, 'trips' => $calc['trips'], 'estimated_total' => $calc['total_amount']];
            } catch (BusinessRuleException) {
            }
        }
        if (! $prices) $reasons[] = 'No approved rate effective on ' . $date;

        return [
            'vehicle_id' => $v->id,
            'registration_number' => $v->registration_number,
            'capacity_litres' => $v->capacity_litres,
            'trips_needed' => (int) ceil($booking->quantity_litres / max(1, $v->capacity_litres)),
            'availability' => $v->availability,
            'owner' => $profile ? ['id' => $profile->id, 'name' => $profile->owner_name, 'business_name' => $profile->business_name, 'mobile' => $profile->user?->mobile] : null,
            'driver' => $v->driver ? ['id' => $v->driver->id, 'name' => $v->driver->name, 'mobile' => $v->driver->mobile] : null,
            'distance_km' => $distance,
            'location_source' => $locSource,
            'last_gps_at' => $v->last_location_at?->toIso8601String(),
            'prices' => $prices,
            'reasons' => $reasons,
        ];
    }

    public function assign(Booking $booking, int $vehicleId, User $admin, ?int $driverProfileId = null, ?string $notes = null): BookingAssignment
    {
        return DB::transaction(function () use ($booking, $vehicleId, $admin, $driverProfileId, $notes) {
            $booking = Booking::lockForUpdate()->findOrFail($booking->id);
            $vehicle = Vehicle::with('tankerProfile.user', 'driver')->lockForUpdate()->findOrFail($vehicleId);

            if (! in_array($booking->status, ['confirmed', 'assigned', 'accepted'], true)) {
                throw new BusinessRuleException('Only confirmed bookings can be assigned (current: ' . $this->bookings->label($booking->status) . ').');
            }
            // Re-check eligibility at assignment time (not only when showing the list).
            $check = $this->evaluate($booking, $vehicle);
            if ($check['reasons']) throw new BusinessRuleException('Vehicle not eligible: ' . implode('; ', $check['reasons']), 409);

            $driver = $driverProfileId ? DriverProfile::where('tanker_profile_id', $vehicle->tanker_profile_id)->where('status', 'active')->findOrFail($driverProfileId) : $vehicle->driver;
            $driverUserId = $driver?->user_id ?: $vehicle->tankerProfile->user_id;

            $previous = BookingAssignment::where('booking_id', $booking->id)->whereIn('assignment_status', BookingAssignment::ACTIVE)->lockForUpdate()->get();
            foreach ($previous as $p) {
                if ($p->vehicle_id === $vehicle->id) throw new BusinessRuleException('This vehicle is already assigned to the booking.');
                $wasAccepted = $p->assignment_status !== 'assigned';
                $p->update(['assignment_status' => 'superseded']);
                if ($wasAccepted) $p->vehicle?->update(['availability' => 'available']);
                $this->notifier->notify([$p->driver_user_id, $p->vehicle?->tankerProfile?->user_id], 'Assignment withdrawn', "Booking {$booking->booking_number} has been reassigned.", 'assignment.withdrawn', ['assignment_id' => $p->id]);
            }

            $assignment = BookingAssignment::create([
                'booking_id' => $booking->id, 'vehicle_id' => $vehicle->id, 'driver_profile_id' => $driver?->id,
                'driver_user_id' => $driverUserId, 'assigned_by' => $admin->id, 'assignment_status' => 'assigned',
                'assigned_at' => now(), 'response_due_at' => now()->addMinutes((int) SettingsService::get('driver_response_timeout_minutes')),
            ]);

            $this->bookings->transition($booking, 'assigned', $admin, trim(($previous->isNotEmpty() ? 'Reassigned to ' : 'Assigned to ') . $vehicle->registration_number . ($notes ? " — {$notes}" : '')));

            $this->notifier->notify(array_unique([$driverUserId, $vehicle->tankerProfile->user_id]), 'New delivery assigned', "Booking {$booking->booking_number}: {$booking->quantity_litres} L {$booking->water_type}, {$booking->area} on {$booking->preferred_date->format('d M')}. Please accept or reject.", 'assignment.new', ['assignment_id' => $assignment->id]);
            $this->notifier->notify($booking->customer_id, 'Tanker assigned', "Tanker {$vehicle->registration_number} has been assigned to booking {$booking->booking_number}.", 'booking.assigned', ['booking_id' => $booking->id]);
            AuditLogger::log('booking.assigned', $booking, null, ['assignment_id' => $assignment->id, 'vehicle_id' => $vehicle->id]);
            return $assignment;
        });
    }

    // ---------------- Driver-side lifecycle ----------------

    private function lockOwned(BookingAssignment $a, User $user): BookingAssignment
    {
        $a = BookingAssignment::with('booking', 'vehicle.tankerProfile')->lockForUpdate()->findOrFail($a->id);
        if (! $a->belongsToTankerUser($user)) throw new BusinessRuleException('This assignment does not belong to you.', 403);
        return $a;
    }

    private function requireStatus(BookingAssignment $a, array $statuses): void
    {
        if (! in_array($a->assignment_status, $statuses, true)) {
            throw new BusinessRuleException('Action not allowed while assignment is ' . str_replace('_', ' ', $a->assignment_status) . '.');
        }
    }

    public function accept(BookingAssignment $a, User $user): BookingAssignment
    {
        return DB::transaction(function () use ($a, $user) {
            $a = $this->lockOwned($a, $user);
            $this->requireStatus($a, ['assigned']);
            $a->update(['assignment_status' => 'accepted', 'accepted_at' => now()]);
            $a->vehicle->update(['availability' => 'busy']);
            $this->bookings->transition($a->booking, 'accepted', $user, 'Driver accepted (' . $a->vehicle->registration_number . ')');
            $this->notifier->notify($a->booking->customer_id, 'Driver accepted', "Tanker {$a->vehicle->registration_number} accepted your booking {$a->booking->booking_number}.", 'assignment.accepted', ['booking_id' => $a->booking_id]);
            $this->notifier->notifyAdmins('Driver accepted', "{$a->vehicle->registration_number} accepted {$a->booking->booking_number}.", 'assignment.accepted', ['booking_id' => $a->booking_id]);
            return $a;
        });
    }

    public function reject(BookingAssignment $a, User $user, ?string $reason): BookingAssignment
    {
        return DB::transaction(function () use ($a, $user, $reason) {
            $a = $this->lockOwned($a, $user);
            $this->requireStatus($a, ['assigned', 'accepted']);
            $wasAccepted = $a->assignment_status === 'accepted';
            $a->update(['assignment_status' => 'rejected', 'rejection_reason' => $reason ?: 'No reason given']);
            if ($wasAccepted) $a->vehicle->update(['availability' => 'available']);
            $this->bookings->transition($a->booking, 'confirmed', $user, 'Driver rejected: ' . ($reason ?: 'no reason given'));
            $this->notifier->notifyAdmins('Driver rejected assignment', "{$a->vehicle->registration_number} rejected {$a->booking->booking_number}. Reason: " . ($reason ?: '—') . '. Please reassign.', 'assignment.rejected', ['booking_id' => $a->booking_id]);
            $this->notifier->notify($a->booking->customer_id, 'Finding another tanker', "We are assigning another tanker for {$a->booking->booking_number}.", 'assignment.rejected', ['booking_id' => $a->booking_id]);
            return $a;
        });
    }

    public function start(BookingAssignment $a, User $user): BookingAssignment
    {
        return DB::transaction(function () use ($a, $user) {
            $a = $this->lockOwned($a, $user);
            $this->requireStatus($a, ['accepted']);
            $other = BookingAssignment::where('vehicle_id', $a->vehicle_id)->where('id', '!=', $a->id)->whereIn('assignment_status', BookingAssignment::TRACKABLE)->with('booking:id,booking_number')->first();
            if ($other) throw new BusinessRuleException('Finish delivery ' . $other->booking->booking_number . ' before starting another.');
            $a->update(['assignment_status' => 'in_transit', 'started_at' => now()]);
            $this->bookings->transition($a->booking, 'in_transit', $user, 'Delivery started');
            $this->notifier->notify($a->booking->customer_id, 'Tanker on the way', "Your tanker for {$a->booking->booking_number} is on the way. Live tracking is now available.", 'delivery.started', ['booking_id' => $a->booking_id]);
            $this->notifier->notifyAdmins('Delivery started', "{$a->vehicle->registration_number} started {$a->booking->booking_number}.", 'delivery.started', ['booking_id' => $a->booking_id]);
            return $a;
        });
    }

    public function arrive(BookingAssignment $a, User $user): BookingAssignment
    {
        return DB::transaction(function () use ($a, $user) {
            $a = $this->lockOwned($a, $user);
            $this->requireStatus($a, ['in_transit']);
            $a->update(['assignment_status' => 'arrived', 'arrived_at' => now()]);
            $this->bookings->transition($a->booking, 'arrived', $user, 'Tanker arrived at delivery location');
            $this->notifier->notify($a->booking->customer_id, 'Tanker arrived', "Your tanker for {$a->booking->booking_number} has arrived.", 'delivery.arrived', ['booking_id' => $a->booking_id]);
            $this->notifier->notifyAdmins('Tanker arrived', "{$a->vehicle->registration_number} arrived for {$a->booking->booking_number}.", 'delivery.arrived', ['booking_id' => $a->booking_id]);
            return $a;
        });
    }

    public function delivered(BookingAssignment $a, User $user, array $data, $proofFile = null): BookingAssignment
    {
        return DB::transaction(function () use ($a, $user, $data, $proofFile) {
            $a = $this->lockOwned($a, $user);
            $this->requireStatus($a, ['in_transit', 'arrived']);
            $proofPath = $proofFile ? $proofFile->store('proofs/' . $a->booking_id, 'local') : null;
            $litres = (int) ($data['delivered_litres'] ?? $a->booking->quantity_litres);
            $a->update([
                'assignment_status' => 'delivered', 'delivered_at' => now(), 'arrived_at' => $a->arrived_at ?? now(),
                'proof_path' => $proofPath, 'proof_notes' => $data['proof_notes'] ?? null, 'delivered_litres' => $litres,
            ]);
            $a->vehicle->update(['availability' => 'available']);
            $this->bookings->transition($a->booking, 'delivered', $user, 'Delivered ' . $litres . ' L' . (! empty($data['proof_notes']) ? ' — ' . $data['proof_notes'] : ''), ['delivered_litres' => $litres]);
            $this->notifier->notify($a->booking->customer_id, 'Water delivered', "Booking {$a->booking->booking_number}: {$litres} L delivered.", 'delivery.delivered', ['booking_id' => $a->booking_id]);
            $this->notifier->notifyAdmins('Delivery completed by driver', "{$a->booking->booking_number} delivered — verify and close.", 'delivery.delivered', ['booking_id' => $a->booking_id]);
            return $a;
        });
    }
}
