<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\Enquiry;
use App\Models\Payment;
use App\Models\TankerPricing;
use App\Models\TankerProfile;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $tz = 'Asia/Kolkata';
        $today = Carbon::now($tz)->startOfDay()->utc();
        $week = Carbon::now($tz)->startOfWeek()->utc();
        $month = Carbon::now($tz)->startOfMonth()->utc();
        $value = fn ($from) => (float) Booking::where('created_at', '>=', $from)->whereNotIn('status', ['cancelled', 'rejected'])->sum('total_amount');
        $collected = fn ($from) => (float) Payment::where('status', 'received')->where('paid_at', '>=', $from)->sum('amount');
        $statusCounts = Booking::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');

        return $this->ok([
            'customers' => [
                'total' => User::where('role', 'customer')->count(),
                'active' => User::where('role', 'customer')->where('status', 'active')->count(),
                'new_this_week' => User::where('role', 'customer')->where('created_at', '>=', $week)->count(),
            ],
            'tankers' => [
                'pending_profiles' => TankerProfile::where('verification_status', 'pending_review')->count(),
                'pending_vehicles' => Vehicle::where('status', 'pending_review')->count(),
                'pending_pricing' => TankerPricing::where('approval_status', 'pending')->count(),
                'active_vehicles' => Vehicle::whereIn('status', Vehicle::ASSIGNABLE_STATUSES)->count(),
                'available_now' => Vehicle::whereIn('status', Vehicle::ASSIGNABLE_STATUSES)->where('availability', 'available')->count(),
            ],
            'enquiries' => [
                'new' => Enquiry::where('status', 'new')->count(),
                'awaiting_follow_up' => Enquiry::whereIn('status', ['contacted', 'quotation_sent'])->count(),
            ],
            'bookings' => $statusCounts,
            'deliveries' => [
                'in_transit' => (int) ($statusCounts['in_transit'] ?? 0) + (int) ($statusCounts['arrived'] ?? 0),
                'completed_today' => Booking::where('status', 'completed')->where('completed_at', '>=', $today)->count(),
                'cancelled_today' => Booking::where('status', 'cancelled')->where('cancelled_at', '>=', $today)->count(),
            ],
            'value' => ['today' => $value($today), 'week' => $value($week), 'month' => $value($month)],
            'collected' => ['today' => $collected($today), 'week' => $collected($week), 'month' => $collected($month)],
            'attention' => [
                'new_bookings' => Booking::where('status', 'new')->oldest()->limit(5)->get(['id', 'booking_number', 'quantity_litres', 'water_type', 'area', 'preferred_date', 'created_at']),
                'awaiting_assignment' => Booking::where('status', 'confirmed')->orderBy('preferred_date')->limit(5)->get(['id', 'booking_number', 'quantity_litres', 'area', 'preferred_date']),
                'overdue_driver_response' => BookingAssignment::where('assignment_status', 'assigned')->where('response_due_at', '<', now())->with('booking:id,booking_number', 'vehicle:id,registration_number')->limit(5)->get(),
                'delivered_to_verify' => Booking::where('status', 'delivered')->limit(5)->get(['id', 'booking_number', 'area', 'delivered_litres']),
            ],
        ]);
    }
}
