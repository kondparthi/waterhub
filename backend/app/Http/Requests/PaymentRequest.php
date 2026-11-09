<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class PaymentRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'method' => ['required', Rule::in(['cash', 'upi', 'bank_transfer', 'other'])],
            'transaction_reference' => [Rule::requiredIf(fn () => in_array($this->input('method'), ['upi', 'bank_transfer'], true)), 'nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ['transaction_reference.required' => 'Transaction/UTR reference is required for UPI and bank transfers.'];
    }
}
