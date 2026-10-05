<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ContactChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpenQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('phone') && $this->filled('whatsapp')) {
            $this->merge(['phone' => $this->input('whatsapp')]);
        }

        if (! $this->filled('contact_channel')) {
            $this->merge(['contact_channel' => ContactChannel::Whatsapp->value]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'phone' => ['required', 'string', 'max:32'],
            'contact_channel' => ['required', Rule::enum(ContactChannel::class)],
            'address' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:2', 'max:2000'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ];
    }
}
