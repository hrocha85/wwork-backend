<?php

namespace App\Filament\Resources\People;

use App\Enums\StaffPermissionCode;
use App\Filament\Resources\People\Pages\ListPeople;
use App\Filament\Resources\People\Pages\ViewPerson;
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

class PersonResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'people';

    protected static ?string $navigationLabel = 'Pessoas';

    protected static ?string $modelLabel = 'pessoa';

    protected static ?string $pluralModelLabel = 'Pessoas';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 2;

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
            Section::make('Pessoa')->schema([
                TextEntry::make('name')->label('Nome'),
                TextEntry::make('email')->label('E-mail'),
                TextEntry::make('membership.agency.name')->label('Agência'),
                TextEntry::make('membership.role')->label('Papel'),
                TextEntry::make('locale')->label('Língua'),
                TextEntry::make('last_seen_at')->label('Última entrada')->placeholder('—'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nome')->searchable(),
                TextColumn::make('email')->label('E-mail')->searchable(),
                TextColumn::make('membership.agency.name')->label('Agência'),
                TextColumn::make('membership.role')->label('Papel'),
                TextColumn::make('locale')->label('Língua'),
                TextColumn::make('last_seen_at')->label('Última entrada')->dateTime()->placeholder('—'),
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
