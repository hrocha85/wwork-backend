<?php

namespace App\Filament\Resources\Ratings\Pages;

use App\Filament\Resources\Ratings\RatingResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListRatings extends ListRecords
{
    protected static string $resource = RatingResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('panel.ratings.list_help');
    }
}
