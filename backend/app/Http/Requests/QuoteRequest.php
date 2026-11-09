<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class QuoteRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'pricing_type' => ['required', Rule::in(['per_litre', 'per_trip'])],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'rate' => ['required_without:vehicle_id', 'nullable', 'numeric', 'gt:0'],
            'vehicle_capacity' => ['nullable', 'integer', 'min:100'],
            'minimum_quantity' => ['nullable', 'integer', 'min:0'],
            'quantity_litres' => ['nullable', 'integer', 'min:100'],
            'delivery_charge' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'trips_override' => ['nullable', 'integer', 'min:1', 'max:100'],
            'trip_override_reason' => ['nullable', 'string', 'max:255'],
            'quotation_notes' => ['nullable', 'string', 'max:1000'],
            'preview' => ['nullable', 'boolean'],
        ];
    }
}
