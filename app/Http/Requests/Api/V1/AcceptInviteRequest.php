<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class AcceptInviteRequest extends FormRequest
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
            'password' => ['required', 'string', 'min:8'],
            'locale' => ['required', 'string'],
            'terms_accepted' => ['nullable', 'boolean'],
        ];
    }
}
