<?php

namespace App\Actions\Visits;

use App\Enums\MembershipRole;
use App\Enums\VisitStatus;
use App\Models\AgendaBlock;
use App\Models\Membership;
use App\Models\Visit;
use App\Models\VisitGoal;
use Illuminate\Support\Carbon;

/**
 * Expands a weekly recurring visit into the actual future occurrences.
 * Dates that would collide with an existing visit or an agenda block are skipped,
 * so the caller always ends up with a valid agenda instead of a half-created batch.
 */
class ExpandRecurrence
{
    /**
     * @param  array<string, mixed>  $input
     * @return list<Visit>
     */
    public function __invoke(Visit $parent, array $input): array
    {
        if (! ($input['is_recurring'] ?? false)) {
            return [];
        }

        $days = array_values(array_filter(
            is_array($input['recurring_days'] ?? null) ? $input['recurring_days'] : [],
            fn ($day) => is_string($day) && in_array($day, self::DAYS, true),
        ));

        if ($days === []) {
            return [];
        }

        $timezone = $parent->agency?->timezone ?: 'UTC';
        $agencyId = (int) $parent->agency_id;
        $assigneeId = (int) $parent->assignee_id;
        $isOwner = $this->isOwner($agencyId, $assigneeId);
        $weeks = max(1, (int) config('wwork.recurrence_weeks', 12));

        $created = [];
        $cursor = Carbon::parse($parent->service_date->toDateString(), $timezone)->addWeek();
        $limit = Carbon::parse($parent->service_date->toDateString(), $timezone)->addWeeks($weeks);

        while ($cursor->lte($limit)) {
            $weekday = self::DAYS[$cursor->dayOfWeekIso - 1];

            if (in_array($weekday, $days, true)) {
                $date = $cursor->toDateString();
                $time = substr((string) $parent->service_time, 0, 5);

                if ($this->free($parent, $date, $time, $timezone)) {
                    if ($isOwner && ! $this->blocked($agencyId, $assigneeId, $date, $time, $timezone)) {
                        $created[] = $this->duplicate($parent, $date, $time);
                    }
                }
            }

            $cursor->addDay();
        }

        return $created;
    }

    private function duplicate(Visit $parent, string $date, string $time): Visit
    {
        $visit = Visit::query()->create([
            'agency_id' => $parent->agency_id,
            'client_id' => $parent->client_id,
            'assignee_id' => $parent->assignee_id,
            'service_date' => $date,
            'service_time' => $time,
            'estimated_end_time' => $parent->estimated_end_time,
            'description' => $parent->description,
            'price_pence' => $parent->price_pence,
            'partner_earning_pence' => $parent->partner_earning_pence,
            'rate' => $parent->rate,
            'lat' => $parent->lat,
            'lng' => $parent->lng,
            'status' => $parent->status === VisitStatus::Offered ? VisitStatus::Offered : VisitStatus::Todo,
            'parent_visit_id' => $parent->parent_visit_id ?? $parent->id,
        ]);

        foreach ($parent->goals as $goal) {
            VisitGoal::query()->create([
                'visit_id' => $visit->id,
                'text' => $goal->text,
                'completed' => null,
            ]);
        }

        return $visit;
    }

    private function free(Visit $parent, string $date, string $time, string $timezone): bool
    {
        $start = Carbon::parse($date.' '.$time, $timezone);
        $end = $start->copy()->addMinutes($this->minutes($parent));

        $clash = Visit::query()
            ->where('agency_id', $parent->agency_id)
            ->where('assignee_id', $parent->assignee_id)
            ->whereDate('service_date', $date)
            ->where('id', '!=', $parent->id)
            ->get()
            ->contains(function (Visit $other) use ($start, $end, $timezone) {
                $otherStart = Carbon::parse($other->service_date->toDateString().' '.substr((string) $other->service_time, 0, 5), $timezone);
                $otherEnd = $otherStart->copy()->addMinutes($this->minutes($other));

                return $start->lt($otherEnd) && $end->gt($otherStart);
            });

        return ! $clash;
    }

    private function blocked(int $agencyId, int $userId, string $date, string $time, string $timezone): bool
    {
        $moment = Carbon::parse($date.' '.substr($time, 0, 8), $timezone)->utc();

        return AgendaBlock::query()
            ->where('agency_id', $agencyId)
            ->where('user_id', $userId)
            ->where('starts_at', '<=', $moment)
            ->where('ends_at', '>', $moment)
            ->exists();
    }

    private function minutes(Visit $visit): int
    {
        if ($visit->estimated_end_time === null) {
            return 60;
        }

        $start = Carbon::parse(substr((string) $visit->service_time, 0, 5));
        $end = Carbon::parse(substr((string) $visit->estimated_end_time, 0, 5));
        $minutes = $start->diffInMinutes($end);

        return $minutes > 0 ? (int) $minutes : 60;
    }

    private function isOwner(int $agencyId, int $userId): bool
    {
        return Membership::query()
            ->where('agency_id', $agencyId)
            ->where('user_id', $userId)
            ->where('role', MembershipRole::Owner)
            ->exists();
    }

    /** @var list<string> */
    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
}
