<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rules\Password;

class RegisterCustomerRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'mobile' => ['required', 'string', $this->mobileRule(), 'unique:users,mobile'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'address' => ['nullable', 'string', 'max:500'],
            'area' => ['nullable', 'string', 'max:120'],
            'landmark' => ['nullable', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return ['mobile.unique' => 'This mobile number is already registered. Please log in.'];
    }
}
