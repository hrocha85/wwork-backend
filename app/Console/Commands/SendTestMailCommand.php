<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestMailCommand extends Command
{
    protected $signature = 'wwork:mail-test {email : Caixa que recebe o teste}';

    protected $description = 'Envia um e-mail curto para validar o SMTP configurado no .env';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $mailer = (string) config('mail.default');

        $this->line('MAIL_MAILER='.$mailer);
        $this->line('MAIL_HOST='.config('mail.mailers.smtp.host'));
        $this->line('MAIL_PORT='.config('mail.mailers.smtp.port'));
        $this->line('MAIL_SCHEME='.(config('mail.mailers.smtp.scheme') ?: '(vazio, STARTTLS na 587)'));
        $this->line('MAIL_USERNAME='.(config('mail.mailers.smtp.username') ?: '(vazio)'));
        $this->line('MAIL_FROM='.config('mail.from.name').' <'.config('mail.from.address').'>');

        if ($mailer !== 'smtp') {
            $this->warn('MAIL_MAILER não é smtp. Nada sai para a caixa real.');
        }

        try {
            Mail::raw(
                'Teste do WWork. Se esta mensagem chegou, o SMTP da API está funcionando.',
                fn ($message) => $message->to($email)->subject('WWork SMTP test'),
            );
        } catch (\Throwable $exception) {
            $this->error('Falhou: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Enviado para '.$email.'. Confira a caixa de entrada e o spam.');

        return self::SUCCESS;
    }
}
