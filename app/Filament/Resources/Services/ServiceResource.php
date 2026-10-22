<?php

namespace App\Filament\Resources\Services;

use App\Filament\Resources\Concerns\ReadOnlyResource;
use App\Filament\Resources\Services\Pages\ListServices;
use App\Filament\Support\PanelLabels;
use App\Models\Visit;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ServiceResource extends Resource
{
    use ReadOnlyResource;

    protected static ?string $model = Visit::class;

    protected static ?string $slug = 'services';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('panel.nav.services');
    }

    public static function getModelLabel(): string
    {
        return __('panel.services.one');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.nav.services');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('panel.nav.operations');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['agency', 'client', 'assignee'])->withCount('photos'))
            ->columns([
                TextColumn::make('service_date')->label(__('panel.services.date'))->date(),
                TextColumn::make('client.name')->label(__('panel.services.client')),
                TextColumn::make('agency.name')->label(__('panel.services.agency')),
                TextColumn::make('assignee.name')->label(__('panel.services.assignee')),
                TextColumn::make('price_pence')->label(__('panel.services.value'))->formatStateUsing(fn (mixed $state, Visit $record): string => PanelLabels::money((int) ($state ?? 0), (string) $record->agency?->currency)),
                TextColumn::make('agency.currency')->label(__('panel.services.currency')),
                TextColumn::make('status')->label(__('panel.services.status'))->badge()->formatStateUsing(fn (mixed $state): string => $state instanceof \BackedEnum ? $state->value : (string) $state),
                TextColumn::make('payment_method')->label(__('panel.services.payment'))->placeholder('—'),
                TextColumn::make('photos_count')->label(__('panel.services.photos')),
                IconColumn::make('reviewed')->label(__('panel.services.reviewed'))->boolean(fn (Visit $record): bool => \App\Models\Review::query()->where('visit_id', $record->id)->exists()),
            ])
            ->defaultSort('service_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServices::route('/'),
        ];
    }
}
