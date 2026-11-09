<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingAssignment extends Model
{
    /** Assignment states that block the vehicle and count as "the" active assignment of a booking. */
    public const ACTIVE = ['assigned', 'accepted', 'in_transit', 'arrived'];
    /** States in which GPS updates are accepted. */
    public const TRACKABLE = ['in_transit', 'arrived'];

    protected $fillable = ['booking_id', 'vehicle_id', 'driver_profile_id', 'driver_user_id', 'assigned_by', 'assignment_status', 'assigned_at', 'response_due_at', 'accepted_at', 'started_at', 'arrived_at', 'delivered_at', 'completed_at', 'rejection_reason', 'proof_path', 'proof_notes', 'delivered_litres', 'last_latitude', 'last_longitude', 'last_accuracy_m', 'last_location_at'];

    protected $casts = [
        'assigned_at' => 'datetime', 'response_due_at' => 'datetime', 'accepted_at' => 'datetime', 'started_at' => 'datetime',
        'arrived_at' => 'datetime', 'delivered_at' => 'datetime', 'completed_at' => 'datetime', 'last_location_at' => 'datetime',
        'last_latitude' => 'float', 'last_longitude' => 'float', 'last_accuracy_m' => 'float',
    ];

    protected $hidden = ['proof_path'];
    protected $appends = ['has_proof', 'response_overdue'];

    public function booking() { return $this->belongsTo(Booking::class)->withTrashed(); }
    public function vehicle() { return $this->belongsTo(Vehicle::class)->withTrashed(); }
    public function driver() { return $this->belongsTo(DriverProfile::class, 'driver_profile_id')->withTrashed(); }
    public function driverUser() { return $this->belongsTo(User::class, 'driver_user_id'); }
    public function assignedBy() { return $this->belongsTo(User::class, 'assigned_by'); }
    public function locations() { return $this->hasMany(TankerLocation::class, 'assignment_id'); }

    public function getHasProofAttribute(): bool { return (bool) $this->proof_path; }

    public function getResponseOverdueAttribute(): bool
    {
        return $this->assignment_status === 'assigned' && $this->response_due_at && $this->response_due_at->isPast();
    }

    /** Owner of the vehicle or the driver login attached to the assignment. */
    public function belongsToTankerUser(User $user): bool
    {
        if ($this->driver_user_id === $user->id) return true;
        $profile = $user->tankerProfile;
        return $profile && $this->vehicle && $this->vehicle->tanker_profile_id === $profile->id;
    }
}
