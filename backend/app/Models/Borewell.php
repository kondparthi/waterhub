<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Borewell extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'source_code', 'source_type', 'address', 'area', 'latitude', 'longitude', 'water_types', 'internal_cost_per_litre', 'daily_capacity_litres', 'track_capacity', 'opening_time', 'closing_time', 'contact_person', 'contact_mobile', 'notes', 'status'];

    protected $casts = ['water_types' => 'array', 'latitude' => 'float', 'longitude' => 'float', 'track_capacity' => 'boolean', 'internal_cost_per_litre' => 'decimal:4', 'daily_capacity_litres' => 'integer'];

    public function bookings() { return $this->hasMany(Booking::class); }

    public function supports(string $waterType): bool
    {
        return empty($this->water_types) || in_array($waterType, $this->water_types, true);
    }

    /** Litres already committed from this source on a date (only meaningful when capacity is tracked). */
    public function committedLitres(string $date, ?int $exceptBookingId = null): int
    {
        return (int) $this->bookings()->whereDate('preferred_date', $date)
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->when($exceptBookingId, fn ($q) => $q->where('id', '!=', $exceptBookingId))
            ->sum('quantity_litres');
    }
}
