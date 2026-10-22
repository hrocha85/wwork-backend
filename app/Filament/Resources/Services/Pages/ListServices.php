<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Services\ServiceResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListServices extends ListRecords
{
    protected static string $resource = ServiceResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('panel.services.list_help');
    }
}
