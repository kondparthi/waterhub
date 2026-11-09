<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rules\Password;

class RegisterTankerRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'business_name' => ['nullable', 'string', 'max:150'],
            'mobile' => ['required', 'string', $this->mobileRule(), 'unique:users,mobile'],
            'alternate_mobile' => ['nullable', 'string', $this->mobileRule()],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'address' => ['required', 'string', 'max:500'],
            'base_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'base_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_driver' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['mobile.unique' => 'This mobile number is already registered. Please log in.'];
    }
}
