<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TankerProfile extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id', 'owner_name', 'business_name', 'alternate_mobile', 'address', 'base_latitude', 'base_longitude', 'verification_status', 'review_notes', 'reviewed_by', 'reviewed_at'];
    protected $casts = ['base_latitude' => 'float', 'base_longitude' => 'float', 'reviewed_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
    public function vehicles() { return $this->hasMany(Vehicle::class); }
    public function drivers() { return $this->hasMany(DriverProfile::class); }

    public function isApproved(): bool { return $this->verification_status === 'approved'; }
}
