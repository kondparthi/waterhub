<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\AuditLogger;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function __construct(private BookingService $bookings) {}

    public function index(Request $request)
    {
        $q = Payment::with('booking:id,booking_number,customer_id,total_amount', 'booking.customer:id,name,mobile', 'receiver:id,name')->latest('paid_at');
        if ($request->filled('method')) $q->where('method', $request->method);
        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->filled('from')) $q->whereDate('paid_at', '>=', $request->from);
        if ($request->filled('to')) $q->whereDate('paid_at', '<=', $request->to);
        $sum = (clone $q)->where('status', 'received')->sum('amount');
        return $this->ok($q->paginate($this->perPage()), 'OK', 200, ['total_received' => (float) $sum]);
    }

    /** Manual cash/UPI/bank-transfer recording. Partial and multiple payments are allowed. */
    public function store(PaymentRequest $request)
    {
        $payment = DB::transaction(function () use ($request) {
            $booking = Booking::lockForUpdate()->findOrFail($request->booking_id);
            if (in_array($booking->status, ['rejected'], true)) throw new BusinessRuleException('Cannot record payment on a rejected booking.');
            $payment = $booking->payments()->create($request->validated() + ['status' => 'received', 'paid_at' => $request->paid_at ?? now(), 'received_by' => $request->user()->id]);
            $this->bookings->recalculatePaymentStatus($booking);
            AuditLogger::log('payment.recorded', $payment, null, $payment->toArray());
            return $payment;
        });
        return $this->created($payment->load('booking:id,booking_number,payment_status,amount_paid,total_amount'), 'Payment recorded');
    }

    public function void(Request $request, Payment $payment)
    {
        $data = $request->validate(['reason' => 'required|string|max:255']);
        if ($payment->status === 'void') throw new BusinessRuleException('Payment already void.');
        DB::transaction(function () use ($payment, $data) {
            $payment->update(['status' => 'void', 'void_reason' => $data['reason']]);
            $this->bookings->recalculatePaymentStatus($payment->booking);
        });
        AuditLogger::log('payment.voided', $payment, ['status' => 'received'], ['status' => 'void', 'reason' => $data['reason']]);
        return $this->ok($payment->fresh(), 'Payment voided');
    }
}
