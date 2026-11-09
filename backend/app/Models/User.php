<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, SoftDeletes;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_TANKER = 'tanker';

    protected $fillable = ['name', 'mobile', 'email', 'password', 'role', 'status', 'status_reason', 'mobile_verified_at', 'last_login_at'];

    protected $hidden = ['password', 'remember_token', 'deleted_at'];

    protected function casts(): array
    {
        return [
            'mobile_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool { return $this->role === self::ROLE_ADMIN; }
    public function isCustomer(): bool { return $this->role === self::ROLE_CUSTOMER; }
    public function isTanker(): bool { return $this->role === self::ROLE_TANKER; }

    public function customerProfile() { return $this->hasOne(CustomerProfile::class); }
    public function addresses() { return $this->hasMany(CustomerAddress::class); }
    public function tankerProfile() { return $this->hasOne(TankerProfile::class); }
    /** Driver record when this user is a driver login created by an owner. */
    public function driverProfile() { return $this->hasOne(DriverProfile::class); }
    public function bookings() { return $this->hasMany(Booking::class, 'customer_id'); }
    public function enquiries() { return $this->hasMany(Enquiry::class, 'customer_id'); }
    public function appNotifications() { return $this->hasMany(AppNotification::class); }

    /** Tanker profile this user operates under: own profile (owner) or the employer profile (driver login). */
    public function operatingTankerProfile(): ?TankerProfile
    {
        return $this->tankerProfile ?: $this->driverProfile?->tankerProfile;
    }
}
