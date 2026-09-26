<?php

namespace App\Filament\Auth;

use App\Filament\Pages\Dashboard;
use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;

class Login extends BaseLogin
{
    public function mount(): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended(Dashboard::getUrl());
        }

        $this->form->fill();
    }

    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()
            ->extraAttributes(['class' => 'wwork-sign-in'], merge: true);
    }
}
