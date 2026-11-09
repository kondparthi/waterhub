<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class PricingRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'pricing_type' => ['required', Rule::in(['per_litre', 'per_trip'])],
            'rate' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'minimum_quantity' => ['nullable', 'integer', 'min:0'],
            'delivery_charge' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'partial_load_rule' => ['nullable', Rule::in(['full_trip', 'pro_rata'])],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }
}
