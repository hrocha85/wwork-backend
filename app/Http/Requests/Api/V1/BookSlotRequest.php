<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ContactChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookSlotRequest extends FormRequest
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
            'service_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'estimated_end_time' => ['nullable', 'date_format:H:i', 'after=time'],
            'is_recurring' => ['sometimes', 'boolean'],
            'recurring_days' => ['nullable', 'array'],
            'recurring_days.*' => ['string', 'in:mon,tue,wed,thu,fri,sat,sun'],
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'phone' => ['required', 'string', 'max:32'],
            'contact_channel' => ['required', Rule::enum(ContactChannel::class)],
        ];
    }
}
