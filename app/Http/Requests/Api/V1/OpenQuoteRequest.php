<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class OpenQuoteRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'whatsapp' => ['required', 'string', 'max:32'],
            'address' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:2', 'max:2000'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ];
    }
}
