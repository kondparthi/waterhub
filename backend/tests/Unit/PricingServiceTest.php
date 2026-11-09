<?php

namespace Tests\Unit;

use App\Exceptions\BusinessRuleException;
use App\Services\PricingService;
use PHPUnit\Framework\TestCase;

class PricingServiceTest extends TestCase
{
    private PricingService $svc;

    protected function setUp(): void
    {
        $this->svc = new PricingService();
    }

    public function test_per_litre_example_from_spec(): void
    {
        $r = $this->svc->calculate(['pricing_type' => 'per_litre', 'rate' => 0.50, 'quantity_litres' => 5000, 'delivery_charge' => 200, 'tax_percent' => 0]);
        $this->assertSame(2700.0, $r['total_amount']);
    }

    public function test_per_trip_example_from_spec(): void
    {
        $r = $this->svc->calculate(['pricing_type' => 'per_trip', 'rate' => 1200, 'quantity_litres' => 10000, 'vehicle_capacity' => 5000, 'delivery_charge' => 200, 'tax_percent' => 0]);
        $this->assertSame(2, $r['trips']);
        $this->assertSame(2600.0, $r['total_amount']);
    }

    public function test_trips_round_up_for_partial_load(): void
    {
        $r = $this->svc->calculate(['pricing_type' => 'per_trip', 'rate' => 1000, 'quantity_litres' => 5001, 'vehicle_capacity' => 5000, 'tax_percent' => 0]);
        $this->assertSame(2, $r['trips']);
    }

    public function test_minimum_quantity_applies_to_per_litre(): void
    {
        $r = $this->svc->calculate(['pricing_type' => 'per_litre', 'rate' => 1, 'quantity_litres' => 500, 'minimum_quantity' => 1000, 'tax_percent' => 0]);
        $this->assertSame(1000, $r['billable_litres']);
        $this->assertSame(1000.0, $r['total_amount']);
    }

    public function test_tax_and_discount(): void
    {
        $r = $this->svc->calculate(['pricing_type' => 'per_litre', 'rate' => 0.5, 'quantity_litres' => 5000, 'delivery_charge' => 200, 'discount' => 200, 'tax_percent' => 5]);
        $this->assertSame(125.0, $r['tax']);
        $this->assertSame(2625.0, $r['total_amount']);
    }

    public function test_trip_override_requires_reason(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->svc->calculate(['pricing_type' => 'per_trip', 'rate' => 1000, 'quantity_litres' => 10000, 'vehicle_capacity' => 5000, 'trips_override' => 1, 'tax_percent' => 0]);
    }

    public function test_per_trip_requires_capacity(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->svc->calculate(['pricing_type' => 'per_trip', 'rate' => 1000, 'quantity_litres' => 1000, 'tax_percent' => 0]);
    }
}
