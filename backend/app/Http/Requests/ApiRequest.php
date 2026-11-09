<?php

namespace App\Http\Requests;

use App\Support\Mobile;
use Illuminate\Foundation\Http\FormRequest;

abstract class ApiRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** Normalise any mobile-number fields before validation. */
    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (['mobile', 'alternate_mobile', 'alternate_contact', 'contact_mobile', 'driver_mobile'] as $f) {
            if ($this->filled($f)) $merge[$f] = Mobile::normalize($this->input($f));
        }
        if ($merge) $this->merge($merge);
    }

    protected function mobileRule(): \Closure
    {
        return function ($attr, $value, $fail) {
            if ($value && ! Mobile::isValid($value)) $fail('Enter a valid mobile number.');
        };
    }
}
