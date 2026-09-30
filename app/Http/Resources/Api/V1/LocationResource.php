<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\MembershipRole;
use App\Enums\VisitStatus;
use App\Models\Membership;
use App\Models\Visit;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LocationResource
{
    /**
     * @return array<string, mixed>
     */
    public static function index(): array
    {
        $actor = AgencyContext::user();
        $membership = AgencyContext::membership();

        if ($membership->role !== MembershipRole::Owner) {
            throw new ApiException(ErrorCodes::TEAM_INVITE_FORBIDDEN, 403);
        }

        $timezone = $membership->agency->timezone;
        $today = Carbon::now($timezone)->toDateString();

        $visits = Visit::query()
            ->where('agency_id', $membership->agency_id)
            ->whereDate('service_date', $today)
            ->whereIn('status', [VisitStatus::EnRoute, VisitStatus::CheckedIn])
            ->get()
            ->groupBy('assignee_id');

        $rows = Membership::query()
            ->where('agency_id', $membership->agency_id)
            ->with('user')
            ->get()
            ->sortBy(fn (Membership $row): string => $row->user_id === $actor->id ? '0' : '1'.$row->user->name)
            ->values();

        return [
            'locations' => $rows->map(function (Membership $row) use ($actor, $timezone, $today, $visits): array {
                $visit = self::work($visits->get($row->user_id));
                [$status, $since] = self::presence($row, $visit, $timezone, $today);
                $user = $row->user;
                $located = $user->last_located_at !== null;

                return [
                    'user_id' => $row->user_id,
                    'name' => $user->name,
                    'has_avatar' => filled($user->avatar_path),
                    'lat' => $user->last_lat === null ? null : (float) $user->last_lat,
                    'lng' => $user->last_lng === null ? null : (float) $user->last_lng,
                    'at' => $located
                        ? Carbon::parse($user->last_located_at)->timezone($timezone)->toIso8601String()
                        : null,
                    'self' => $row->user_id === $actor->id,
                    'status' => $status,
                    'since' => $since,
                ];
            })->all(),
        ];
    }

    private static function work(mixed $group): ?Visit
    {
        if (! $group instanceof Collection || $group->isEmpty()) {
            return null;
        }

        $checked = $group->first(fn (Visit $visit): bool => $visit->status === VisitStatus::CheckedIn);

        if ($checked instanceof Visit) {
            return $checked;
        }

        $moving = $group->first(fn (Visit $visit): bool => $visit->status === VisitStatus::EnRoute);

        return $moving instanceof Visit ? $moving : null;
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private static function presence(Membership $row, ?Visit $visit, string $timezone, string $today): array
    {
        if ($visit !== null && $visit->status === VisitStatus::CheckedIn) {
            $since = $visit->check_in_at === null
                ? null
                : Carbon::parse($visit->check_in_at)->timezone($timezone)->format('H:i');

            return ['working', $since];
        }

        if ($visit !== null && $visit->status === VisitStatus::EnRoute) {
            return ['traveling', null];
        }

        $at = $row->user->last_located_at;

        if ($at === null) {
            return ['away', null];
        }

        $located = Carbon::parse($at);

        if ($located->greaterThan(now()->subMinutes(2))) {
            return ['online', null];
        }

        if ($located->greaterThan(now()->subMinutes(15))) {
            return ['recent', null];
        }

        $local = $located->copy()->timezone($timezone);

        if ($local->toDateString() === $today) {
            return ['today', $local->format('H:i')];
        }

        return ['away', null];
    }
}
