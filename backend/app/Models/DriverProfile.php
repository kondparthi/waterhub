<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DriverProfile extends Model
{
    use SoftDeletes;

    protected $fillable = ['tanker_profile_id', 'user_id', 'name', 'mobile', 'licence_number', 'licence_document', 'is_owner', 'status'];
    protected $casts = ['is_owner' => 'boolean'];
    protected $hidden = ['licence_document'];
    protected $appends = ['has_licence_document'];

    public function tankerProfile() { return $this->belongsTo(TankerProfile::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function vehicles() { return $this->hasMany(Vehicle::class); }

    public function getHasLicenceDocumentAttribute(): bool { return (bool) $this->licence_document; }
}
