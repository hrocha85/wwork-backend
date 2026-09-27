<?php

namespace App\Filament\Resources\People\Pages;

use App\Filament\Resources\People\PersonResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewPerson extends ViewRecord
{
    protected static string $resource = PersonResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('panel.people.view_help');
    }
}
