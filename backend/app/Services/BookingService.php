<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\BookingStatusHistory;
use App\Models\Borewell;
use App\Models\Enquiry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Owns the booking lifecycle. All status changes go through transition(),
 * which enforces the allowed-transition map and writes booking_status_history.
 */
class BookingService
{
    /** from => [to => roles allowed] */
    public const TRANSITIONS = [
        'new' => ['under_review' => ['admin'], 'quoted' => ['admin'], 'rejected' => ['admin'], 'cancelled' => ['admin', 'customer']],
        'under_review' => ['quoted' => ['admin'], 'rejected' => ['admin'], 'cancelled' => ['admin', 'customer']],
        'quoted' => ['quoted' => ['admin'], 'confirmed' => ['admin', 'customer'], 'under_review' => ['admin'], 'rejected' => ['admin'], 'cancelled' => ['admin', 'customer']],
        'confirmed' => ['assigned' => ['admin'], 'quoted' => ['admin'], 'cancelled' => ['admin', 'customer']],
        'assigned' => ['assigned' => ['admin'], 'accepted' => ['tanker'], 'confirmed' => ['tanker', 'admin'], 'cancelled' => ['admin', 'customer']],
        'accepted' => ['assigned' => ['admin'], 'in_transit' => ['tanker'], 'confirmed' => ['tanker', 'admin'], 'cancelled' => ['admin', 'customer']],
        'in_transit' => ['arrived' => ['tanker'], 'delivered' => ['tanker'], 'cancelled' => ['admin']],
        'arrived' => ['delivered' => ['tanker'], 'cancelled' => ['admin']],
        'delivered' => ['completed' => ['admin']],
        'completed' => [],
        'rejected' => [],
        'cancelled' => [],
    ];

    public function __construct(private NotificationService $notifier, private PricingService $pricing) {}

    public function canTransition(string $from, string $to, ?string $role): bool
    {
        $allowed = self::TRANSITIONS[$from][$to] ?? null;
        return $allowed !== null && ($role === null || in_array($role, $allowed, true));
    }

    public function transition(Booking $booking, string $to, ?User $actor, ?string $notes = null, array $attributes = []): Booking
    {
        $from = $booking->status;
        if (! $this->canTransition($from, $to, $actor?->role)) {
            throw new BusinessRuleException("Booking cannot move from '" . $this->label($from) . "' to '" . $this->label($to) . "'.");
        }
        $booking->fill($attributes);
        $booking->status = $to;
        $booking->save();
        BookingStatusHistory::create([
            'booking_id' => $booking->id, 'old_status' => $from, 'new_status' => $to,
            'changed_by' => $actor?->id, 'notes' => $notes, 'created_at' => now(),
        ]);
        return $booking;
    }

