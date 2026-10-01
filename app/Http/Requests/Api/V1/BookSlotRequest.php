<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class BookSlotRequest extends FormRequest
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
            'service_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'estimated_end_time' => ['nullable', 'date_format:H:i', 'after=time'],
            'is_recurring' => ['sometimes', 'boolean'],
            'recurring_days' => ['nullable', 'array'],
            'recurring_days.*' => ['string', 'in:mon,tue,wed,thu,fri,sat,sun'],
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'whatsapp' => ['required', 'string', 'max:32'],
        ];
    }
}
