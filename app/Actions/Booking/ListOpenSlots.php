<?php

namespace App\Actions\Booking;

use App\Models\Agency;
use App\Models\AgendaBlock;
use App\Models\BookingService;
use App\Models\Visit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ListOpenSlots
{
    /**
     * @return list<array{date: string, slots: list<array{service_id: int, time: string}>}>
     */
    public function __invoke(Agency $agency, int $days = 14): array
    {
        $agency->loadMissing(['bookingServices', 'bookingHours', 'ownerMembership']);
        $ownerId = $agency->ownerMembership?->user_id;
        if ($ownerId === null || $agency->bookingServices->isEmpty() || $agency->bookingHours->isEmpty()) {
            return [];
        }

        $timezone = $agency->timezone ?: 'UTC';
        $start = Carbon::now($timezone)->startOfDay();
        $end = $start->copy()->addDays($days - 1)->endOfDay();
        $hours = $agency->bookingHours->groupBy('weekday');
        $visits = Visit::query()
            ->where('agency_id', $agency->id)
            ->where('assignee_id', $ownerId)
            ->whereDate('service_date', '>=', $start->toDateString())
            ->whereDate('service_date', '<=', $end->toDateString())
            ->get();
        $blocks = AgendaBlock::query()
            ->where('agency_id', $agency->id)
            ->where('user_id', $ownerId)
            ->where('starts_at', '<', $end->copy()->utc())
            ->where('ends_at', '>', $start->copy()->utc())
            ->get();

        $daysOut = [];
        $names = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        for ($offset = 0; $offset < $days; $offset++) {
            $day = $start->copy()->addDays($offset);
            $weekday = $names[$day->dayOfWeekIso - 1];
            $windows = $hours->get($weekday, collect());
            $slots = [];
            foreach ($agency->bookingServices as $service) {
                foreach ($windows as $window) {
                    $slots = array_merge($slots, $this->windowSlots($service, $day, (string) $window->starts_at, (string) $window->ends_at, $timezone, $visits, $blocks));
                }
            }
            if ($slots !== []) {
                $daysOut[] = ['date' => $day->toDateString(), 'slots' => $slots];
            }
        }

        return $daysOut;
    }

    public function open(Agency $agency, int $serviceId, string $date, string $time): bool
    {
        foreach ($this($agency) as $day) {
            if ($day['date'] !== $date) {
                continue;
            }
            foreach ($day['slots'] as $slot) {
                if ($slot['service_id'] === $serviceId && $slot['time'] === $time) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, Visit>  $visits
     * @param  Collection<int, AgendaBlock>  $blocks
     * @return list<array{service_id: int, time: string}>
     */
    private function windowSlots(BookingService $service, Carbon $day, string $starts, string $ends, string $timezone, Collection $visits, Collection $blocks): array
    {
        $cursor = Carbon::parse($day->toDateString().' '.substr($starts, 0, 5), $timezone);
        $limit = Carbon::parse($day->toDateString().' '.substr($ends, 0, 5), $timezone);
        $step = $service->duration_minutes;
        $now = Carbon::now($timezone);
        $slots = [];

        while ($cursor->copy()->addMinutes($step)->lte($limit)) {
            $slotEnd = $cursor->copy()->addMinutes($step);
            if ($cursor->greaterThan($now) && ! $this->taken($cursor, $slotEnd, $step, $timezone, $visits, $blocks)) {
                $slots[] = ['service_id' => $service->id, 'time' => $cursor->format('H:i')];
            }
            $cursor->addMinutes($step);
        }

        return $slots;
    }

    /**
     * @param  Collection<int, Visit>  $visits
     * @param  Collection<int, AgendaBlock>  $blocks
     */
    private function taken(Carbon $slotStart, Carbon $slotEnd, int $minutes, string $timezone, Collection $visits, Collection $blocks): bool
    {
        foreach ($visits as $visit) {
            $visitStart = Carbon::parse($visit->service_date->toDateString().' '.substr((string) $visit->service_time, 0, 5), $timezone);
            $visitEnd = $visitStart->copy()->addMinutes($minutes);
            if ($visitStart->lt($slotEnd) && $visitEnd->gt($slotStart)) {
                return true;
            }
        }

        foreach ($blocks as $block) {
            if ($block->starts_at->lt($slotEnd->copy()->utc()) && $block->ends_at->gt($slotStart->copy()->utc())) {
                return true;
            }
        }

        return false;
    }
}
