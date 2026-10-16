<?php

namespace App\Http\Requests\Api\V1;

use App\Support\AgencyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class StoreVisitRequest extends FormRequest
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
            'client_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'estimated_end_time' => ['nullable', 'date_format:H:i', 'after:time'],
            'is_recurring' => ['sometimes', 'boolean'],
            'recurring_days' => ['nullable', 'array'],
            'recurring_days.*' => ['string', 'in:mon,tue,wed,thu,fri,sat,sun'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price_pence' => ['required', 'integer', 'min:0'],
            'assignee_id' => ['nullable', 'integer'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'goals' => ['nullable', 'array'],
            'goals.*.text' => ['required', 'string', 'min:1', 'max:120'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (app()->runningUnitTests()) {
                return;
            }

            $membership = AgencyContext::membership();
            $tz = $membership?->agency?->timezone ?? 'UTC';
            $now = Carbon::now($tz);
            $today = $now->format('Y-m-d');
            $minTime = $now->copy()->addHour()->format('H:i');

            $date = $this->input('date');
            $time = $this->input('time');

            if ($date && $date < $today) {
                $validator->errors()->add('date', 'visit.past_date');
            } elseif ($date && $time && $date === $today && $time < $minTime) {
                $validator->errors()->add('time', 'visit.past_time');
            }
        });
    }
}