<?php

namespace App\Filament\Resources\EndClients\Pages;

use App\Filament\Resources\EndClients\EndClientResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListEndClients extends ListRecords
{
    protected static string $resource = EndClientResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('panel.end_clients.list_help');
    }
}
