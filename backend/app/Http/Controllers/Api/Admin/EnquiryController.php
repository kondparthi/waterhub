<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Services\AuditLogger;
use App\Services\BookingService;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class EnquiryController extends Controller
{
    public function __construct(private BookingService $bookings, private NotificationService $notifier) {}

    public function index(Request $request)
    {
        $q = Enquiry::with('customer:id,name,mobile')->latest();
        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->filled('area')) $q->where('area', 'like', '%' . $request->area . '%');
        if ($request->filled('from')) $q->whereDate('created_at', '>=', $request->from);
        if ($request->filled('to')) $q->whereDate('created_at', '<=', $request->to);
        if ($request->filled('min_qty')) $q->where('quantity_litres', '>=', (int) $request->min_qty);
        if ($s = $request->get('search')) $q->where(fn ($w) => $w->where('enquiry_number', 'like', "%{$s}%")->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%")->orWhere('mobile', 'like', "%{$s}%")));
        return $this->ok($q->paginate($this->perPage()));
    }

    public function show(Enquiry $enquiry)
    {
        return $this->ok($enquiry->load('customer:id,name,mobile,email', 'notesLog.user:id,name', 'booking:id,booking_number,status'));
    }

    /** Record a call/contact note. First contact moves the enquiry to "contacted". */
    public function note(Request $request, Enquiry $enquiry)
    {
        $data = $request->validate(['note' => 'required|string|max:2000', 'type' => 'nullable|in:note,call']);
        $enquiry->notesLog()->create(['user_id' => $request->user()->id, 'type' => $data['type'] ?? 'note', 'note' => $data['note']]);
        if ($enquiry->status === 'new') $enquiry->update(['status' => 'contacted']);
        return $this->ok($enquiry->fresh()->load('notesLog.user:id,name'), 'Note added');
    }

    public function quote(Request $request, Enquiry $enquiry)
    {
        $data = $request->validate(['quoted_amount' => 'required|numeric|gt:0', 'quotation_details' => 'required|string|max:2000']);
        if (in_array($enquiry->status, ['converted', 'closed', 'cancelled'], true)) throw new BusinessRuleException('Enquiry is ' . $enquiry->status . '.');
        $enquiry->update($data + ['status' => 'quotation_sent', 'customer_accepted_at' => null]);
        $enquiry->notesLog()->create(['user_id' => $request->user()->id, 'type' => 'quotation', 'note' => 'Quotation ₹' . number_format($data['quoted_amount'], 2) . ': ' . $data['quotation_details']]);
        $this->notifier->notify($enquiry->customer_id, 'Quotation for your enquiry', "{$enquiry->enquiry_number}: ₹" . number_format($data['quoted_amount'], 2), 'enquiry.quoted', ['enquiry_id' => $enquiry->id]);
        return $this->ok($enquiry->fresh()->load('notesLog.user:id,name'), 'Quotation sent');
    }

    public function convert(Request $request, Enquiry $enquiry)
    {
        $overrides = $request->validate([
            'quantity_litres' => 'nullable|integer|min:100', 'preferred_date' => 'nullable|date|after_or_equal:today',
            'preferred_time_slot' => 'nullable|string|max:40',
        ]);
        $booking = $this->bookings->convertEnquiry($enquiry, $request->user(), $overrides);
        return $this->created($booking, 'Converted to booking ' . $booking->booking_number);
    }

    public function close(Request $request, Enquiry $enquiry)
    {
        $data = $request->validate(['status' => 'required|in:closed,cancelled', 'reason' => 'required|string|max:255']);
        if (in_array($enquiry->status, ['converted', 'closed', 'cancelled'], true)) throw new BusinessRuleException('Enquiry is already ' . $enquiry->status . '.');
        $before = $enquiry->only('status');
        $enquiry->update(['status' => $data['status'], 'close_reason' => $data['reason']]);
        $enquiry->notesLog()->create(['user_id' => $request->user()->id, 'type' => 'status', 'note' => ucfirst($data['status']) . ': ' . $data['reason']]);
        AuditLogger::log('enquiry.' . $data['status'], $enquiry, $before, $enquiry->only('status', 'close_reason'));
        return $this->ok($enquiry, 'Enquiry ' . $data['status']);
    }
}
