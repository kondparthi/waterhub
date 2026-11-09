<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use SoftDeletes;

    public const ASSIGNABLE_STATUSES = ['approved', 'active'];

    protected $fillable = ['tanker_profile_id', 'driver_profile_id', 'registration_number', 'vehicle_type', 'capacity_litres', 'image_path', 'rc_path', 'service_areas', 'service_radius_km', 'base_latitude', 'base_longitude', 'water_types', 'status', 'availability', 'review_notes', 'last_latitude', 'last_longitude', 'last_location_at'];

    protected $casts = [
        'service_areas' => 'array', 'water_types' => 'array', 'capacity_litres' => 'integer',
        'base_latitude' => 'float', 'base_longitude' => 'float', 'last_latitude' => 'float', 'last_longitude' => 'float',
        'last_location_at' => 'datetime', 'service_radius_km' => 'integer',
    ];

    protected $hidden = ['image_path', 'rc_path'];
    protected $appends = ['has_image', 'has_rc'];

    public function tankerProfile() { return $this->belongsTo(TankerProfile::class); }
    public function driver() { return $this->belongsTo(DriverProfile::class, 'driver_profile_id'); }
    public function pricing() { return $this->hasMany(TankerPricing::class); }
    public function assignments() { return $this->hasMany(BookingAssignment::class); }

    public function getHasImageAttribute(): bool { return (bool) $this->image_path; }
    public function getHasRcAttribute(): bool { return (bool) $this->rc_path; }

    public function isAssignable(): bool { return in_array($this->status, self::ASSIGNABLE_STATUSES, true); }

    /** Approved pricing effective on a given date for a pricing type. */
    public function effectivePricing(string $type, $date = null): ?TankerPricing
    {
        $date = $date ? \Illuminate\Support\Carbon::parse($date)->toDateString() : now()->toDateString();
        return $this->pricing()
            ->where('pricing_type', $type)->where('approval_status', 'approved')
            ->whereDate('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->orderByDesc('effective_from')->orderByDesc('id')->first();
    }
}
