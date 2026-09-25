<?php

namespace App\Services;

use App\Enums\MembershipRole;
use App\Models\Visit;
use App\Support\AgencyContext;
use Illuminate\Support\Carbon;

class VisitIcs
{
    public function render(string $from, string $to): string
    {
        $actor = AgencyContext::user();
        $membership = AgencyContext::membership();
        $query = Visit::query()
            ->where('agency_id', $membership->agency_id)
            ->whereDate('service_date', '>=', $from)
            ->whereDate('service_date', '<=', $to)
            ->with('client')
            ->orderBy('service_date')
            ->orderBy('service_time');

        if ($membership->role === MembershipRole::Invited) {
            $query->where('assignee_id', $actor->id);
        }

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//WWork//EN',
        ];

        foreach ($query->get() as $visit) {
            $start = Carbon::parse($visit->service_date->toDateString().' '.substr((string) $visit->service_time, 0, 8));
            $end = $start->copy()->addHour();
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:'.$visit->sync_uuid.'@wwork';
            $lines[] = 'SUMMARY:WWork';
            $lines[] = 'DTSTART:'.$start->format('Ymd\THis');
            $lines[] = 'DTEND:'.$end->format('Ymd\THis');
            $lines[] = 'LOCATION:'.$this->escape((string) $visit->client->address);
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', ';', ',', "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', ''], $value);
    }
}
