<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['booking_id', 'amount', 'method', 'transaction_reference', 'status', 'paid_at', 'received_by', 'notes', 'void_reason'];
    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'datetime'];

    public function booking() { return $this->belongsTo(Booking::class)->withTrashed(); }
    public function receiver() { return $this->belongsTo(User::class, 'received_by'); }
}
