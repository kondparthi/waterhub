<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Enquiry extends Model
{
    use SoftDeletes;

    protected $fillable = ['enquiry_number', 'customer_id', 'water_type', 'quantity_litres', 'requested_capacity', 'delivery_address', 'area', 'landmark', 'latitude', 'longitude', 'preferred_date', 'preferred_time_slot', 'notes', 'alternate_contact', 'status', 'quoted_amount', 'quotation_details', 'customer_accepted_at', 'converted_booking_id', 'close_reason'];

    protected $casts = ['latitude' => 'float', 'longitude' => 'float', 'preferred_date' => 'date:Y-m-d', 'customer_accepted_at' => 'datetime', 'quoted_amount' => 'decimal:2'];

    protected static function booted(): void
    {
        static::created(function (Enquiry $e) {
            $e->enquiry_number = sprintf('EQ-%s-%06d', now()->year, $e->id);
            $e->saveQuietly();
        });
    }

    public function customer() { return $this->belongsTo(User::class, 'customer_id'); }
    public function notesLog() { return $this->hasMany(EnquiryNote::class)->latest(); }
    public function booking() { return $this->belongsTo(Booking::class, 'converted_booking_id'); }
}
