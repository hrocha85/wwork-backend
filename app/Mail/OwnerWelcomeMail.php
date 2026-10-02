<?php

namespace App\Mail;

/**
 * Boas-vindas do dono. Cadastro pelo app: sem senha no corpo.
 * Dono criado no painel: leva a senha temporária e o aviso de troca no primeiro acesso.
 *
 * Estrutura em quatro blocos: boas-vindas, tutorial de primeiro acesso
 * (textos já aprovados, preservados), benefícios reais da assinatura e
 * mensagem final. Sai no idioma do destinatário (MailNotifier).
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
                // 1. Boas-vindas
                __('mail.welcome.opening', ['agency' => $this->agencyName]),
                // 2. Tutorial de primeiro acesso (textos existentes, preservados)
                __('mail.welcome.body', ['agency' => $this->agencyName]),
                $offline ? __('mail.welcome.temporary') : __('mail.welcome.next'),
                // 3. Benefícios de ser assinante (somente recursos existentes)
                __('mail.welcome.benefits_title'),
                __('mail.welcome.benefit_agenda'),
                __('mail.welcome.benefit_team'),
                __('mail.welcome.benefit_field'),
                __('mail.welcome.benefit_invoices'),
                // 4. Mensagem final
                __('mail.welcome.closing'),
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
