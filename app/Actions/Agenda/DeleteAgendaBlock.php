<?php

namespace App\Actions\Agenda;

use App\Models\AgendaBlock;
use App\Policies\AgendaBlockPolicy;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;

class DeleteAgendaBlock
{
    public function __invoke(AgendaBlock $block): void
    {
        $actor = AgencyContext::user();

        if (! app(AgendaBlockPolicy::class)->delete($actor, $block)) {
            throw new ApiException(ErrorCodes::AGENDA_BLOCK_FORBIDDEN, 403);
        }

        $block->delete();
        RecordActivity::add($block->agency_id, $actor->id, 'agenda.block_deleted');
    }
}
