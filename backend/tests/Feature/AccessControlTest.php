<?php

namespace Tests\Feature;

use App\Models\Vehicle;

class AccessControlTest extends ApiTestCase
{
    public function test_customer_cannot_view_another_customers_booking_or_tracking(): void
    {
        $c = $this->customer();
        $id = $this->as($c)->postJson('/api/v1/bookings', $this->bookingPayload())->json('data.id');
        $other = $this->postJson('/api/v1/auth/register/customer', ['name' => 'B', 'mobile' => '9123456780', 'password' => 'Secret123', 'password_confirmation' => 'Secret123'])->json('data.token');
        $this->as($other)->getJson("/api/v1/bookings/{$id}")->assertForbidden();
        $this->as($other)->getJson("/api/v1/bookings/{$id}/tracking")->assertForbidden();
        $this->as($other)->postJson("/api/v1/my/bookings/{$id}/cancel", ['reason' => 'x'])->assertForbidden();
    }

    public function test_role_routes_are_protected(): void
    {
        $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();
        $this->as($this->customer())->getJson('/api/v1/admin/dashboard')->assertForbidden();
        $this->as($this->tanker())->getJson('/api/v1/admin/bookings')->assertForbidden();
        $this->as($this->tanker())->getJson('/api/v1/my/bookings')->assertForbidden();
        $this->as($this->customer())->getJson('/api/v1/tanker/leads')->assertForbidden();
    }

    public function test_driver_cannot_act_on_another_tankers_assignment(): void
    {
        $a = $this->admin();
        $id = $this->confirmedBooking($this->customer(), $a);
        $aid = $this->as($a)->postJson("/api/v1/admin/bookings/{$id}/assign", ['vehicle_id' => 1])->json('data.id');
        $this->as($this->tanker2())->postJson("/api/v1/tanker/assignments/{$aid}/accept")->assertForbidden();
        $this->as($this->tanker2())->getJson("/api/v1/tanker/assignments/{$aid}")->assertNotFound();
        $this->as($this->tanker2())->getJson('/api/v1/tanker/leads')->assertJsonCount(0, 'data');
    }

    public function test_pending_vehicle_cannot_be_assigned(): void
    {
        $a = $this->admin();
        Vehicle::find(1)->update(['status' => 'pending_review']);
        $id = $this->confirmedBookingWithVehicle(2);
        $res = $this->as($a)->getJson("/api/v1/admin/bookings/{$id}/eligible-tankers")->json('data');
        $this->assertNotContains(1, array_column($res['eligible'], 'vehicle_id'));
        $this->as($a)->postJson("/api/v1/admin/bookings/{$id}/assign", ['vehicle_id' => 1])->assertStatus(409);
    }

    public function test_same_vehicle_cannot_be_double_booked_on_same_date(): void
    {
        $a = $this->admin();
        $c = $this->customer();
        $b1 = $this->confirmedBooking($c, $a);
        $b2 = $this->confirmedBooking($c, $a);
        $this->as($a)->postJson("/api/v1/admin/bookings/{$b1}/assign", ['vehicle_id' => 1])->assertOk();
        $this->as($a)->postJson("/api/v1/admin/bookings/{$b2}/assign", ['vehicle_id' => 1])->assertStatus(409);
    }

    public function test_illegal_status_transitions_are_blocked(): void
    {
        $a = $this->admin();
        $c = $this->customer();
        $id = $this->as($c)->postJson('/api/v1/bookings', $this->bookingPayload())->json('data.id');
        $this->as($a)->postJson("/api/v1/admin/bookings/{$id}/complete")->assertStatus(409);
        $this->as($a)->postJson("/api/v1/admin/bookings/{$id}/assign", ['vehicle_id' => 1])->assertStatus(409);
        $this->as($a)->postJson("/api/v1/admin/bookings/{$id}/quote", ['pricing_type' => 'per_litre', 'rate' => 0.5])->assertStatus(422); // no source yet
    }

    public function test_new_tanker_registration_is_pending_and_pricing_needs_approval(): void
    {
        $t = $this->postJson('/api/v1/auth/register/tanker', ['name' => 'New Op', 'mobile' => '9988776655', 'password' => 'Secret123', 'password_confirmation' => 'Secret123', 'address' => 'Kukatpally', 'base_latitude' => 17.48, 'base_longitude' => 78.39])
            ->assertCreated()->assertJsonPath('data.user.tanker.verification_status', 'pending_review')->json('data.token');
        $vid = $this->as($t)->postJson('/api/v1/tanker/vehicles', ['registration_number' => 'ts 08 xy 9999', 'capacity_litres' => 6000])->assertCreated()->assertJsonPath('data.registration_number', 'TS08XY9999')->json('data.id');
        $pid = $this->as($t)->postJson("/api/v1/tanker/vehicles/{$vid}/pricing", ['pricing_type' => 'per_trip', 'rate' => 1500, 'effective_from' => now()->toDateString()])->assertCreated()->json('data.id');
        $a = $this->admin();
        $this->as($a)->postJson("/api/v1/admin/vehicles/{$vid}/review", ['action' => 'approve'])->assertOk();
        $this->as($a)->postJson("/api/v1/admin/pricing/{$pid}/review", ['action' => 'approve'])->assertOk();
        $id = $this->confirmedBooking($this->customer(), $a);
        $row = collect($this->as($a)->getJson("/api/v1/admin/bookings/{$id}/eligible-tankers")->json('data.excluded'))->firstWhere('vehicle_id', $vid);
        $this->assertContains('Tanker profile not approved', $row['reasons']);
    }

    public function test_upload_validation_rejects_non_images(): void
    {
        $t = $this->tanker();
        $file = \Illuminate\Http\UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream');
        $this->as($t)->post('/api/v1/tanker/vehicles', ['registration_number' => 'TS07AA1111', 'capacity_litres' => 5000, 'image' => $file], ['Accept' => 'application/json'])->assertStatus(422);
    }

    private function confirmedBookingWithVehicle(int $vehicleId): int
    {
        $a = $this->admin();
        $c = $this->customer();
        $id = $this->as($c)->postJson('/api/v1/bookings', $this->bookingPayload())->json('data.id');
        $this->as($a)->postJson("/api/v1/admin/bookings/{$id}/source", ['borewell_id' => 1]);
        $this->as($a)->postJson("/api/v1/admin/bookings/{$id}/quote", ['pricing_type' => 'per_litre', 'vehicle_id' => $vehicleId])->assertOk();
        $this->as($c)->postJson("/api/v1/my/bookings/{$id}/accept-quote")->assertOk();
        return $id;
    }
}
