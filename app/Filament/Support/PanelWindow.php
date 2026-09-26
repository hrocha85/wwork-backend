<?php

namespace App\Filament\Support;

use Carbon\CarbonImmutable;

final class PanelWindow
{
    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable, previousStart: CarbonImmutable, previousEnd: CarbonImmutable}
     */
    public static function range(?string $period): array
    {
        $end = CarbonImmutable::now();
        $start = match ($period) {
            'today' => $end->startOfDay(),
            '7' => $end->subDays(7),
            'month' => $end->startOfMonth(),
            'year' => $end->startOfYear(),
            default => $end->subDays(30),
        };
        $seconds = max(1, $start->diffInSeconds($end));

        return [
            'start' => $start,
            'end' => $end,
            'previousStart' => $start->subSeconds($seconds),
            'previousEnd' => $start,
        ];
    }
}
