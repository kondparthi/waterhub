<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function view(User $user, Booking $booking): bool
    {
        return $user->isAdmin() || ($user->isCustomer() && $booking->customer_id === $user->id);
    }

    /** Only admin and the booking's own customer may see the tanker location. */
    public function track(User $user, Booking $booking): bool
    {
        return $this->view($user, $booking);
    }
}
