<?php

namespace App\Filament\Resources\EndClients;

use App\Filament\Resources\Concerns\ReadOnlyResource;
use App\Filament\Resources\EndClients\Pages\ListEndClients;
use App\Models\Client;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class EndClientResource extends Resource
{
    use ReadOnlyResource;

    protected static ?string $model = Client::class;

    protected static ?string $slug = 'end-clients';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('panel.nav.end_clients');
    }

    public static function getModelLabel(): string
    {
        return __('panel.end_clients.one');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.nav.end_clients');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('panel.nav.operations');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['agency', 'visits', 'reviews']))
            ->columns([
                TextColumn::make('name')->label(__('panel.end_clients.name'))->searchable(),
                TextColumn::make('email')->label(__('panel.end_clients.email'))->searchable()->placeholder('—'),
                TextColumn::make('phone')->label(__('panel.end_clients.phone'))->placeholder('—'),
                TextColumn::make('agency.name')->label(__('panel.end_clients.agency')),
                TextColumn::make('agency.trade')->label(__('panel.end_clients.trade'))->formatStateUsing(fn (mixed $state): string => \App\Filament\Support\PanelLabels::trade($state)),
                TextColumn::make('agency.country')->label(__('panel.end_clients.country')),
                TextColumn::make('agency.currency')->label(__('panel.end_clients.currency')),
                IconColumn::make('user_id')->label(__('panel.end_clients.has_account'))->boolean(fn (Client $record): bool => $record->user_id !== null),
                TextColumn::make('visits_count')->label(__('panel.end_clients.visits'))->counts('visits'),
                TextColumn::make('last_visit')->label(__('panel.end_clients.last_visit'))->state(fn (Client $record): ?string => $record->visits->max('service_date')?->toDateString())->placeholder('—'),
                TextColumn::make('last_rating')->label(__('panel.end_clients.last_rating'))->state(fn (Client $record): ?int => $record->reviews->sortByDesc('id')->first()?->rating)->placeholder('—'),
            ])
            ->filters([
                TernaryFilter::make('email_without_account')
                    ->label(__('panel.end_clients.filter_email_no_account'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('email')->whereNull('user_id'),
                        false: fn (Builder $query): Builder => $query->where(fn (Builder $q): Builder => $q->whereNull('email')->orWhereNotNull('user_id')),
                    ),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEndClients::route('/'),
        ];
    }
}
