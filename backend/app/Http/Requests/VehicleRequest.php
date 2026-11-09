<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class VehicleRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        if ($this->filled('registration_number')) {
            $this->merge(['registration_number' => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $this->input('registration_number')))]);
        }
        foreach (['service_areas', 'water_types'] as $f) {
            if (is_string($this->input($f))) {
                $decoded = json_decode($this->input($f), true);
                $this->merge([$f => is_array($decoded) ? $decoded : array_values(array_filter(array_map('trim', explode(',', $this->input($f)))))]);
            }
        }
    }

    public function rules(): array
    {
        $id = $this->route('vehicle')?->id;
        return [
            'registration_number' => [$id ? 'sometimes' : 'required', 'string', 'regex:/^[A-Z]{2}[0-9]{1,2}[A-Z]{0,3}[0-9]{1,4}$/', Rule::unique('vehicles', 'registration_number')->ignore($id)],
            'vehicle_type' => ['nullable', 'string', 'max:40'],
            'capacity_litres' => [$id ? 'sometimes' : 'required', 'integer', 'min:500', 'max:60000'],
            'driver_profile_id' => ['nullable', 'integer', 'exists:driver_profiles,id'],
            'service_areas' => ['nullable', 'array', 'max:30'],
            'service_areas.*' => ['string', 'max:80'],
            'service_radius_km' => ['nullable', 'integer', 'min:1', 'max:200'],
            'base_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'base_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'water_types' => ['nullable', 'array'],
            'water_types.*' => ['string', 'max:80'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'rc_document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return ['registration_number.regex' => 'Use a valid registration number, e.g. TS09AB1234.'];
    }
}
