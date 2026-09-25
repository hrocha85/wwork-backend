<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class MarkGoalsRequest extends FormRequest
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
            'goals' => ['required', 'array', 'min:1'],
            'goals.*.id' => ['required', 'integer'],
            'goals.*.completed' => ['required', 'boolean'],
        ];
    }
}
