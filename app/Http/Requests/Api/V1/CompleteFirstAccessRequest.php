<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CompleteFirstAccessRequest extends FormRequest
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
            'name' => ['required', 'string'],
            'trade' => ['required', 'string'],
            'trade_detail' => ['nullable', 'string'],
            'legal_address' => ['nullable', 'string'],
        ];
    }
}
