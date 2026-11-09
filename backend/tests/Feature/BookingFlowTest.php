<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingStatusHistory;

class BookingFlowTest extends ApiTestCase
{
    public function test_customer_registration_and_login_with_mobile(): void
    {
        $this->postJson('/api/v1/auth/register/customer', ['name' => 'Asha', 'mobile' => '98765 43210', 'password' => 'Secret123', 'password_confirmation' => 'Secret123'])
            ->assertCreated()->assertJsonPath('data.user.mobile', '+919876543210');
        $this->postJson('/api/v1/auth/register/customer', ['name' => 'Dup', 'mobile' => '+91 9876543210', 'password' => 'Secret123', 'password_confirmation' => 'Secret123'])
            ->assertStatus(422);
        $this->postJson('/api/v1/auth/login', ['login' => '9876543210', 'password' => 'Secret123'])->assertOk()->assertJsonPath('data.user.role', 'customer');
        $this->postJson('/api/v1/auth/login', ['login' => '9876543210', 'password' => 'wrong'])->assertStatus(422);
    }

    public function test_full_booking_lifecycle_with_tracking_and_payment(): void
    {
        $c = $this->customer();
        $a = $this->admin();
        $t = $this->tanker();

        // Customer requests booking
        $res = $this->as($c)->postJson('/api/v1/bookings', $this->bookingPayload())->assertCreated();
        $id = $res->json('data.id');
        $this->assertMatchesRegularExpression('/^WT-\d{4}-\d{6}$/', $res->json('data.booking_number'));

        // Admin selects source & quotes per litre from vehicle TS09AB1234 (₹0.50 + ₹200)
        $this->as($a)->getJson("/api/v1/admin/bookings/{$id}/sources")->assertOk();
        $this->as($a)->postJson("/api/v1/admin/bookings/{$id}/source", ['borewell_id' => 1])->assertOk();
        $this->as($a)->postJson("/api/v1/admin/bookings/{$id}/quote", ['pricing_type' => 'per_litre', 'vehicle_id' => 1])
            ->assertOk()->assertJsonPath('data.total_amount', '2700.00')->assertJsonPath('data.status', 'quoted');

        // Customer accepts → confirmed
        $this->as($c)->postJson("/api/v1/my/bookings/{$id}/accept-quote")->assertOk()->assertJsonPath('data.status', 'confirmed');

        // Eligible tankers, assign
        $eligible = $this->as($a)->getJson("/api/v1/admin/bookings/{$id}/eligible-tankers")->assertOk()->json('data.eligible');
        $this->assertNotEmpty($eligible);
        $this->as($a)->postJson("/api/v1/admin/bookings/{$id}/assign", ['vehicle_id' => 1])->assertOk();

        // Driver sees lead with masked customer details, accepts
        $lead = $this->as($t)->getJson('/api/v1/tanker/leads')->assertOk()->json('data.0');
        $this->assertNull($lead['booking']['delivery_address']);
        $this->assertStringContainsString('*', $lead['booking']['customer_mobile']);
        $aid = $lead['id'];
        $this->as($t)->postJson("/api/v1/tanker/assignments/{$aid}/accept")->assertOk()->assertJsonPath('data.booking.delivery_address', 'Plot 12, Road 3');

        // GPS rejected before start
        $this->as($t)->postJson('/api/v1/tanker/locations', ['assignment_id' => $aid, 'latitude' => 17.45, 'longitude' => 78.36])->assertStatus(409);

        // Start, GPS, tracking
        $this->as($t)->postJson("/api/v1/tanker/assignments/{$aid}/start")->assertOk();
        $this->as($t)->postJson('/api/v1/tanker/locations', ['assignment_id' => $aid, 'latitude' => 17.4500, 'longitude' => 78.3600, 'accuracy_m' => 12])->assertCreated();
        $track = $this->as($c)->getJson("/api/v1/bookings/{$id}/tracking")->assertOk()->json('data');
        $this->assertTrue($track['trackable']);
        $this->assertEqualsWithDelta(17.45, $track['location']['latitude'], 0.0001);
        $this->as($a)->getJson('/api/v1/admin/tracking/active')->assertOk()->assertJsonCount(1, 'data.deliveries');

        // Impossible jump ignored
        $this->as($t)->postJson('/api/v1/tanker/locations', ['assignment_id' => $aid, 'latitude' => 19.0, 'longitude' => 72.8])->assertStatus(422);

        $this->as($t)->postJson("/api/v1/tanker/assignments/{$aid}/arrive")->assertOk();
        $this->as($t)->postJson("/api/v1/tanker/assignments/{$aid}/delivered", ['delivered_litres' => 5000, 'proof_notes' => 'Tank filled'])->assertOk();

        // GPS rejected after delivery
        $this->as($t)->postJson('/api/v1/tanker/locations', ['assignment_id' => $aid, 'latitude' => 17.4501, 'longitude' => 78.3601])->assertStatus(409);

        // Admin completes and records payments (partial then full)
        $this->as($a)->postJson("/api/v1/admin/bookings/{$id}/complete")->assertOk()->assertJsonPath('data.status', 'completed');
        $this->as($a)->postJson('/api/v1/admin/payments', ['booking_id' => $id, 'amount' => 1000, 'method' => 'cash'])->assertCreated()->assertJsonPath('data.booking.payment_status', 'partial');
        $this->as($a)->postJson('/api/v1/admin/payments', ['booking_id' => $id, 'amount' => 1700, 'method' => 'upi'])->assertStatus(422); // UTR required
        $this->as($a)->postJson('/api/v1/admin/payments', ['booking_id' => $id, 'amount' => 1700, 'method' => 'upi', 'transaction_reference' => 'UTR123'])->assertCreated()->assertJsonPath('data.booking.payment_status', 'paid');

        // Full audit trail retained
        $statuses = BookingStatusHistory::where('booking_id', $id)->orderBy('id')->pluck('new_status')->all();
        $this->assertSame(['new', 'under_review', 'quoted', 'confirmed', 'assigned', 'accepted', 'in_transit', 'arrived', 'delivered', 'completed'], $statuses);

        // Reports
        $range = '&from=' . now()->toDateString() . '&to=' . now()->addWeek()->toDateString();
        $this->as($a)->getJson('/api/v1/admin/reports/summary?type=overview' . $range)->assertOk()->assertJsonPath('data.completed', 1);
        foreach (['daily', 'status', 'sources', 'tankers', 'customers', 'cancellations', 'payments'] as $type) {
            $this->as($a)->getJson('/api/v1/admin/reports/summary?type=' . $type . $range)->assertOk();
        }
        $this->as($a)->get('/api/v1/admin/reports/summary?type=sources&format=csv')->assertOk()->assertHeader('content-type', 'text/csv; charset=utf-8');
        $this->as($a)->getJson('/api/v1/admin/dashboard')->assertOk();
    }

