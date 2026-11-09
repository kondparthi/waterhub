<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TankerPricing extends Model
{
    protected $table = 'tanker_pricing';

    protected $fillable = ['vehicle_id', 'pricing_type', 'rate', 'minimum_quantity', 'delivery_charge', 'partial_load_rule', 'effective_from', 'effective_to', 'approval_status', 'submitted_by', 'approved_by', 'approved_at', 'review_notes'];

    protected $casts = ['rate' => 'decimal:4', 'delivery_charge' => 'decimal:2', 'effective_from' => 'date:Y-m-d', 'effective_to' => 'date:Y-m-d', 'approved_at' => 'datetime', 'minimum_quantity' => 'integer'];

    public function vehicle() { return $this->belongsTo(Vehicle::class); }
}
