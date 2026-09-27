<?php

namespace App\Filament\Widgets;

use App\Enums\StaffPermissionCode;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Filament\Support\PanelLabels;
use App\Models\Subscription;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionAttention extends TableWidget
{
    protected static ?int $sort = 4;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth('staff')->user();

        return $user instanceof User
            && $user->hasStaffPermission(StaffPermissionCode::ViewRevenue);
    }

    protected function getTableHeading(): string|Htmlable|null
    {
        return __('panel.attention.heading');
    }

    public function table(Table $table): Table
    {
        return $table
            ->description(__('panel.attention.help'))
            ->query(fn (): Builder => Subscription::query()
                ->needsAttention()
                ->with([
                    'agency' => function ($query) {
                        $query->withCount('memberships')->with('ownerMembership.user');
                    },
                ]))
            ->columns([
                TextColumn::make('agency.name')->label(__('panel.people.agency')),
                TextColumn::make('plan')->label(__('panel.agency.plan'))->badge()->color('primary')->formatStateUsing(fn (mixed $state): string => PanelLabels::plan($state)),
                TextColumn::make('status')->label(__('panel.agency.status'))->badge()->color(fn (mixed $state): string => PanelLabels::statusColor($state))->formatStateUsing(fn (mixed $state): string => PanelLabels::status($state)),
                TextColumn::make('amount_minor')->label(__('panel.subscriptions.amount'))->formatStateUsing(fn (mixed $state, Subscription $record): string => PanelLabels::money((int) $state, $record->currency)),
                TextColumn::make('usage')->label(__('panel.subscriptions.usage'))->getStateUsing(fn (Subscription $record): string => PanelLabels::seatUsage($record)),
                TextColumn::make('attention')->label(__('panel.attention.reason'))->getStateUsing(fn (Subscription $record): string => PanelLabels::attention($record) ?? '—'),
            ])
            ->recordUrl(fn (Subscription $record): string => SubscriptionResource::getUrl('view', ['record' => $record]))
            ->paginated(false)
            ->emptyStateHeading(__('panel.attention.empty'));
    }
}
