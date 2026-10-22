<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'currency' => ['nullable', 'string', 'size:3'],
            'legal_address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
            'payment_method' => ['nullable', 'string'],
            'payment_details' => ['nullable', 'string'],
            'payment_link' => ['nullable', 'string', 'max:500'],
            'vat_registered' => ['nullable', 'boolean'],
            'tax_id' => ['nullable', 'string'],
        ];
    }
}
