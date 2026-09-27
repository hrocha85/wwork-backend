<?php

namespace App\Filament\Resources\Subscriptions;

use App\Enums\BillingInterval;
use App\Enums\StaffPermissionCode;
use App\Enums\SubscriptionStatus;
use App\Filament\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Filament\Resources\Subscriptions\Pages\ViewSubscription;
use App\Filament\Support\PanelLabels;
use App\Models\Subscription;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 0;

    public static function getNavigationLabel(): string
    {
        return __('panel.nav.subscriptions');
    }

    public static function getModelLabel(): string
    {
        return __('panel.subscriptions.one');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.nav.subscriptions');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('panel.nav.subscriptions');
    }

    public static function getRecordTitle(?Model $record): ?string
    {
        return $record instanceof Subscription ? $record->agency?->name : null;
    }

    public static function canViewAny(): bool
    {
        return static::seesRevenue();
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
        return parent::getEloquentQuery()->with([
            'agency' => function ($query) {
                $query->withCount('memberships')->with('ownerMembership.user');
            },
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('panel.subscriptions.section'))
                ->description(__('panel.subscriptions.section_help'))
                ->schema([
                    TextEntry::make('agency.name')->label(__('panel.people.agency')),
                    TextEntry::make('agency.country')->label(__('panel.agency.country')),
                    TextEntry::make('agency.trade')->label(__('panel.agency.trade'))->badge()->color('primary')->formatStateUsing(fn (mixed $state): string => PanelLabels::trade($state)),
                    TextEntry::make('agency.ownerMembership.user.email')->label(__('panel.agency.owner_email')),
                    TextEntry::make('plan')->label(__('panel.agency.plan'))->badge()->color('primary')->formatStateUsing(fn (mixed $state): string => PanelLabels::plan($state)),
                    TextEntry::make('status')->label(__('panel.agency.status'))->badge()->color(fn (mixed $state): string => PanelLabels::statusColor($state))->formatStateUsing(fn (mixed $state): string => PanelLabels::status($state)),
                    TextEntry::make('amount_minor')->label(__('panel.subscriptions.amount'))->formatStateUsing(fn (mixed $state, Subscription $record): string => PanelLabels::money((int) $state, $record->currency)),
                    TextEntry::make('billing')->label(__('panel.subscriptions.billing'))->formatStateUsing(fn (mixed $state): string => PanelLabels::billing($state)),
                    TextEntry::make('discount_type')->label(__('panel.subscriptions.discount'))->formatStateUsing(fn (mixed $state): string => PanelLabels::discount($state)),
                    TextEntry::make('usage')->label(__('panel.subscriptions.usage'))->getStateUsing(fn (Subscription $record): string => PanelLabels::seatUsage($record)),
                    TextEntry::make('cancel_at')->label(__('panel.subscriptions.cancel_at'))->date()->placeholder('—'),
                    TextEntry::make('complimentary_until')->label(__('panel.subscriptions.complimentary_until'))->date()->placeholder('—'),
                    TextEntry::make('paid_offline_until')->label(__('panel.subscriptions.paid_offline_until'))->date()->placeholder('—'),
                    TextEntry::make('stripe_status')->label(__('panel.subscriptions.stripe_status'))->placeholder('—'),
                    TextEntry::make('stripe_id')->label(__('panel.subscriptions.stripe_id'))->placeholder('—'),
                    TextEntry::make('attention')
                        ->label(__('panel.subscriptions.attention'))
                        ->getStateUsing(fn (Subscription $record): string => PanelLabels::attention($record) ?? __('panel.subscriptions.clear')),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('agency.name')->label(__('panel.people.agency'))->searchable()->sortable(),
                TextColumn::make('agency.country')->label(__('panel.agency.country'))->sortable(),
                TextColumn::make('agency.trade')->label(__('panel.agency.trade'))->badge()->color('primary')->formatStateUsing(fn (mixed $state): string => PanelLabels::trade($state)),
                TextColumn::make('plan')->label(__('panel.agency.plan'))->badge()->color('primary')->formatStateUsing(fn (mixed $state): string => PanelLabels::plan($state)),
                TextColumn::make('status')->label(__('panel.agency.status'))->badge()->color(fn (mixed $state): string => PanelLabels::statusColor($state))->formatStateUsing(fn (mixed $state): string => PanelLabels::status($state)),
                TextColumn::make('amount_minor')->label(__('panel.subscriptions.amount'))->formatStateUsing(fn (mixed $state, Subscription $record): string => PanelLabels::money((int) $state, $record->currency)),
                TextColumn::make('billing')->label(__('panel.subscriptions.billing'))->formatStateUsing(fn (mixed $state): string => PanelLabels::billing($state)),
                TextColumn::make('usage')->label(__('panel.subscriptions.usage'))->getStateUsing(fn (Subscription $record): string => PanelLabels::seatUsage($record)),
                TextColumn::make('attention')->label(__('panel.attention.reason'))->getStateUsing(fn (Subscription $record): string => PanelLabels::attention($record) ?? '—'),
                TextColumn::make('agency.ownerMembership.user.email')->label(__('panel.agency.owner_email'))->searchable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('panel.agency.status'))
                    ->options(fn (): array => collect(SubscriptionStatus::cases())->mapWithKeys(
                        fn (SubscriptionStatus $status): array => [$status->value => PanelLabels::status($status)],
                    )->all()),
                SelectFilter::make('plan')
                    ->label(__('panel.agency.plan'))
                    ->options(fn (): array => PanelLabels::plans()),
                SelectFilter::make('billing')
                    ->label(__('panel.subscriptions.billing'))
                    ->options(collect(BillingInterval::cases())->mapWithKeys(
                        fn (BillingInterval $billing): array => [$billing->value => PanelLabels::billing($billing)],
                    )->all()),
                Filter::make('attention')
                    ->label(__('panel.attention.only'))
                    ->query(fn (Builder $query): Builder => $query->needsAttention()),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptions::route('/'),
            'view' => ViewSubscription::route('/{record}'),
        ];
    }

    private static function seesRevenue(): bool
    {
        $user = auth('staff')->user();

        return $user instanceof User && $user->hasStaffPermission(StaffPermissionCode::ViewRevenue);
    }
}
