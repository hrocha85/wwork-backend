<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\MembershipRole;
use App\Models\AgendaBlock;
use App\Models\Client;
use App\Models\Membership;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use Illuminate\Support\Carbon;

class AgendaResource
{
    /**
     * @return array<string, mixed>
     */
    public static function show(string $token): array
    {
        $client = Client::query()->where('agenda_token', $token)->first();

        if ($client === null) {
            throw new ApiException(ErrorCodes::AGENDA_INVALID_TOKEN, 404);
        }

        $visits = $client->visits()->orderBy('service_date')->orderBy('service_time')->get();

        return [
            'client_name' => $client->name,
            'visits' => $visits->map(fn ($visit): array => [
                'date' => $visit->service_date->toDateString(),
                'time' => substr((string) $visit->service_time, 0, 5),
                'done' => $visit->status->value === 'done',
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function blocks(string $from, string $to): array
    {
        $actor = AgencyContext::user();
        $membership = AgencyContext::membership();
        $timezone = $membership->agency->timezone;
        $start = Carbon::parse($from, $timezone)->startOfDay()->utc();
        $end = Carbon::parse($to, $timezone)->endOfDay()->utc();
        $owner = Membership::query()
            ->where('agency_id', $membership->agency_id)
            ->where('role', MembershipRole::Owner)
            ->first();

        $rows = AgendaBlock::query()
            ->where('agency_id', $membership->agency_id)
            ->when($owner !== null, fn ($query) => $query->where('user_id', $owner->user_id))
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->orderBy('starts_at')
            ->get();

        return [
            'blocks' => $rows->map(fn (AgendaBlock $block): array => self::block($block, $timezone, $owner?->user_id, $actor->id))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function block(AgendaBlock $block, ?string $timezone = null, ?int $ownerUserId = null, ?int $actorId = null): array
    {
        $membership = AgencyContext::membership();
        $timezone ??= $membership->agency->timezone;
        $actorId ??= AgencyContext::user()->id;

        if ($ownerUserId === null) {
            $ownerUserId = Membership::query()
                ->where('agency_id', $membership->agency_id)
                ->where('role', MembershipRole::Owner)
                ->value('user_id');
        }

        return [
            'id' => $block->id,
            'starts_at' => $block->starts_at->timezone($timezone)->format('Y-m-d\TH:i:s'),
            'ends_at' => $block->ends_at->timezone($timezone)->format('Y-m-d\TH:i:s'),
            'note' => $block->note,
            'created_by' => $ownerUserId !== null && $block->created_by === (int) $ownerUserId ? 'owner' : 'invited',
            'mine' => $block->created_by === $actorId,
        ];
    }
}
