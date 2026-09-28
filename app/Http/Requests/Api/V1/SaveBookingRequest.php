<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SaveBookingRequest extends FormRequest
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
            'services' => ['required', 'array', 'min:1'],
            'services.*.name' => ['required', 'string', 'min:2', 'max:80'],
            'services.*.duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'services.*.price_pence' => ['required', 'integer', 'min:0'],
            'hours' => ['required', 'array', 'min:1'],
            'hours.*.weekday' => ['required', 'in:mon,tue,wed,thu,fri,sat,sun'],
            'hours.*.starts' => ['required', 'date_format:H:i'],
            'hours.*.ends' => ['required', 'date_format:H:i'],
        ];
    }
}
