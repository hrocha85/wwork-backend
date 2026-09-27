<?php

namespace App\Filament\Resources\Agencies\Pages;

use App\Actions\Auth\InvitePaidOwner;
use App\Enums\StaffPermissionCode;
use App\Filament\Resources\Agencies\AgencyResource;
use App\Filament\Support\PanelLabels;
use App\Models\User;
use App\Support\ApiException;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ListAgencies extends ListRecords
{
    protected static string $resource = AgencyResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('panel.agency.list_help');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('inviteOwner')
                ->label(__('panel.invite.action'))
                ->icon(Heroicon::OutlinedUserPlus)
                ->modalIcon(Heroicon::OutlinedUserPlus)
                ->modalWidth(Width::Large)
                ->modalDescription(__('panel.invite.help'))
                ->visible(fn (): bool => self::canManagePlans())
                ->schema([
                    TextInput::make('agency_name')
                        ->label(__('panel.invite.agency'))
                        ->helperText(__('panel.invite.agency_help'))
                        ->required()
                        ->minLength(2)
                        ->maxLength(80),
                    Select::make('trade')
                        ->label(__('panel.invite.trade'))
                        ->helperText(__('panel.invite.trade_help'))
                        ->options(fn (): array => PanelLabels::trades())
                        ->native(false)
                        ->required(),
                    TextInput::make('name')
                        ->label(__('panel.invite.owner'))
                        ->helperText(__('panel.invite.owner_help'))
                        ->required()
                        ->maxLength(80),
                    TextInput::make('email')
                        ->label(__('panel.invite.email'))
                        ->helperText(__('panel.invite.email_help'))
                        ->email()
                        ->required()
                        ->maxLength(255),
                    TextInput::make('password')
                        ->label(__('panel.invite.password'))
                        ->helperText(__('panel.invite.password_help'))
                        ->password()
                        ->required()
                        ->minLength(8),
                    Select::make('plan')
                        ->label(__('panel.invite.plan'))
                        ->helperText(__('panel.invite.plan_help'))
                        ->options(fn (): array => PanelLabels::plans())
                        ->native(false)
                        ->required(),
                    DatePicker::make('until')->label(__('panel.invite.until'))->helperText(__('panel.agency.until_help')),
                ])
                ->action(function (array $data, Action $action): void {
                    try {
                        app(InvitePaidOwner::class)($data, auth('staff')->id());
                    } catch (ApiException $exception) {
                        Notification::make()->title($exception->error)->danger()->send();
                        $action->halt();
                    }

                    Notification::make()->title(__('panel.invite.sent'))->success()->send();
                }),
        ];
    }

    private static function canManagePlans(): bool
    {
        $user = auth('staff')->user();

        return $user instanceof User && $user->hasStaffPermission(StaffPermissionCode::ManagePlans);
    }
}
