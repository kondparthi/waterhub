<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use SoftDeletes;

    public const STATUSES = ['new', 'under_review', 'quoted', 'confirmed', 'assigned', 'accepted', 'in_transit', 'arrived', 'delivered', 'completed', 'rejected', 'cancelled'];
    public const ACTIVE_DELIVERY = ['accepted', 'in_transit', 'arrived'];
    public const FINAL = ['completed', 'rejected', 'cancelled'];

    protected $fillable = ['customer_id', 'enquiry_id', 'borewell_id', 'water_type', 'quantity_litres', 'requested_capacity', 'delivery_address', 'area', 'landmark', 'latitude', 'longitude', 'preferred_date', 'preferred_time_slot', 'notes', 'status', 'pricing_type', 'subtotal', 'delivery_charge', 'tax', 'discount', 'total_amount', 'currency', 'payment_status', 'amount_paid', 'quotation_notes', 'quoted_at', 'customer_accepted_at', 'confirmed_at', 'completed_at', 'cancelled_at', 'cancel_reason', 'cancellation_fee', 'cancelled_by', 'delivered_litres'];

    protected $casts = [
        'latitude' => 'float', 'longitude' => 'float', 'preferred_date' => 'date:Y-m-d',
        'subtotal' => 'decimal:2', 'delivery_charge' => 'decimal:2', 'tax' => 'decimal:2', 'discount' => 'decimal:2',
        'total_amount' => 'decimal:2', 'amount_paid' => 'decimal:2', 'cancellation_fee' => 'decimal:2',
        'quoted_at' => 'datetime', 'customer_accepted_at' => 'datetime', 'confirmed_at' => 'datetime',
        'completed_at' => 'datetime', 'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (Booking $b) {
            $b->booking_number = sprintf('WT-%s-%06d', now()->year, $b->id);
            $b->saveQuietly();
        });
    }

    public function customer() { return $this->belongsTo(User::class, 'customer_id'); }
    public function borewell() { return $this->belongsTo(Borewell::class)->withTrashed(); }
    public function enquiry() { return $this->belongsTo(Enquiry::class); }
    public function items() { return $this->hasMany(BookingItem::class); }
    public function assignments() { return $this->hasMany(BookingAssignment::class)->latest('id'); }
    public function statusHistory() { return $this->hasMany(BookingStatusHistory::class)->orderBy('id'); }
    public function payments() { return $this->hasMany(Payment::class)->latest('paid_at'); }

    public function activeAssignment()
    {
        return $this->hasOne(BookingAssignment::class)->whereIn('assignment_status', BookingAssignment::ACTIVE)->latestOfMany();
    }

    public function balanceDue(): float
    {
        $due = $this->status === 'cancelled' ? (float) $this->cancellation_fee : (float) $this->total_amount;
        return round(max(0, $due - (float) $this->amount_paid), 2);
    }
}
