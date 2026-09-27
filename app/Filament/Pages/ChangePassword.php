<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ChangePassword extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'change-password';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function getTitle(): string
    {
        return __('panel.password.title');
    }

    public function getSubheading(): ?string
    {
        return __('panel.account.password_help');
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return ['wwork-form-page'];
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('password')
                    ->label(__('panel.password.new'))
                    ->helperText(__('panel.password.new_help'))
                    ->password()
                    ->revealable()
                    ->required()
                    ->minLength(8)
                    ->confirmed()
                    ->autocomplete('new-password'),
                TextInput::make('password_confirmation')
                    ->label(__('panel.password.confirm'))
                    ->helperText(__('panel.password.confirm_help'))
                    ->password()
                    ->revealable()
                    ->required()
                    ->autocomplete('new-password'),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('back')
                            ->label(__('panel.account.back'))
                            ->icon(Heroicon::OutlinedArrowLeft)
                            ->url(Dashboard::getUrl())
                            ->color('gray'),
                        Action::make('save')
                            ->label(__('panel.account.save'))
                            ->icon(Heroicon::OutlinedCheck)
                            ->color('primary')
                            ->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        /** @var User $user */
        $user = auth('staff')->user();
        $user->password = $state['password'];
        $user->must_change_password = false;
        $user->save();

        $guard = auth('staff');
        $hash = $guard->hashPasswordForCookie($user->getAuthPassword());
        foreach (array_unique(['staff', auth()->getDefaultDriver()]) as $driver) {
            $key = 'password_hash_'.$driver;
            if (session()->has($key)) {
                session()->put($key, $hash);
            }
        }

        $this->redirect(Dashboard::getUrl());
    }
}
