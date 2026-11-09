<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingStatusHistory extends Model
{
    public $timestamps = false;
    protected $table = 'booking_status_history';
    protected $fillable = ['booking_id', 'old_status', 'new_status', 'changed_by', 'notes', 'created_at'];
    protected $casts = ['created_at' => 'datetime'];

    public function actor() { return $this->belongsTo(User::class, 'changed_by')->withTrashed(); }
}
