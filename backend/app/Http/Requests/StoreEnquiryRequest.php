<?php

namespace App\Http\Requests;

class StoreEnquiryRequest extends OrderRequest
{
    protected bool $quantityRequired = false;

    public function rules(): array
    {
        $rules = parent::rules();
        $rules['quantity_litres'][] = 'required_without:requested_capacity';
        return $rules;
    }
}
