<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\Vehicle;

/**
 * Price calculation for per-litre and per-trip tanker pricing.
 *  per_litre: billable litres (≥ minimum quantity) × rate + delivery charge
 *  per_trip : trips × rate + delivery charge, trips = ceil(quantity ÷ capacity)
 * Delivery charge is applied once per booking. Tax % (settings) applies after discount.
 */
class PricingService
{
    public function calculate(array $in): array
    {
        $type = $in['pricing_type'];
        $rate = (float) $in['rate'];
        $qty = (int) $in['quantity_litres'];
        $capacity = isset($in['vehicle_capacity']) && $in['vehicle_capacity'] ? (int) $in['vehicle_capacity'] : null;
        $minQty = (int) ($in['minimum_quantity'] ?? 0);
        $delivery = round((float) ($in['delivery_charge'] ?? 0), 2);
        $discount = round((float) ($in['discount'] ?? 0), 2);
        $taxPercent = (float) ($in['tax_percent'] ?? SettingsService::get('tax_percent'));
        $partialRule = $in['partial_load_rule'] ?? 'full_trip';

        if ($rate <= 0) throw new BusinessRuleException('Rate must be greater than zero.', 422);
        if ($qty <= 0) throw new BusinessRuleException('Quantity must be greater than zero.', 422);
        if ($capacity !== null && $capacity <= 0) throw new BusinessRuleException('Vehicle capacity must be positive.', 422);
        if ($type === 'per_trip' && ! $capacity) throw new BusinessRuleException('Vehicle capacity is required for per-trip pricing.', 422);

        $calculatedTrips = $capacity ? (int) ceil($qty / $capacity) : 1;
        $trips = $calculatedTrips;
        $overrideReason = null;
        if (! empty($in['trips_override']) && (int) $in['trips_override'] !== $calculatedTrips) {
            if (empty($in['trip_override_reason'])) {
                throw new BusinessRuleException('A reason is required to override the calculated trip count.', 422, ['trip_override_reason' => ['Reason required for trip override.']]);
            }
            $trips = max(1, (int) $in['trips_override']);
            $overrideReason = $in['trip_override_reason'];
        }

        $billable = $qty;
        if ($type === 'per_litre') {
            $billable = max($qty, $minQty);
            $subtotal = round($billable * $rate, 2);
        } else {
            $subtotal = ($partialRule === 'pro_rata' && empty($in['trips_override']))
                ? round($rate * $qty / $capacity, 2)
                : round($trips * $rate, 2);
        }

        if ($discount < 0 || $discount > $subtotal + $delivery) {
            throw new BusinessRuleException('Discount must be between 0 and the gross amount.', 422);
        }
        $taxable = $subtotal + $delivery - $discount;
        $tax = round($taxable * $taxPercent / 100, 2);
        $total = round($taxable + $tax, 2);

        return [
            'pricing_type' => $type,
            'rate_snapshot' => $rate,
            'quantity_litres' => $qty,
            'billable_litres' => $billable,
            'vehicle_capacity' => $capacity,
            'calculated_trips' => $calculatedTrips,
            'trips' => $trips,
            'trip_override_reason' => $overrideReason,
            'subtotal' => $subtotal,
            'delivery_charge' => $delivery,
            'discount' => $discount,
            'tax_percent' => $taxPercent,
            'tax' => $tax,
            'total_amount' => $total,
            'line_total' => round($subtotal + $delivery, 2),
        ];
    }

    /**
     * Build a quotation for a booking either from a vehicle's approved effective rate,
     * or from a manually entered rate (admin quote without a specific vehicle).
     */
    public function quoteForBooking(Booking $booking, array $data): array
    {
        $vehicle = null;
        $pricing = null;
        $input = [
            'pricing_type' => $data['pricing_type'],
            'quantity_litres' => $data['quantity_litres'] ?? $booking->quantity_litres,
            'discount' => $data['discount'] ?? 0,
            'trips_override' => $data['trips_override'] ?? null,
            'trip_override_reason' => $data['trip_override_reason'] ?? null,
        ];

        if (! empty($data['vehicle_id'])) {
            $vehicle = Vehicle::findOrFail($data['vehicle_id']);
            if (! $vehicle->isAssignable()) throw new BusinessRuleException('Only approved vehicles can be used for quotation.', 422);
            $pricing = $vehicle->effectivePricing($data['pricing_type'], $booking->preferred_date);
            if (! $pricing) {
                throw new BusinessRuleException('Vehicle ' . $vehicle->registration_number . ' has no approved ' . str_replace('_', '-', $data['pricing_type']) . ' rate effective on ' . $booking->preferred_date->toDateString() . '.', 422);
            }
            $input += [
                'rate' => $pricing->rate,
                'vehicle_capacity' => $vehicle->capacity_litres,
                'minimum_quantity' => $pricing->minimum_quantity,
                'delivery_charge' => array_key_exists('delivery_charge', $data) && $data['delivery_charge'] !== null ? $data['delivery_charge'] : $pricing->delivery_charge,
                'partial_load_rule' => $pricing->partial_load_rule,
            ];
        } else {
            $input += [
                'rate' => $data['rate'] ?? 0,
                'vehicle_capacity' => $data['vehicle_capacity'] ?? null,
                'minimum_quantity' => $data['minimum_quantity'] ?? null,
                'delivery_charge' => $data['delivery_charge'] ?? 0,
            ];
        }

        $calc = $this->calculate($input);
        $calc['vehicle_id'] = $vehicle?->id;
        $calc['tanker_pricing_id'] = $pricing?->id;
        return $calc;
    }
}
