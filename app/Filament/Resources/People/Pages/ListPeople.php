<?php

namespace App\Filament\Resources\People\Pages;

use App\Filament\Resources\People\PersonResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListPeople extends ListRecords
{
    protected static string $resource = PersonResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('panel.people.list_help');
    }
}
