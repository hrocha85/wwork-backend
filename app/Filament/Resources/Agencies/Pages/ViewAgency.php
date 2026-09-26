<?php

namespace App\Filament\Resources\Agencies\Pages;

use App\Actions\Auth\InvitePaidOwner;
use App\Actions\Subscription\AssignPlan;
use App\Enums\PlanCode;
use App\Enums\StaffPermissionCode;
use App\Enums\SubscriptionStatus;
use App\Filament\Resources\Agencies\AgencyResource;
use App\Models\Agency;
use App\Models\User;
use App\Support\ApiException;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;

class ViewAgency extends ViewRecord
{
    protected static string $resource = AgencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('assignPlan')
                ->label('Atribuir plano')
                ->visible(fn (): bool => self::canManagePlans())
                ->schema([
                    Select::make('plan')
                        ->label('Plano')
                        ->options(collect(PlanCode::cases())->mapWithKeys(
                            fn (PlanCode $plan): array => [$plan->value => $plan->name],
                        )->all())
                        ->required(),
                    Select::make('reason')
                        ->label('Motivo')
                        ->options([
                            'pay' => 'Pagou a diferença',
                            'complimentary' => 'Cortesia',
                            'correction' => 'Correção',
                            'paid_offline' => 'Pago fora',
                        ])
                        ->required()
                        ->live(),
                    DatePicker::make('until')
                        ->label('Até quando')
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

                    Notification::make()->title('Plano atribuído')->success()->send();
                }),
            Action::make('resendWelcome')
                ->label('Reenviar boas-vindas')
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
                        ->title('Nova senha temporária')
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
