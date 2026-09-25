<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
            'agency_name' => ['required', 'string', 'min:2', 'max:80'],
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
            'locale' => ['required', 'string'],
            'trade' => ['required', 'string'],
            'utm_source' => ['nullable', 'string', 'max:80'],
            'utm_campaign' => ['nullable', 'string', 'max:80'],
            'payment_method' => ['nullable', 'string'],
            'terms_accepted' => ['nullable', 'boolean'],
        ];
    }
}