    public function label(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status));
    }

    public function create(User $customer, array $data, ?Enquiry $enquiry = null, ?User $actor = null): Booking
    {
        return DB::transaction(function () use ($customer, $data, $enquiry, $actor) {
            $booking = Booking::create(array_merge($data, [
                'customer_id' => $customer->id, 'enquiry_id' => $enquiry?->id, 'status' => 'new',
                'currency' => SettingsService::get('currency') ?: 'INR',
            ]));
            BookingStatusHistory::create([
                'booking_id' => $booking->id, 'old_status' => null, 'new_status' => 'new',
                'changed_by' => ($actor ?? $customer)->id, 'notes' => $enquiry ? 'Converted from enquiry ' . $enquiry->enquiry_number : 'Booking request submitted',
                'created_at' => now(),
            ]);
            $booking->refresh();
            $this->notifier->notifyAdmins('New booking request', "Booking {$booking->booking_number} — {$booking->quantity_litres} L {$booking->water_type} for {$booking->preferred_date->format('d M Y')}.", 'booking.new', ['booking_id' => $booking->id]);
            return $booking;
        });
    }

    public function markUnderReview(Booking $booking, User $admin, ?string $notes = null): Booking
    {
        return $this->transition($booking, 'under_review', $admin, $notes ?? 'Admin reviewing booking');
    }

    public function selectSource(Booking $booking, int $borewellId, User $admin): Booking
    {
        if (in_array($booking->status, array_merge(Booking::FINAL, ['in_transit', 'arrived', 'delivered']), true)) {
            throw new BusinessRuleException('Source cannot be changed at this stage.');
        }
        $source = Borewell::findOrFail($borewellId);
        if ($source->status !== 'active') throw new BusinessRuleException("{$source->name} is {$source->status}; select an active source.", 422);
        if (! $source->supports($booking->water_type)) throw new BusinessRuleException("{$source->name} does not supply '{$booking->water_type}'.", 422);
        if ($source->track_capacity && $source->daily_capacity_litres) {
            $remaining = $source->daily_capacity_litres - $source->committedLitres($booking->preferred_date->toDateString(), $booking->id);
            if ($remaining < $booking->quantity_litres) {
                throw new BusinessRuleException("{$source->name} has only {$remaining} L remaining for that date.", 422);
            }
        }
        return DB::transaction(function () use ($booking, $source, $admin) {
            $old = $booking->borewell_id;
            if ($booking->status === 'new') $this->transition($booking, 'under_review', $admin, 'Admin reviewing booking');
            $booking->update(['borewell_id' => $source->id]);
            AuditLogger::log('booking.source_selected', $booking, ['borewell_id' => $old], ['borewell_id' => $source->id]);
            return $booking;
        });
    }

    public function quote(Booking $booking, array $data, User $admin): Booking
    {
        if (! in_array($booking->status, ['new', 'under_review', 'quoted', 'confirmed'], true)) {
            throw new BusinessRuleException('Quotation can only be prepared before a tanker is assigned.');
        }
        if (! $booking->borewell_id) throw new BusinessRuleException('Select a water source before preparing the quotation.', 422);

        $calc = $this->pricing->quoteForBooking($booking, $data);

        return DB::transaction(function () use ($booking, $calc, $data, $admin) {
            if ($booking->status === 'new') $this->transition($booking, 'under_review', $admin, 'Admin reviewing booking');
            $booking->items()->delete();
            $booking->items()->create($calc);
            $notes = 'Quotation ₹' . number_format($calc['total_amount'], 2) . ' (' . str_replace('_', '-', $calc['pricing_type']) . ')';
            $this->transition($booking, 'quoted', $admin, $notes, [
                'pricing_type' => $calc['pricing_type'],
                'quantity_litres' => $calc['quantity_litres'],
                'subtotal' => $calc['subtotal'], 'delivery_charge' => $calc['delivery_charge'],
                'tax' => $calc['tax'], 'discount' => $calc['discount'], 'total_amount' => $calc['total_amount'],
                'quotation_notes' => $data['quotation_notes'] ?? null,
                'quoted_at' => now(), 'customer_accepted_at' => null,
            ]);
            $this->notifier->notify($booking->customer_id, 'Quotation ready', "Your booking {$booking->booking_number} is quoted at ₹" . number_format($calc['total_amount'], 2) . '. Please review and confirm.', 'booking.quoted', ['booking_id' => $booking->id]);
            return $booking->load('items');
        });
    }

    public function customerAcceptQuote(Booking $booking, User $customer): Booking
    {
        if ($booking->status !== 'quoted') throw new BusinessRuleException('There is no pending quotation to accept.');
        return DB::transaction(function () use ($booking, $customer) {
            $this->transition($booking, 'confirmed', $customer, 'Customer accepted quotation', ['customer_accepted_at' => now(), 'confirmed_at' => now()]);
            $this->notifier->notifyAdmins('Quotation accepted', "Customer accepted quotation for {$booking->booking_number}. Ready for tanker assignment.", 'booking.confirmed', ['booking_id' => $booking->id]);
            return $booking;
        });
    }

    public function confirm(Booking $booking, User $admin, ?string $notes = null, bool $acceptedOffline = false): Booking
    {
        if ($booking->status !== 'quoted') throw new BusinessRuleException('Only a quoted booking can be confirmed.');
        if (! $booking->borewell_id) throw new BusinessRuleException('Select a water source first.', 422);
        if (SettingsService::get('require_customer_quote_acceptance') && ! $booking->customer_accepted_at && ! $acceptedOffline) {
            throw new BusinessRuleException('Customer has not accepted the quotation yet. Tick "customer accepted by phone/offline" to confirm on their behalf.', 422);
        }
        return DB::transaction(function () use ($booking, $admin, $notes, $acceptedOffline) {
            $attrs = ['confirmed_at' => now()];
            if ($acceptedOffline && ! $booking->customer_accepted_at) $attrs['customer_accepted_at'] = now();
            $this->transition($booking, 'confirmed', $admin, $notes ?: ($acceptedOffline ? 'Confirmed by admin (customer accepted offline)' : 'Confirmed by admin'), $attrs);
            $this->notifier->notify($booking->customer_id, 'Booking confirmed', "Booking {$booking->booking_number} is confirmed for ₹" . number_format($booking->total_amount, 2) . '. A tanker will be assigned shortly.', 'booking.confirmed', ['booking_id' => $booking->id]);
            return $booking;
        });
    }

    public function reject(Booking $booking, User $admin, string $reason): Booking
    {
        return DB::transaction(function () use ($booking, $admin, $reason) {
            $this->transition($booking, 'rejected', $admin, $reason);
            $this->notifier->notify($booking->customer_id, 'Booking could not be accepted', "Booking {$booking->booking_number}: {$reason}", 'booking.rejected', ['booking_id' => $booking->id]);
            return $booking;
        });
    }

    /** Fee that would apply if the booking were cancelled now (shown to the customer beforehand). */
    public function cancellationFee(Booking $booking): float
    {
        return in_array($booking->status, ['confirmed', 'assigned', 'accepted', 'in_transit', 'arrived'], true)
            ? (float) SettingsService::get('cancellation_fee_after_confirmation') : 0.0;
    }

    public function customerCanCancel(Booking $booking): bool
    {
        return in_array($booking->status, (array) SettingsService::get('customer_cancellable_statuses'), true)
            && $this->canTransition($booking->status, 'cancelled', 'customer');
    }

    public function cancel(Booking $booking, User $actor, string $reason, ?float $feeOverride = null): Booking
    {
        if ($actor->isCustomer() && ! $this->customerCanCancel($booking)) {
            throw new BusinessRuleException('This booking can no longer be cancelled online. Please contact support.');
        }
        $fee = ($actor->isAdmin() && $feeOverride !== null) ? $feeOverride : $this->cancellationFee($booking);

        return DB::transaction(function () use ($booking, $actor, $reason, $fee) {
            $booking = Booking::lockForUpdate()->find($booking->id);
            $active = $booking->activeAssignment;
            $this->transition($booking, 'cancelled', $actor, $reason . ($fee > 0 ? ' (cancellation fee ₹' . number_format($fee, 2) . ')' : ''), [
                'cancelled_at' => now(), 'cancel_reason' => $reason, 'cancelled_by' => $actor->id, 'cancellation_fee' => $fee,
            ]);
            if ($active) {
                $active->update(['assignment_status' => 'cancelled']);
                $active->vehicle?->update(['availability' => 'available']);
                $this->notifier->notify([$active->driver_user_id, $active->vehicle?->tankerProfile?->user_id], 'Delivery cancelled', "Booking {$booking->booking_number} was cancelled. Reason: {$reason}", 'assignment.cancelled', ['assignment_id' => $active->id]);
            }
            $this->recalculatePaymentStatus($booking);
            if ($actor->isCustomer()) {
                $this->notifier->notifyAdmins('Booking cancelled by customer', "{$booking->booking_number}: {$reason}", 'booking.cancelled', ['booking_id' => $booking->id]);
            } else {
                $this->notifier->notify($booking->customer_id, 'Booking cancelled', "Booking {$booking->booking_number} was cancelled. Reason: {$reason}", 'booking.cancelled', ['booking_id' => $booking->id]);
            }
            return $booking;
        });
    }

    public function complete(Booking $booking, User $admin, ?string $notes = null): Booking
    {
        return DB::transaction(function () use ($booking, $admin, $notes) {
            $assignment = $booking->assignments()->where('assignment_status', 'delivered')->first();
            $this->transition($booking, 'completed', $admin, $notes ?? 'Delivery verified and booking closed', ['completed_at' => now()]);
            $assignment?->update(['assignment_status' => 'completed', 'completed_at' => now()]);
            $this->notifier->notify($booking->customer_id, 'Booking completed', "Booking {$booking->booking_number} is completed. Balance due: ₹" . number_format($booking->balanceDue(), 2), 'booking.completed', ['booking_id' => $booking->id]);
            return $booking;
        });
    }

    public function recalculatePaymentStatus(Booking $booking): void
    {
        $paid = (float) $booking->payments()->where('status', 'received')->sum('amount');
        $due = $booking->status === 'cancelled' ? (float) $booking->cancellation_fee : (float) $booking->total_amount;
        $status = $paid <= 0 ? 'unpaid' : ($paid + 0.001 >= $due ? 'paid' : 'partial');
        if ($booking->status === 'cancelled' && $paid > $due) $status = 'refunded';
        $booking->update(['amount_paid' => $paid, 'payment_status' => $due <= 0 && $paid <= 0 ? ($booking->status === 'cancelled' ? 'paid' : 'unpaid') : $status]);
    }

    /** Convert an enquiry to a booking without re-entering customer details. */
    public function convertEnquiry(Enquiry $enquiry, User $admin, array $overrides = []): Booking
    {
        if (in_array($enquiry->status, ['converted', 'closed', 'cancelled'], true)) {
            throw new BusinessRuleException('This enquiry is already ' . $enquiry->status . '.');
        }
        return DB::transaction(function () use ($enquiry, $admin, $overrides) {
            $data = array_merge([
                'water_type' => $enquiry->water_type,
                'quantity_litres' => $enquiry->quantity_litres ?: $enquiry->requested_capacity,
                'requested_capacity' => $enquiry->requested_capacity,
                'delivery_address' => $enquiry->delivery_address, 'area' => $enquiry->area, 'landmark' => $enquiry->landmark,
                'latitude' => $enquiry->latitude, 'longitude' => $enquiry->longitude,
                'preferred_date' => $enquiry->preferred_date ?? now()->addDay()->toDateString(),
                'preferred_time_slot' => $enquiry->preferred_time_slot, 'notes' => $enquiry->notes,
            ], array_filter($overrides, fn ($v) => $v !== null));
            if (empty($data['quantity_litres'])) throw new BusinessRuleException('Quantity is required to convert the enquiry.', 422);

            $booking = $this->create($enquiry->customer, $data, $enquiry, $admin);
            $enquiry->update(['status' => 'converted', 'converted_booking_id' => $booking->id]);
            $enquiry->notesLog()->create(['user_id' => $admin->id, 'type' => 'status', 'note' => 'Converted to booking ' . $booking->booking_number]);
            $this->notifier->notify($enquiry->customer_id, 'Enquiry converted to booking', "Your enquiry {$enquiry->enquiry_number} is now booking {$booking->booking_number}.", 'enquiry.converted', ['booking_id' => $booking->id]);
            AuditLogger::log('enquiry.converted', $enquiry, null, ['booking_id' => $booking->id]);
            return $booking;
        });
    }
}
