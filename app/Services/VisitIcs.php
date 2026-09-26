<?php

namespace App\Services;

use App\Enums\MembershipRole;
use App\Models\AgendaBlock;
use App\Models\Membership;
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

        $timezone = $membership->agency->timezone;

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

        if ($membership->role === MembershipRole::Owner) {
            $ownerId = Membership::query()
                ->where('agency_id', $membership->agency_id)
                ->where('role', MembershipRole::Owner)
                ->value('user_id');
            $rangeStart = Carbon::parse($from, $timezone)->startOfDay()->utc();
            $rangeEnd = Carbon::parse($to, $timezone)->endOfDay()->utc();
            $blocks = AgendaBlock::query()
                ->where('agency_id', $membership->agency_id)
                ->where('user_id', $ownerId)
                ->where('starts_at', '<', $rangeEnd)
                ->where('ends_at', '>', $rangeStart)
                ->orderBy('starts_at')
                ->get();

            foreach ($blocks as $block) {
                $lines[] = 'BEGIN:VEVENT';
                $lines[] = 'UID:'.$block->sync_uuid.'@wwork';
                $lines[] = 'SUMMARY:WWork unavailable';
                $lines[] = 'DTSTART:'.$block->starts_at->timezone($timezone)->format('Ymd\THis');
                $lines[] = 'DTEND:'.$block->ends_at->timezone($timezone)->format('Ymd\THis');
                $lines[] = 'END:VEVENT';
            }
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', ';', ',', "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', ''], $value);
    }
}
