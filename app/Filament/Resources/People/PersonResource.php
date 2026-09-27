<?php

namespace App\Filament\Resources\People;

use App\Enums\StaffPermissionCode;
use App\Filament\Resources\People\Pages\ListPeople;
use App\Filament\Resources\People\Pages\ViewPerson;
use App\Filament\Support\PanelLabels;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PersonResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'people';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('panel.nav.people');
    }

    public static function getModelLabel(): string
    {
        return __('panel.people.one');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.nav.people');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('panel.nav.directory');
    }

    public static function canViewAny(): bool
    {
        $user = auth('staff')->user();

        return $user instanceof User && $user->hasStaffPermission(StaffPermissionCode::ManageAgencies);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('membership');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('panel.people.section'))
                ->description(__('panel.people.section_help'))
                ->schema([
                TextEntry::make('name')->label(__('panel.people.name')),
                TextEntry::make('email')->label(__('panel.people.email')),
                TextEntry::make('membership.agency.name')->label(__('panel.people.agency')),
                TextEntry::make('membership.role')->label(__('panel.people.role'))->badge()->color(fn (mixed $state): string => PanelLabels::roleColor($state))->formatStateUsing(fn (mixed $state): string => PanelLabels::role($state)),
                TextEntry::make('locale')->label(__('panel.people.locale'))->formatStateUsing(fn (mixed $state): string => __('panel.locale.'.($state instanceof \BackedEnum ? $state->value : $state))),
                TextEntry::make('last_seen_at')->label(__('panel.people.last_seen'))->placeholder('—'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('panel.people.name'))->searchable(),
                TextColumn::make('email')->label(__('panel.people.email'))->searchable(),
                TextColumn::make('membership.agency.name')->label(__('panel.people.agency')),
                TextColumn::make('membership.role')->label(__('panel.people.role'))->badge()->color(fn (mixed $state): string => PanelLabels::roleColor($state))->formatStateUsing(fn (mixed $state): string => PanelLabels::role($state)),
                TextColumn::make('locale')->label(__('panel.people.locale'))->formatStateUsing(fn (mixed $state): string => __('panel.locale.'.($state instanceof \BackedEnum ? $state->value : $state))),
                TextColumn::make('last_seen_at')->label(__('panel.people.last_seen'))->dateTime()->placeholder('—'),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPeople::route('/'),
            'view' => ViewPerson::route('/{record}'),
        ];
    }
}
