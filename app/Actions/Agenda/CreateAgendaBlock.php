<?php

namespace App\Actions\Agenda;

use App\Enums\MembershipRole;
use App\Models\AgendaBlock;
use App\Models\Membership;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;
use Illuminate\Support\Carbon;

class CreateAgendaBlock
{
    public function __invoke(array $input): AgendaBlock
    {
        $actor = AgencyContext::user();
        $membership = AgencyContext::membership();
        $timezone = $membership->agency->timezone;
        $start = $this->moment($input['starts_at'] ?? null, $timezone);
        $end = $this->moment($input['ends_at'] ?? null, $timezone);

        if ($start === null || $end === null || $end->lessThanOrEqualTo($start) || $start->toDateString() !== $end->toDateString()) {
            throw new ApiException(ErrorCodes::AGENDA_ENDS_BEFORE_START, 422);
        }

        $note = trim((string) ($input['note'] ?? ''));

        if (mb_strlen($note) > 120) {
            throw new ApiException(ErrorCodes::AGENDA_NOTE_TOO_LONG, 422);
        }

        $owner = Membership::query()
            ->where('agency_id', $membership->agency_id)
            ->where('role', MembershipRole::Owner)
            ->first();

        if ($owner === null) {
            throw new ApiException(ErrorCodes::AGENDA_BLOCK_FORBIDDEN, 403);
        }

        $block = AgendaBlock::query()->create([
            'agency_id' => $membership->agency_id,
            'user_id' => $owner->user_id,
            'created_by' => $actor->id,
            'starts_at' => $start->utc(),
            'ends_at' => $end->utc(),
            'note' => $note === '' ? null : $note,
        ]);

        RecordActivity::add($membership->agency_id, $actor->id, 'agenda.blocked');

        return $block;
    }

    private function moment(mixed $value, string $timezone): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value, $timezone);
        } catch (\Throwable) {
            return null;
        }
    }
}
