<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class BorewellRequest extends ApiRequest
{
    public function rules(): array
    {
        $id = $this->route('borewell')?->id;
        $req = $id ? 'sometimes' : 'required';
        return [
            'name' => [$req, 'string', 'max:100'],
            'source_code' => [$req, 'string', 'max:30', 'alpha_dash', Rule::unique('borewells', 'source_code')->ignore($id)],
            'source_type' => [$req, Rule::in(['borewell', 'bavi'])],
            'address' => ['nullable', 'string', 'max:500'],
            'area' => ['nullable', 'string', 'max:120'],
            'latitude' => [$req, 'numeric', 'between:-90,90'],
            'longitude' => [$req, 'numeric', 'between:-180,180'],
            'water_types' => ['nullable', 'array'],
            'water_types.*' => ['string', 'max:80'],
            'internal_cost_per_litre' => ['nullable', 'numeric', 'min:0'],
            'daily_capacity_litres' => ['nullable', 'integer', 'min:0'],
            'track_capacity' => ['nullable', 'boolean'],
            'opening_time' => ['nullable', 'date_format:H:i'],
            'closing_time' => ['nullable', 'date_format:H:i'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'contact_mobile' => ['nullable', 'string', $this->mobileRule()],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'maintenance'])],
        ];
    }
}
