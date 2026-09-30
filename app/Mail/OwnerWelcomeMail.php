<?php

namespace App\Mail;

/**
 * Boas-vindas do dono. Cadastro pelo app: sem senha no corpo.
 * Dono criado no painel: leva a senha temporária e o aviso de troca no primeiro acesso.
 */
class OwnerWelcomeMail extends NoticeMail
{
    public function __construct(
        public string $ownerName,
        public string $agencyName,
        public string $emailAddress,
        public ?string $temporaryPassword = null,
    ) {}

    protected function subjectLine(): string
    {
        return __('mail.welcome.subject');
    }

    protected function notice(): array
    {
        $offline = $this->temporaryPassword !== null;

        return [
            'heading' => __('mail.welcome.heading', ['name' => $this->ownerName]),
            'lines' => [
                __('mail.welcome.body', ['agency' => $this->agencyName]),
                $offline ? __('mail.welcome.temporary') : __('mail.welcome.next'),
            ],
            'details' => [
                __('mail.common.agency') => $this->agencyName,
                __('mail.common.email') => $this->emailAddress,
                __('mail.welcome.password') => $this->temporaryPassword,
            ],
            'button' => ['label' => __('mail.welcome.button'), 'url' => self::app('/login')],
        ];
    }
}
