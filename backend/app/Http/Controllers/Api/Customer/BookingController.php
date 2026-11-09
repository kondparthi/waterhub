<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderRequest;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(private BookingService $bookings) {}

    public function index(Request $request)
    {
        $q = $request->user()->bookings()->latest();
        if ($request->get('scope') === 'active') $q->whereNotIn('status', Booking::FINAL);
        if ($request->get('scope') === 'history') $q->whereIn('status', Booking::FINAL);
        if ($request->filled('status')) $q->where('status', $request->status);
        return $this->ok($q->paginate($this->perPage()));
    }

    public function store(OrderRequest $request)
    {
        $data = $request->safe()->except('save_address', 'address_label', 'alternate_contact');
        $booking = $this->bookings->create($request->user(), $data);
        if ($request->boolean('save_address')) {
            $user = $request->user();
            $user->addresses()->firstOrCreate(
                ['address' => $request->delivery_address],
                ['label' => $request->address_label ?: 'Saved', 'area' => $request->area, 'landmark' => $request->landmark, 'latitude' => $request->latitude, 'longitude' => $request->longitude, 'is_default' => ! $user->addresses()->exists()]
            );
        }
        return $this->created($booking, 'Booking request submitted. Reference ' . $booking->booking_number);
    }

    public function acceptQuote(Request $request, Booking $booking)
    {
        $this->authorize('view', $booking);
        return $this->ok($this->bookings->customerAcceptQuote($booking, $request->user()), 'Quotation accepted. Your booking is confirmed.');
    }

    public function cancel(Request $request, Booking $booking)
    {
        $this->authorize('view', $booking);
        $data = $request->validate(['reason' => 'required|string|max:255']);
        return $this->ok($this->bookings->cancel($booking, $request->user(), $data['reason']), 'Booking cancelled');
    }

    public function dashboard(Request $request)
    {
        $user = $request->user();
        $active = $user->bookings()->whereNotIn('status', Booking::FINAL)->latest()->first();
        return $this->ok([
            'active_booking' => $active,
            'active_count' => $user->bookings()->whereNotIn('status', Booking::FINAL)->count(),
            'completed_count' => $user->bookings()->where('status', 'completed')->count(),
            'open_enquiries' => $user->enquiries()->whereIn('status', ['new', 'contacted', 'quotation_sent'])->count(),
            'recent_bookings' => $user->bookings()->latest()->limit(5)->get(),
        ]);
    }
}