    public function test_per_trip_quote(): void
    {
        $c = $this->customer();
        $a = $this->admin();
        $id = $this->as($c)->postJson('/api/v1/bookings', $this->bookingPayload(['quantity_litres' => 10000]))->json('data.id');
        $this->as($a)->postJson("/api/v1/admin/bookings/{$id}/source", ['borewell_id' => 1])->assertOk();
        $res = $this->as($a)->postJson("/api/v1/admin/bookings/{$id}/quote", ['pricing_type' => 'per_trip', 'vehicle_id' => 1])->assertOk();
        // 2 trips × ₹1,200 + ₹200
        $this->assertSame('2600.00', $res->json('data.total_amount'));
        $this->assertSame(2, $res->json('data.items.0.trips'));
    }

    public function test_driver_reject_and_admin_reassign_keeps_history(): void
    {
        $c = $this->customer();
        $a = $this->admin();
        $id = $this->confirmedBooking($c, $a);
        $this->as($a)->postJson("/api/v1/admin/bookings/{$id}/assign", ['vehicle_id' => 1])->assertOk();
        $aid = $this->as($this->tanker())->getJson('/api/v1/tanker/leads')->json('data.0.id');
        $this->as($this->tanker())->postJson("/api/v1/tanker/assignments/{$aid}/reject", ['reason' => 'Vehicle breakdown'])->assertOk();
        $this->assertSame('confirmed', Booking::find($id)->status);

        // Reassign to the other operator's vehicle (id 3)
        $this->as($a)->postJson("/api/v1/admin/bookings/{$id}/assign", ['vehicle_id' => 3])->assertOk();
        $this->assertSame(2, Booking::find($id)->assignments()->count());
        $this->assertSame(['assigned', 'rejected'], Booking::find($id)->assignments()->pluck('assignment_status')->all());
    }

    public function test_enquiry_to_booking_conversion(): void
    {
        $c = $this->customer();
        $a = $this->admin();
        $eid = $this->as($c)->postJson('/api/v1/enquiries', $this->bookingPayload(['quantity_litres' => null, 'requested_capacity' => 5000]))->assertCreated()->json('data.id');
        $this->as($a)->postJson("/api/v1/admin/enquiries/{$eid}/notes", ['note' => 'Called customer', 'type' => 'call'])->assertOk()->assertJsonPath('data.status', 'contacted');
        $this->as($a)->postJson("/api/v1/admin/enquiries/{$eid}/quote", ['quoted_amount' => 2700, 'quotation_details' => '5000 L @ 0.50 + 200'])->assertOk();
        $this->as($c)->postJson("/api/v1/my/enquiries/{$eid}/accept-quote")->assertOk();
        $bid = $this->as($a)->postJson("/api/v1/admin/enquiries/{$eid}/convert")->assertCreated()->json('data.id');
        $this->as($c)->getJson("/api/v1/my/enquiries/{$eid}")->assertJsonPath('data.status', 'converted')->assertJsonPath('data.converted_booking_id', $bid);
        $this->as($a)->postJson("/api/v1/admin/enquiries/{$eid}/convert")->assertStatus(409);
    }

    public function test_customer_cancellation_applies_configured_fee(): void
    {
        \App\Models\Setting::where('key', 'cancellation_fee_after_confirmation')->update(['value' => '150']);
        \App\Models\Setting::flush();
        $c = $this->customer();
        $id = $this->confirmedBooking($c, $this->admin());
        $this->as($c)->getJson("/api/v1/bookings/{$id}")->assertJsonPath('data.cancellation_fee_if_cancelled', 150);
        $this->as($c)->postJson("/api/v1/my/bookings/{$id}/cancel", ['reason' => 'Plans changed'])->assertOk()->assertJsonPath('data.cancellation_fee', '150.00');
    }
}
