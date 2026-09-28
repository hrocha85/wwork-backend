<?php

namespace App\Actions\Booking;

use App\Enums\BookingRequestStatus;
use App\Models\Agency;
use App\Models\AgendaBlock;
use App\Models\BookingHour;
use App\Models\BookingRequest;
use App\Models\BookingService;
use App\Models\Visit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ListOpenSlots
{
    /**
     * @return list<array{date: string, slots: list<array{service_id: int, time: string, remaining: int}>}>
     */
    public function __invoke(Agency $agency, Carbon $start, Carbon $end): array
    {
        $agency->loadMissing(['bookingServices', 'bookingHours', 'ownerMembership']);
        $ownerId = $agency->ownerMembership?->user_id;
        if ($ownerId === null || $agency->bookingServices->isEmpty() || $agency->bookingHours->isEmpty()) {
            return [];
        }

        $timezone = $agency->timezone ?: 'UTC';
        $from = $start->copy()->timezone($timezone)->startOfDay();
        $to = $end->copy()->timezone($timezone)->endOfDay();
        $hours = $agency->bookingHours->groupBy('weekday');
        $visits = Visit::query()
            ->where('agency_id', $agency->id)
            ->whereDate('service_date', '>=', $from->toDateString())
            ->whereDate('service_date', '<=', $to->toDateString())
            ->get();
        $requests = BookingRequest::query()
            ->where('agency_id', $agency->id)
            ->whereIn('status', [BookingRequestStatus::Pending->value, BookingRequestStatus::Approved->value])
            ->whereDate('requested_date', '>=', $from->toDateString())
            ->whereDate('requested_date', '<=', $to->toDateString())
            ->with('service')
            ->get();
        $blocks = AgendaBlock::query()
            ->where('agency_id', $agency->id)
            ->where('user_id', $ownerId)
            ->where('starts_at', '<', $to->copy()->utc())
            ->where('ends_at', '>', $from->copy()->utc())
            ->get();

        $daysOut = [];
        $names = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $cursor = $from->copy();
        $last = $to->copy()->startOfDay();
        while ($cursor->lte($last)) {
            $weekday = $names[$cursor->dayOfWeekIso - 1];
            $windows = $hours->get($weekday, collect());
            $slots = [];
            foreach ($agency->bookingServices as $service) {
                foreach ($windows as $window) {
                    $slots = array_merge($slots, $this->windowSlots($service, $cursor->copy(), $window, $timezone, $visits, $requests, $blocks));
                }
            }
            if ($slots !== []) {
                $daysOut[] = ['date' => $cursor->toDateString(), 'slots' => $this->uniqueSlots($slots)];
            }
            $cursor->addDay();
        }

        return $daysOut;
    }

    public function free(Agency $agency, int $serviceId, string $date, string $time, ?int $ignoreRequestId = null): bool
    {
        $timezone = $agency->timezone ?: 'UTC';
        $day = Carbon::parse($date, $timezone)->startOfDay();
        foreach ($this($agency, $day, $day->copy()->endOfDay()) as $row) {
            if ($row['date'] !== $date) {
                continue;
            }
            foreach ($row['slots'] as $slot) {
                if ($slot['service_id'] === $serviceId && $slot['time'] === $time && $slot['remaining'] > 0) {
                    return true;
                }
            }
        }

        if ($ignoreRequestId === null) {
            return false;
        }

        return $this->roomWithout($agency, $serviceId, $date, $time, $ignoreRequestId);
    }

    /**
     * @param  Collection<int, Visit>  $visits
     * @param  Collection<int, BookingRequest>  $requests
     * @param  Collection<int, AgendaBlock>  $blocks
     * @return list<array{service_id: int, time: string, remaining: int}>
     */
    private function windowSlots(
        BookingService $service,
        Carbon $day,
        BookingHour $window,
        string $timezone,
        Collection $visits,
        Collection $requests,
        Collection $blocks,
    ): array {
        $starts = substr((string) $window->starts_at, 0, 5);
        $ends = substr((string) $window->ends_at, 0, 5);
        $cursor = Carbon::parse($day->toDateString().' '.$starts, $timezone);
        $limit = Carbon::parse($day->toDateString().' '.$ends, $timezone);
        $step = $service->duration_minutes;
        $capacity = max(1, (int) $window->concurrent_slots);
        $now = Carbon::now($timezone);
        $slots = [];

        while ($cursor->copy()->addMinutes($step)->lte($limit)) {
            $slotEnd = $cursor->copy()->addMinutes($step);
            if ($cursor->greaterThan($now) && ! $this->blocked($cursor, $slotEnd, $blocks)) {
                $taken = $this->occupied($cursor, $slotEnd, $step, $timezone, $visits, $requests, null);
                $remaining = $capacity - $taken;
                if ($remaining > 0) {
                    $slots[] = [
                        'service_id' => $service->id,
                        'time' => $cursor->format('H:i'),
                        'remaining' => $remaining,
                    ];
                }
            }
            $cursor->addMinutes($step);
        }

        return $slots;
    }

    /**
     * @param  list<array{service_id: int, time: string, remaining: int}>  $slots
     * @return list<array{service_id: int, time: string, remaining: int}>
     */
    private function uniqueSlots(array $slots): array
    {
        $best = [];
        foreach ($slots as $slot) {
            $key = $slot['service_id'].'|'.$slot['time'];
            if (! isset($best[$key]) || $slot['remaining'] > $best[$key]['remaining']) {
                $best[$key] = $slot;
            }
        }

        return array_values($best);
    }

    /**
     * @param  Collection<int, AgendaBlock>  $blocks
     */
    private function blocked(Carbon $slotStart, Carbon $slotEnd, Collection $blocks): bool
    {
        foreach ($blocks as $block) {
            if ($block->starts_at->lt($slotEnd->copy()->utc()) && $block->ends_at->gt($slotStart->copy()->utc())) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, Visit>  $visits
     * @param  Collection<int, BookingRequest>  $requests
     */
    private function occupied(
        Carbon $slotStart,
        Carbon $slotEnd,
        int $minutes,
        string $timezone,
        Collection $visits,
        Collection $requests,
        ?int $ignoreRequestId,
    ): int {
        $count = 0;
        foreach ($visits as $visit) {
            $visitStart = Carbon::parse($visit->service_date->toDateString().' '.substr((string) $visit->service_time, 0, 5), $timezone);
            $visitEnd = $visitStart->copy()->addMinutes($minutes);
            if ($visitStart->lt($slotEnd) && $visitEnd->gt($slotStart)) {
                $count++;
            }
        }

        foreach ($requests as $request) {
            if ($ignoreRequestId !== null && $request->id === $ignoreRequestId) {
                continue;
            }
            $requestStart = Carbon::parse($request->requested_date->toDateString().' '.substr((string) $request->requested_time, 0, 5), $timezone);
            $duration = $request->service?->duration_minutes ?? $minutes;
            $requestEnd = $requestStart->copy()->addMinutes($duration);
            if ($requestStart->lt($slotEnd) && $requestEnd->gt($slotStart)) {
                $count++;
            }
        }

        return $count;
    }

    private function roomWithout(Agency $agency, int $serviceId, string $date, string $time, int $ignoreRequestId): bool
    {
        $timezone = $agency->timezone ?: 'UTC';
        $service = $agency->bookingServices->firstWhere('id', $serviceId);
        if ($service === null) {
            return false;
        }
        $day = Carbon::parse($date, $timezone);
        $names = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $weekday = $names[$day->dayOfWeekIso - 1];
        $slotStart = Carbon::parse($date.' '.$time, $timezone);
        $slotEnd = $slotStart->copy()->addMinutes($service->duration_minutes);
        $windows = $agency->bookingHours->where('weekday', $weekday);
        foreach ($windows as $window) {
            $opens = Carbon::parse($date.' '.substr((string) $window->starts_at, 0, 5), $timezone);
            $closes = Carbon::parse($date.' '.substr((string) $window->ends_at, 0, 5), $timezone);
            if ($slotStart->lt($opens) || $slotEnd->gt($closes)) {
                continue;
            }
            $visits = Visit::query()
                ->where('agency_id', $agency->id)
                ->whereDate('service_date', $date)
                ->get();
            $requests = BookingRequest::query()
                ->where('agency_id', $agency->id)
                ->whereIn('status', [BookingRequestStatus::Pending->value, BookingRequestStatus::Approved->value])
                ->whereDate('requested_date', $date)
                ->with('service')
                ->get();
            $taken = $this->occupied($slotStart, $slotEnd, $service->duration_minutes, $timezone, $visits, $requests, $ignoreRequestId);
            if ($taken < max(1, (int) $window->concurrent_slots)) {
                return true;
            }
        }

        return false;
    }
}
