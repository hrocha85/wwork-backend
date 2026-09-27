<?php

namespace App\Filament\Resources\Agencies;

use App\Enums\StaffPermissionCode;
use App\Enums\SubscriptionStatus;
use App\Filament\Resources\Agencies\Pages\ListAgencies;
use App\Filament\Resources\Agencies\Pages\ViewAgency;
use App\Filament\Support\PanelLabels;
use App\Models\Agency;
use App\Models\Subscription;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AgencyResource extends Resource
{
    protected static ?string $model = Agency::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('panel.nav.agencies');
    }

    public static function getModelLabel(): string
    {
        return __('panel.agency.one');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.nav.agencies');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('panel.nav.directory');
    }

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
            Section::make(__('panel.agency.section'))
                ->description(__('panel.agency.section_help'))
                ->schema([
                TextEntry::make('name')->label(__('panel.agency.name')),
                TextEntry::make('trade')->label(__('panel.agency.trade'))->badge()->color('primary')->formatStateUsing(fn (mixed $state): string => PanelLabels::trade($state)),
                TextEntry::make('country')->label(__('panel.agency.country')),
                TextEntry::make('invoice_region')->label(__('panel.agency.region')),
                TextEntry::make('timezone')->label(__('panel.agency.timezone')),
                TextEntry::make('currency')->label(__('panel.agency.currency')),
                TextEntry::make('campaign')
                    ->label(__('panel.agency.campaign'))
                    ->getStateUsing(fn (Agency $record): string => PanelLabels::campaign($record->utm_campaign, $record->utm_source)),
                TextEntry::make('ownerMembership.user.name')->label(__('panel.agency.owner')),
                TextEntry::make('ownerMembership.user.email')->label(__('panel.agency.owner_email')),
                TextEntry::make('subscription.plan')->label(__('panel.agency.plan'))->badge()->color('primary')->formatStateUsing(fn (mixed $state): string => PanelLabels::plan($state)),
                TextEntry::make('subscription.status')->label(__('panel.agency.status'))->badge()->color(fn (mixed $state): string => PanelLabels::statusColor($state))->formatStateUsing(fn (mixed $state): string => PanelLabels::status($state)),
                TextEntry::make('subscription.seats')->label(__('panel.agency.seats')),
                TextEntry::make('seat_usage')
                    ->label(__('panel.subscriptions.usage'))
                    ->getStateUsing(function (Agency $record): string {
                        $subscription = $record->subscription;
                        if (! $subscription instanceof Subscription) {
                            return '—';
                        }

                        $record->loadCount('memberships');
                        $subscription->setRelation('agency', $record);

                        return PanelLabels::seatUsage($subscription);
                    }),
                TextEntry::make('subscription.billing')->label(__('panel.subscriptions.billing'))->placeholder('—')->formatStateUsing(fn (mixed $state): string => $state === null ? '—' : PanelLabels::billing($state)),
                TextEntry::make('subscription.amount_minor')
                    ->label(__('panel.subscriptions.amount'))
                    ->visible(fn (): bool => static::staffCan(StaffPermissionCode::ViewRevenue))
                    ->formatStateUsing(function (mixed $state, Agency $record): string {
                        $currency = $record->subscription?->currency;
                        if ($state === null || ! is_string($currency) || $currency === '') {
                            return '—';
                        }

                        return PanelLabels::money((int) $state, $currency);
                    }),
                TextEntry::make('subscription.cancel_at')->label(__('panel.subscriptions.cancel_at'))->date()->placeholder('—'),
                TextEntry::make('subscription.complimentary_until')->label(__('panel.subscriptions.complimentary_until'))->date()->placeholder('—'),
                TextEntry::make('subscription.paid_offline_until')->label(__('panel.subscriptions.paid_offline_until'))->date()->placeholder('—'),
                TextEntry::make('memberships_count')
                    ->label(__('panel.agency.people'))
                    ->getStateUsing(fn (Agency $record): int => $record->memberships()->count()),
                TextEntry::make('visits_count')
                    ->label(__('panel.agency.visits'))
                    ->getStateUsing(fn (Agency $record): int => $record->visits()->count()),
                TextEntry::make('invoices_count')
                    ->label(__('panel.agency.invoices'))
                    ->getStateUsing(fn (Agency $record): int => $record->invoices()->count()),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('panel.agency.name'))->searchable()->sortable(),
                TextColumn::make('subscription.plan')->label(__('panel.agency.plan'))->badge()->color('primary')->formatStateUsing(fn (mixed $state): string => PanelLabels::plan($state))->placeholder('—'),
                TextColumn::make('trade')->label(__('panel.agency.trade'))->badge()->color('primary')->formatStateUsing(fn (mixed $state): string => PanelLabels::trade($state)),
                TextColumn::make('campaign')
                    ->label(__('panel.agency.campaign'))
                    ->getStateUsing(fn (Agency $record): string => PanelLabels::campaign($record->utm_campaign, $record->utm_source)),
                TextColumn::make('memberships_count')->label(__('panel.agency.people'))->counts('memberships'),
                TextColumn::make('subscription.status')->label(__('panel.agency.status'))->badge()->color(fn (mixed $state): string => PanelLabels::statusColor($state))->formatStateUsing(fn (mixed $state): string => PanelLabels::status($state))->placeholder('—'),
                TextColumn::make('created_at')->label(__('panel.agency.created'))->dateTime()->sortable(),
                TextColumn::make('ownerMembership.user.email')
                    ->label(__('panel.agency.owner_email'))
                    ->searchable(),
                TextColumn::make('ownerMembership.user.last_seen_at')
                    ->label(__('panel.agency.last_seen'))
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('trade')
                    ->label(__('panel.agency.trade'))
                    ->options(fn (): array => PanelLabels::trades()),
                SelectFilter::make('plan')
                    ->label(__('panel.agency.plan'))
                    ->options(fn (): array => PanelLabels::plans())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, string $plan): Builder => $query->whereHas(
                            'subscription',
                            fn (Builder $query): Builder => $query->where('plan', $plan),
                        ),
                    )),
                SelectFilter::make('status')
                    ->label(__('panel.agency.status'))
                    ->options([
                        SubscriptionStatus::Active->value => __('panel.status.active'),
                        SubscriptionStatus::PastDue->value => __('panel.status.past_due'),
                        SubscriptionStatus::Complimentary->value => __('panel.status.complimentary'),
                        SubscriptionStatus::PaidOffline->value => __('panel.status.paid_offline'),
                        SubscriptionStatus::Cancelled->value => __('panel.status.cancelled'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, string $status): Builder => $query->whereHas(
                            'subscription',
                            fn (Builder $query): Builder => $query->where('status', $status),
                        ),
                    )),
                SelectFilter::make('campaign')
                    ->label(__('panel.agency.campaign'))
                    ->options(fn (): array => ['Direct' => __('panel.campaign.direct')])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        if (blank($value)) {
                            return $query;
                        }
                        if ($value === 'Direct') {
                            return $query
                                ->where(fn (Builder $query): Builder => $query->whereNull('utm_campaign')->orWhere('utm_campaign', ''))
                                ->where(fn (Builder $query): Builder => $query->whereNull('utm_source')->orWhere('utm_source', ''));
                        }

                        return $query->where(
                            fn (Builder $query): Builder => $query->where('utm_campaign', $value)->orWhere('utm_source', $value),
                        );
                    }),
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
