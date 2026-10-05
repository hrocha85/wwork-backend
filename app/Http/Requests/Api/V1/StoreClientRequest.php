<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ContactChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aceita `phone` (nome novo) e ainda `whatsapp` (nome antigo) como alias,
     * para não quebrar cliente que ainda manda o corpo antigo.
     */
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
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
