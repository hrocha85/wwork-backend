<?php

namespace App\Filament\Resources\Agencies\Pages;

use App\Actions\Auth\InvitePaidOwner;
use App\Actions\Subscription\AssignPlan;
use App\Enums\StaffPermissionCode;
use App\Enums\SubscriptionStatus;
use App\Filament\Resources\Agencies\AgencyResource;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Filament\Support\PanelLabels;
use App\Models\Agency;
use App\Models\User;
use App\Support\ApiException;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ViewAgency extends ViewRecord
{
    protected static string $resource = AgencyResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('panel.agency.view_help');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('subscription')
                ->label(__('panel.nav.subscriptions'))
                ->icon(Heroicon::OutlinedBanknotes)
                ->visible(function (): bool {
                    $record = $this->getRecord();

                    return $record instanceof Agency
                        && $record->subscription !== null
                        && self::canManagePlans();
                })
                ->url(function (): ?string {
                    $record = $this->getRecord();
                    if (! $record instanceof Agency || $record->subscription === null) {
                        return null;
                    }

                    return SubscriptionResource::getUrl('view', ['record' => $record->subscription]);
                }),
            Action::make('assignPlan')
                ->label(__('panel.agency.assign'))
                ->icon(Heroicon::OutlinedCreditCard)
                ->modalIcon(Heroicon::OutlinedCreditCard)
                ->modalWidth(Width::Large)
                ->modalDescription(__('panel.agency.assign_help'))
                ->visible(fn (): bool => self::canManagePlans())
                ->schema([
                    Select::make('plan')
                        ->label(__('panel.agency.plan'))
                        ->helperText(__('panel.invite.plan_help'))
                        ->options(fn (): array => PanelLabels::plans())
                        ->native(false)
                        ->required(),
                    Select::make('reason')
                        ->label(__('panel.agency.reason'))
                        ->helperText(__('panel.agency.reason_help'))
                        ->options([
                            'pay' => __('panel.reason.pay'),
                            'complimentary' => __('panel.reason.complimentary'),
                            'correction' => __('panel.reason.correction'),
                            'paid_offline' => __('panel.reason.paid_offline'),
                        ])
                        ->native(false)
                        ->required()
                        ->live(),
                    DatePicker::make('until')
                        ->label(__('panel.agency.until'))
                        ->helperText(__('panel.agency.until_help'))
                        ->visible(fn (Get $get): bool => in_array($get('reason'), ['complimentary', 'paid_offline'], true))
                        ->required(fn (Get $get): bool => $get('reason') === 'complimentary'),
                ])
                ->action(function (array $data, Action $action): void {
                    $record = $this->getRecord();
                    if (! $record instanceof Agency) {
                        return;
                    }

                    try {
                        app(AssignPlan::class)($record, $data, auth('staff')->id());
                    } catch (ApiException $exception) {
                        Notification::make()->title($exception->error)->danger()->send();
                        $action->halt();
                    }

                    Notification::make()->title(__('panel.agency.assigned'))->success()->send();
                }),
            Action::make('resendWelcome')
                ->label(__('panel.agency.resend'))
                ->modalDescription(__('panel.agency.resend_help'))
                ->visible(function (): bool {
                    $record = $this->getRecord();

                    return $record instanceof Agency
                        && $record->subscription?->status === SubscriptionStatus::PaidOffline
                        && self::canManagePlans();
                })
                ->requiresConfirmation()
                ->action(function (): void {
                    $record = $this->getRecord();
                    if (! $record instanceof Agency) {
                        return;
                    }

                    $password = app(InvitePaidOwner::class)->resend($record, auth('staff')->id());
                    Notification::make()
                        ->title(__('panel.agency.temp_password'))
                        ->body($password)
                        ->success()
                        ->send();
                }),
        ];
    }

    private static function canManagePlans(): bool
    {
        $user = auth('staff')->user();

        return $user instanceof User && $user->hasStaffPermission(StaffPermissionCode::ManagePlans);
    }
}
