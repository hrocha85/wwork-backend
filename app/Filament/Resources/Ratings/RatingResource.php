<?php

namespace App\Filament\Resources\Ratings;

use App\Filament\Resources\Concerns\ReadOnlyResource;
use App\Filament\Resources\Ratings\Pages\ListRatings;
use App\Models\Review;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class RatingResource extends Resource
{
    use ReadOnlyResource;

    protected static ?string $model = Review::class;

    protected static ?string $slug = 'ratings';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?int $navigationSort = 6;

    public static function getNavigationLabel(): string
    {
        return __('panel.nav.ratings');
    }

    public static function getModelLabel(): string
    {
        return __('panel.ratings.one');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.nav.ratings');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('panel.nav.operations');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['agency', 'client', 'visit']))
            ->columns([
                TextColumn::make('rating')->label(__('panel.ratings.rating')),
                TextColumn::make('comment')->label(__('panel.ratings.comment'))->limit(60)->placeholder('—'),
                TextColumn::make('client.name')->label(__('panel.ratings.client')),
                TextColumn::make('agency.name')->label(__('panel.ratings.agency')),
                TextColumn::make('visit.service_date')->label(__('panel.ratings.service_date'))->date(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRatings::route('/'),
        ];
    }
}
