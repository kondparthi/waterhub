<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingItem extends Model
{
    protected $fillable = ['booking_id', 'vehicle_id', 'tanker_pricing_id', 'pricing_type', 'rate_snapshot', 'quantity_litres', 'billable_litres', 'vehicle_capacity', 'trips', 'calculated_trips', 'trip_override_reason', 'delivery_charge', 'line_total'];
    protected $casts = ['rate_snapshot' => 'decimal:4', 'delivery_charge' => 'decimal:2', 'line_total' => 'decimal:2'];

    public function vehicle() { return $this->belongsTo(Vehicle::class)->withTrashed(); }
}
