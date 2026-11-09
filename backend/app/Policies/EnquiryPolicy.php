<?php

namespace App\Policies;

use App\Models\Enquiry;
use App\Models\User;

class EnquiryPolicy
{
    public function view(User $user, Enquiry $enquiry): bool
    {
        return $user->isAdmin() || ($user->isCustomer() && $enquiry->customer_id === $user->id);
    }
}
