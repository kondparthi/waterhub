<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\TankerLocation;
use App\Models\User;
use App\Support\Geo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TrackingService
{
    public function record(User $user, array $data): TankerLocation
    {
        $a = BookingAssignment::with('vehicle')->findOrFail($data['assignment_id']);
        if (! $a->belongsToTankerUser($user)) throw new BusinessRuleException('This assignment does not belong to you.', 403);
        if (! in_array($a->assignment_status, BookingAssignment::TRACKABLE, true)) {
            throw new BusinessRuleException('Location sharing is only accepted during an active delivery.', 409);
        }
        if (! Geo::validCoordinate($data['latitude'], $data['longitude'])) throw new BusinessRuleException('Invalid coordinates.', 422);

        $recordedAt = isset($data['recorded_at']) ? Carbon::parse($data['recorded_at'])->utc() : now();
        if ($recordedAt->gt(now()->addMinutes(2))) $recordedAt = now();
        if ($recordedAt->lt(now()->subHour())) throw new BusinessRuleException('Location point is too old.', 422);

        if ($a->last_latitude && $a->last_location_at) {
            $km = Geo::distanceKm($a->last_latitude, $a->last_longitude, (float) $data['latitude'], (float) $data['longitude']);
            $hours = max(1, abs($recordedAt->diffInSeconds($a->last_location_at))) / 3600;
            $max = (int) SettingsService::get('gps_max_speed_kmph');
            if ($km > 0.5 && $km / $hours > $max) {
                AuditLogger::log('gps.suspicious', $a, ['lat' => $a->last_latitude, 'lng' => $a->last_longitude], ['lat' => $data['latitude'], 'lng' => $data['longitude'], 'kmph' => round($km / $hours)]);
                throw new BusinessRuleException('Location ignored: impossible jump detected.', 422);
            }
        }

        return DB::transaction(function () use ($a, $data, $recordedAt) {
            $loc = TankerLocation::create([
                'assignment_id' => $a->id, 'latitude' => $data['latitude'], 'longitude' => $data['longitude'],
                'accuracy_m' => $data['accuracy_m'] ?? null, 'speed' => $data['speed'] ?? null, 'heading' => $data['heading'] ?? null,
                'recorded_at' => $recordedAt, 'received_at' => now(),
            ]);
            // Latest location is kept on the assignment and vehicle for fast map refresh.
            $a->update(['last_latitude' => $data['latitude'], 'last_longitude' => $data['longitude'], 'last_accuracy_m' => $data['accuracy_m'] ?? null, 'last_location_at' => $recordedAt]);
            $a->vehicle->update(['last_latitude' => $data['latitude'], 'last_longitude' => $data['longitude'], 'last_location_at' => $recordedAt]);
            return $loc;
        });
    }

    public function forBooking(Booking $booking, bool $isAdmin): array
    {
        $a = $booking->assignments()->whereIn('assignment_status', ['accepted', 'in_transit', 'arrived', 'delivered'])->with('vehicle', 'driver')->first();
        $trackable = $a && in_array($a->assignment_status, BookingAssignment::TRACKABLE, true);
        $staleAfter = (int) SettingsService::get('gps_stale_after_seconds');
        $age = $a?->last_location_at ? (int) abs(now()->diffInSeconds($a->last_location_at)) : null;

        $trail = [];
        if ($trackable) {
            $trail = TankerLocation::where('assignment_id', $a->id)->orderByDesc('recorded_at')->limit(200)->get(['latitude', 'longitude', 'recorded_at'])
                ->reverse()->values()->map(fn ($l) => [$l->latitude, $l->longitude])->all();
        }

        return [
            'booking_id' => $booking->id,
            'booking_number' => $booking->booking_number,
            'status' => $booking->status,
            'trackable' => $trackable,
            'destination' => ['latitude' => $booking->latitude, 'longitude' => $booking->longitude, 'address' => $booking->delivery_address],
            'source' => $isAdmin && $booking->borewell ? ['name' => $booking->borewell->name, 'latitude' => $booking->borewell->latitude, 'longitude' => $booking->borewell->longitude] : null,
            'assignment' => $a ? [
                'id' => $a->id, 'status' => $a->assignment_status,
                'vehicle' => $a->vehicle?->registration_number, 'capacity_litres' => $a->vehicle?->capacity_litres,
                'driver_name' => $a->driver?->name ?? $a->vehicle?->tankerProfile?->owner_name,
                'driver_mobile' => $a->driver?->mobile ?? $a->vehicle?->tankerProfile?->user?->mobile,
                'started_at' => $a->started_at?->toIso8601String(), 'arrived_at' => $a->arrived_at?->toIso8601String(),
            ] : null,
            'location' => $trackable && $a->last_location_at ? [
                'latitude' => $a->last_latitude, 'longitude' => $a->last_longitude, 'accuracy_m' => $a->last_accuracy_m,
                'updated_at' => $a->last_location_at->toIso8601String(), 'age_seconds' => $age, 'stale' => $age > $staleAfter,
            ] : null,
            'trail' => $trail,
            'refresh_seconds' => (int) SettingsService::get('map_refresh_seconds'),
        ];
    }

    /** All active deliveries for the admin live map. */
    public function activeDeliveries(): array
    {
        $staleAfter = (int) SettingsService::get('gps_stale_after_seconds');
        return BookingAssignment::with(['booking:id,booking_number,latitude,longitude,delivery_address,area,status,customer_id', 'booking.customer:id,name,mobile', 'vehicle:id,registration_number,capacity_litres', 'driver:id,name,mobile'])
            ->whereIn('assignment_status', ['accepted', 'in_transit', 'arrived'])->get()
            ->map(function ($a) use ($staleAfter) {
                $age = $a->last_location_at ? (int) abs(now()->diffInSeconds($a->last_location_at)) : null;
                return [
                    'assignment_id' => $a->id, 'status' => $a->assignment_status,
                    'booking' => $a->booking, 'vehicle' => $a->vehicle, 'driver' => $a->driver,
                    'location' => $a->last_location_at ? ['latitude' => $a->last_latitude, 'longitude' => $a->last_longitude, 'updated_at' => $a->last_location_at->toIso8601String(), 'age_seconds' => $age, 'stale' => $age > $staleAfter] : null,
                ];
            })->all();
    }
}
