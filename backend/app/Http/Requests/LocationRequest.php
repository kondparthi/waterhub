<?php

namespace App\Http\Requests;

class LocationRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'assignment_id' => ['required', 'integer'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'speed' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'heading' => ['nullable', 'numeric', 'min:0', 'max:360'],
            'recorded_at' => ['nullable', 'date'],
        ];
    }
}
