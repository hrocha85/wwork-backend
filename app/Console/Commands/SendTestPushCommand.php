<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\OneSignalPush;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SendTestPushCommand extends Command
{
    protected $signature = 'wwork:push-test {email : Usuário que recebe o push (precisa ter aberto o app e aceitado notificações)}';

    protected $description = 'Envia um push curto via OneSignal para validar ONESIGNAL_APP_ID e ONESIGNAL_REST_API_KEY';

    public function handle(OneSignalPush $push): int
    {
        $this->line('ONESIGNAL_APP_ID='.(config('wwork.onesignal_app_id') ?: '(vazio)'));
        $this->line('ONESIGNAL_REST_API_KEY='.(filled(config('wwork.onesignal_rest_key')) ? '(definida)' : '(vazio)'));

        if (! $push->configured()) {
            $this->error('OneSignal não configurado no .env. Rode php artisan config:clear depois de editar.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', (string) $this->argument('email'))->first();

        if ($user === null) {
            $this->error('Nenhum usuário com esse e-mail.');

            return self::FAILURE;
        }

        $this->line('external_id='.OneSignalPush::externalId($user));

        $result = $push->toUsers([$user], 'WWork', 'Teste de notificação. Se chegou, o push está funcionando.', '/', (string) Str::uuid());

        if (! $result['sent']) {
            $this->error('Não enviado. HTTP '.($result['status'] ?? '-').' '.json_encode($result['errors'], JSON_UNESCAPED_UNICODE));
            $this->line('Se aparecer invalid_aliases, esse usuário ainda não tem navegador inscrito: abra o app logado, aceite as notificações e tente de novo.');

            return self::FAILURE;
        }

        $this->info('Enviado. Notification id '.$result['id'].'. Confira o dispositivo e Delivery > Messages no painel.');

        return self::SUCCESS;
    }
}
