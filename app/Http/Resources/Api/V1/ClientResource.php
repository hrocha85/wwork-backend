<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Client;
use App\Models\Visit;
use App\Policies\ClientPolicy;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ClientResource
{
    /**
     * @return array<string, mixed>
     */
    public static function created(Client $client): array
    {
        return [
            'id' => $client->id,
            'name' => $client->name,
            'whatsapp' => $client->whatsapp,
            'address' => $client->address,
            'lat' => (float) $client->lat,
            'lng' => (float) $client->lng,
            'created_by' => $client->created_by,
            'sync_uuid' => $client->sync_uuid,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function index(string $day): array
    {
        $actor = AgencyContext::user();

        if (! app(ClientPolicy::class)->viewAny($actor)) {
            throw new ApiException(ErrorCodes::CLIENT_FORBIDDEN, 403);
        }

        $agency = AgencyContext::membership()->agency;
        $query = Client::query()->where('agency_id', $agency->id)->with('visits.assignee');

        if ($day !== 'all') {
            $weekday = self::weekday($day);
            $driver = DB::connection()->getDriverName();
            $query->whereHas('visits', function ($visits) use ($weekday, $driver): void {
                if ($driver === 'sqlite') {
                    $visits->whereRaw("cast(strftime('%w', service_date) as integer) = ?", [$weekday]);
                } else {
                    $visits->whereRaw('((DAYOFWEEK(service_date) + 6) % 7) = ?', [$weekday]);
                }
            });
        }

        $today = Carbon::now($agency->timezone)->toDateString();

        return [
            'clients' => $query->orderBy('name')->get()->map(
                fn (Client $client): array => self::summary($client, $today),
            )->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function show(Client $client): array
    {
        $actor = AgencyContext::user();

        if (! app(ClientPolicy::class)->view($actor, $client)) {
            throw new ApiException(ErrorCodes::CLIENT_FORBIDDEN, 403);
        }

        $client->load('visits.assignee');

        return [
            'id' => $client->id,
            'name' => $client->name,
            'whatsapp' => $client->whatsapp,
            'address' => $client->address,
            'lat' => (float) $client->lat,
            'lng' => (float) $client->lng,
            'visits' => $client->visits
                ->sortBy(fn (Visit $visit): string => $visit->service_date->toDateString().self::clock($visit))
                ->map(fn (Visit $visit): array => [
                    'id' => $visit->id,
                    'date' => $visit->service_date->toDateString(),
                    'time' => self::clock($visit),
                    'status' => $visit->status->value,
                    'assignee_name' => $visit->assignee->name,
                    'price_pence' => $visit->price_pence,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function summary(Client $client, string $today): array
    {
        $next = $client->visits
            ->filter(fn (Visit $visit): bool => $visit->service_date->toDateString() >= $today)
            ->sortBy(fn (Visit $visit): string => $visit->service_date->toDateString().self::clock($visit))
            ->first();

        return [
            'id' => $client->id,
            'name' => $client->name,
            'address' => $client->address,
            'lat' => (float) $client->lat,
            'lng' => (float) $client->lng,
            'next_visit' => $next === null ? null : [
                'id' => $next->id,
                'date' => $next->service_date->toDateString(),
                'time' => self::clock($next),
                'status' => $next->status->value,
                'assignee_name' => $next->assignee->name,
            ],
        ];
    }

    private static function clock(Visit $visit): string
    {
        return substr((string) $visit->service_time, 0, 5);
    }

    private static function weekday(string $day): int
    {
        return match ($day) {
            'sun' => 0,
            'mon' => 1,
            'tue' => 2,
            'wed' => 3,
            'thu' => 4,
            'fri' => 5,
            'sat' => 6,
            default => 0,
        };
    }
}
