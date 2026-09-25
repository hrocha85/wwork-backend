<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\MembershipRole;
use App\Models\CheckEvent;
use App\Models\Visit;
use App\Models\VisitGoal;
use App\Models\VisitPhoto;
use App\Policies\VisitPolicy;
use App\Support\AgencyContext;
use App\Support\ApiException;
use Illuminate\Support\Carbon;

class VisitResource
{
    /**
     * @return array<string, mixed>
     */
    public static function index(?string $day, mixed $assignee): array
    {
        $actor = AgencyContext::user();
        $membership = AgencyContext::membership();
        $agency = $membership->agency;
        $timezone = $agency->timezone;
        $now = Carbon::now($timezone);
        $window = self::window($day === null || $day === '' ? 'today' : $day, $now);

        $query = Visit::query()
            ->where('agency_id', $agency->id)
            ->whereDate('service_date', '>=', $window['start'])
            ->whereDate('service_date', '<=', $window['end'])
            ->with(['client', 'assignee', 'goals', 'photos', 'events', 'invoiceLine'])
            ->orderBy('service_time')
            ->orderBy('id');

        if ($membership->role === MembershipRole::Invited) {
            $query->where('assignee_id', $actor->id);
        } elseif (is_numeric($assignee)) {
            $query->where('assignee_id', (int) $assignee);
        }

        return [
            'visits' => $query->get()->map(
                fn (Visit $visit): array => self::item($visit, $membership->role, $timezone),
            )->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function show(Visit $visit): array
    {
        $denial = app(VisitPolicy::class)->denial(AgencyContext::user(), $visit);

        if ($denial !== null) {
            throw new ApiException($denial, 403);
        }

        $membership = AgencyContext::membership();
        $visit->load(['client', 'assignee', 'goals', 'photos', 'events', 'invoiceLine']);

        return self::item($visit, $membership->role, $membership->agency->timezone);
    }

    /**
     * @return array<string, mixed>
     */
    public static function created(Visit $visit): array
    {
        return [
            'id' => $visit->id,
            'sync_uuid' => $visit->sync_uuid,
            'status' => $visit->status->value,
            'assignee' => [
                'id' => $visit->assignee->id,
                'name' => $visit->assignee->name,
            ],
            'rate' => $visit->rate,
            'partner_earning_pence' => $visit->partner_earning_pence,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function accepted(Visit $visit): array
    {
        return [
            'id' => $visit->id,
            'status' => $visit->status->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function declined(Visit $visit): array
    {
        return [
            'id' => $visit->id,
            'status' => $visit->status->value,
            'assignee_id' => $visit->assignee_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function event(CheckEvent $event, Visit $visit): array
    {
        $visit->loadMissing('agency');
        $timezone = $visit->agency->timezone;

        return [
            'id' => $event->id,
            'type' => $event->type->value,
            'lat' => (float) $event->lat,
            'lng' => (float) $event->lng,
            'at' => $event->occurred_at->timezone($timezone)->toIso8601String(),
            'visit_status' => $visit->status->value,
            'check_in_at' => $visit->check_in_at?->timezone($timezone)->toIso8601String(),
            'duration_seconds' => $event->getAttribute('duration_seconds'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function photo(VisitPhoto $photo): array
    {
        return [
            'id' => $photo->id,
            'path' => $photo->path,
            'sync_uuid' => $photo->sync_uuid,
        ];
    }

    /**
     * @param  array<int, VisitGoal>  $goals
     * @return array<string, mixed>
     */
    public static function goals(array $goals): array
    {
        return [
            'goals' => array_map(fn (VisitGoal $goal): array => [
                'id' => $goal->id,
                'text' => $goal->text,
                'completed' => $goal->completed,
            ], $goals),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function item(Visit $visit, MembershipRole $role, string $timezone): array
    {
        $owner = $role === MembershipRole::Owner;
        $client = [
            'id' => $visit->client->id,
            'name' => $visit->client->name,
            'address' => $visit->client->address,
        ];

        if ($owner) {
            $client['whatsapp'] = $visit->client->whatsapp;
        }

        $row = [
            'id' => $visit->id,
            'sync_uuid' => $visit->sync_uuid,
            'client' => $client,
            'date' => $visit->service_date->toDateString(),
            'time' => substr((string) $visit->service_time, 0, 5),
            'status' => $visit->status->value,
            'description' => $visit->description,
            'partner_earning_pence' => $visit->partner_earning_pence,
            'lat' => (float) $visit->lat,
            'lng' => (float) $visit->lng,
            'check_in_at' => $visit->check_in_at?->timezone($timezone)->toIso8601String(),
            'goals' => $visit->goals->map(fn (VisitGoal $goal): array => [
                'id' => $goal->id,
                'text' => $goal->text,
                'completed' => $goal->completed,
            ])->values()->all(),
        ];

        if ($owner) {
            $row['assignee'] = [
                'id' => $visit->assignee->id,
                'name' => $visit->assignee->name,
            ];
            $row['price_pence'] = $visit->price_pence;
            $row['rate'] = $visit->rate;
            $row['invoiced'] = $visit->invoiceLine !== null;
            $row['photos'] = $visit->photos->map(fn (VisitPhoto $photo): array => [
                'id' => $photo->id,
                'path' => $photo->path,
                'sync_uuid' => $photo->sync_uuid,
            ])->values()->all();
            $row['events'] = $visit->events->map(fn (CheckEvent $event): array => [
                'id' => $event->id,
                'type' => $event->type->value,
                'lat' => (float) $event->lat,
                'lng' => (float) $event->lng,
                'at' => $event->occurred_at->timezone($timezone)->toIso8601String(),
            ])->values()->all();
        }

        return $row;
    }

    /**
     * @return array{start: string, end: string}
     */
    private static function window(string $day, Carbon $now): array
    {
        if ($day === 'tomorrow') {
            $date = $now->copy()->addDay()->toDateString();

            return ['start' => $date, 'end' => $date];
        }

        if ($day === 'week') {
            return [
                'start' => $now->copy()->startOfWeek(Carbon::MONDAY)->toDateString(),
                'end' => $now->copy()->endOfWeek(Carbon::SUNDAY)->toDateString(),
            ];
        }

        $date = $now->toDateString();

        return ['start' => $date, 'end' => $date];
    }
}
