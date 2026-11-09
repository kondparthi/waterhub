<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        putenv('ADMIN_PASSWORD=Admin@12345');
        $this->seed(DemoSeeder::class);
    }

    protected function tokenFor(string $login, string $password): string
    {
        return $this->postJson('/api/v1/auth/login', ['login' => $login, 'password' => $password])->assertOk()->json('data.token');
    }

    protected function as(string $token): static
    {
        $this->app['auth']->forgetGuards();
        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    protected function admin(): string { return $this->tokenFor('admin@example.com', 'Admin@12345'); }
    protected function customer(): string { return $this->tokenFor('9000000001', 'Customer@123'); }
    protected function tanker(): string { return $this->tokenFor('9000000002', 'Tanker@123'); }
    protected function tanker2(): string { return $this->tokenFor('9000000003', 'Tanker@123'); }

    protected function bookingPayload(array $over = []): array
    {
        return array_merge([
            'water_type' => 'General Use Water', 'quantity_litres' => 5000,
            'delivery_address' => 'Plot 12, Road 3', 'area' => 'Kondapur',
            'latitude' => 17.4630, 'longitude' => 78.3570,
            'preferred_date' => now()->addDay()->toDateString(), 'preferred_time_slot' => '09:00-12:00',
        ], $over);
    }

    /** Creates a booking and drives it to "confirmed". */
    protected function confirmedBooking(string $c, string $a, array $over = []): int
    {
        $id = $this->as($c)->postJson('/api/v1/bookings', $this->bookingPayload($over))->assertCreated()->json('data.id');
        $this->as($a)->postJson("/api/v1/admin/bookings/{$id}/source", ['borewell_id' => 1])->assertOk();
        $this->as($a)->postJson("/api/v1/admin/bookings/{$id}/quote", ['pricing_type' => 'per_litre', 'vehicle_id' => 1])->assertOk();
        $this->as($c)->postJson("/api/v1/my/bookings/{$id}/accept-quote")->assertOk();
        return $id;
    }
}
