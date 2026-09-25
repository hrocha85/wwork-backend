<?php

namespace App\Filament\Resources\Agencies;

use App\Enums\StaffPermissionCode;
use App\Enums\Trade;
use App\Filament\Resources\Agencies\Pages\ListAgencies;
use App\Filament\Resources\Agencies\Pages\ViewAgency;
use App\Models\Agency;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AgencyResource extends Resource
{
    protected static ?string $model = Agency::class;

    protected static ?string $navigationLabel = 'Agências';

    protected static ?string $modelLabel = 'agência';

    protected static ?string $pluralModelLabel = 'Agências';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return static::staffCan(StaffPermissionCode::ManageAgencies);
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

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Agência')->schema([
                TextEntry::make('name')->label('Nome'),
                TextEntry::make('trade')->label('Ofício'),
                TextEntry::make('country')->label('País'),
                TextEntry::make('invoice_region')->label('Região da fatura'),
                TextEntry::make('timezone')->label('Fuso'),
                TextEntry::make('currency')->label('Moeda'),
                TextEntry::make('campaign')
                    ->label('Campanha')
                    ->getStateUsing(fn (Agency $record): string => $record->utm_campaign ?: ($record->utm_source ?: 'Direct')),
                TextEntry::make('ownerMembership.user.name')->label('Dono'),
                TextEntry::make('ownerMembership.user.email')->label('E-mail do dono'),
                TextEntry::make('subscription.plan')->label('Plano'),
                TextEntry::make('subscription.status')->label('Estado'),
                TextEntry::make('subscription.seats')->label('Assentos'),
                TextEntry::make('memberships_count')
                    ->label('Pessoas ativas')
                    ->getStateUsing(fn (Agency $record): int => $record->memberships()->count()),
                TextEntry::make('visits_count')
                    ->label('Visitas')
                    ->getStateUsing(fn (Agency $record): int => $record->visits()->count()),
                TextEntry::make('invoices_count')
                    ->label('Faturas da casa')
                    ->getStateUsing(fn (Agency $record): int => $record->invoices()->count()),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nome')->searchable()->sortable(),
                TextColumn::make('subscription.plan')->label('Plano')->placeholder('—'),
                TextColumn::make('trade')->label('Ofício'),
                TextColumn::make('campaign')
                    ->label('Campanha')
                    ->getStateUsing(fn (Agency $record): string => $record->utm_campaign ?: ($record->utm_source ?: 'Direct')),
                TextColumn::make('memberships_count')->label('Pessoas')->counts('memberships'),
                TextColumn::make('subscription.status')->label('Estado')->placeholder('—'),
                TextColumn::make('created_at')->label('Criada em')->dateTime()->sortable(),
                TextColumn::make('ownerMembership.user.email')
                    ->label('E-mail do dono')
                    ->searchable(),
                TextColumn::make('ownerMembership.user.last_seen_at')
                    ->label('Última entrada')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('trade')
                    ->label('Ofício')
                    ->options(collect(Trade::cases())->mapWithKeys(
                        fn (Trade $trade): array => [$trade->value => $trade->value],
                    )->all()),
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
            'index' => ListAgencies::route('/'),
            'view' => ViewAgency::route('/{record}'),
        ];
    }

    private static function staffCan(StaffPermissionCode $permission): bool
    {
        $user = auth('staff')->user();

        return $user instanceof User && $user->hasStaffPermission($permission);
    }
}
