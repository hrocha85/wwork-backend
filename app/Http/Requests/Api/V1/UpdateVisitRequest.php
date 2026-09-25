<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVisitRequest extends FormRequest
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
            'assignee_id' => ['sometimes', 'nullable', 'integer'],
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'time' => ['sometimes', 'date_format:H:i'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'price_pence' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
