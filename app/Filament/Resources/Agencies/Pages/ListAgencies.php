<?php

namespace App\Filament\Resources\Agencies\Pages;

use App\Actions\Auth\InvitePaidOwner;
use App\Enums\PlanCode;
use App\Enums\StaffPermissionCode;
use App\Enums\Trade;
use App\Filament\Resources\Agencies\AgencyResource;
use App\Models\User;
use App\Support\ApiException;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAgencies extends ListRecords
{
    protected static string $resource = AgencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('inviteOwner')
                ->label('Convidar dono')
                ->visible(fn (): bool => self::canManagePlans())
                ->schema([
                    TextInput::make('agency_name')->label('Nome da agência')->required()->minLength(2)->maxLength(80),
                    Select::make('trade')
                        ->label('Ofício')
                        ->options(collect(Trade::cases())->mapWithKeys(
                            fn (Trade $trade): array => [$trade->value => $trade->value],
                        )->all())
                        ->required(),
                    TextInput::make('name')->label('Nome do dono')->required()->maxLength(80),
                    TextInput::make('email')->label('E-mail')->email()->required()->maxLength(255),
                    TextInput::make('password')->label('Senha temporária')->password()->required()->minLength(8),
                    Select::make('plan')
                        ->label('Plano')
                        ->options(collect(PlanCode::cases())->mapWithKeys(
                            fn (PlanCode $plan): array => [$plan->value => $plan->name],
                        )->all())
                        ->required(),
                    DatePicker::make('until')->label('Até quando'),
                ])
                ->action(function (array $data, Action $action): void {
                    try {
                        app(InvitePaidOwner::class)($data, auth('staff')->id());
                    } catch (ApiException $exception) {
                        Notification::make()->title($exception->error)->danger()->send();
                        $action->halt();
                    }

                    Notification::make()->title('Convite enviado')->success()->send();
                }),
        ];
    }

    private static function canManagePlans(): bool
    {
        $user = auth('staff')->user();

        return $user instanceof User && $user->hasStaffPermission(StaffPermissionCode::ManagePlans);
    }
}
