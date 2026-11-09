<?php

namespace App\Http\Requests;

use App\Models\WaterType;
use App\Services\SettingsService;
use Illuminate\Validation\Rule;

/** Shared fields for customer enquiries and booking requests. */
class OrderRequest extends ApiRequest
{
    protected bool $quantityRequired = true;

    public function rules(): array
    {
        return [
            'water_type' => ['required', 'string', Rule::in(WaterType::where('is_active', true)->pluck('name')->all())],
            'quantity_litres' => [$this->quantityRequired ? 'required' : 'nullable', 'integer', 'min:100', 'max:200000'],
            'requested_capacity' => ['nullable', 'integer', 'min:100', 'max:100000'],
            'delivery_address' => ['required', 'string', 'max:500'],
            'area' => ['nullable', 'string', 'max:120'],
            'landmark' => ['nullable', 'string', 'max:150'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'preferred_date' => [$this->quantityRequired ? 'required' : 'nullable', 'date', 'after_or_equal:today', 'before:' . now()->addMonths(3)->toDateString()],
            'preferred_time_slot' => ['nullable', 'string', Rule::in((array) SettingsService::get('time_slots'))],
            'notes' => ['nullable', 'string', 'max:1000'],
            'alternate_contact' => ['nullable', 'string', $this->mobileRule()],
            'save_address' => ['nullable', 'boolean'],
            'address_label' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return ['water_type.in' => 'Select a valid water type.', 'preferred_date.after_or_equal' => 'Delivery date cannot be in the past.'];
    }
}
