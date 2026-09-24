<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * O primeiro fundador, para o /admin abrir na máquina local.
 *
 * E-mail e senha saem do .env. Sem eles o seeder não cria ninguém: credencial
 * de acesso ao painel não entra no git.
 */
class FounderSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('wwork.founder.email');
        $password = config('wwork.founder.password');

        if (blank($email) || blank($password)) {
            $this->command?->warn(
                'FounderSeeder: defina WWORK_FOUNDER_EMAIL e WWORK_FOUNDER_PASSWORD no .env.'
            );

            return;
        }

        $founder = User::withTrashed()->firstOrNew(['email' => $email]);

        $founder->fill([
            'name' => config('wwork.founder.name'),
            'password' => $password,
            'role' => UserRole::Founder,
            'locale' => 'en',
        ]);
        $founder->deleted_at = null;
        $founder->email_verified_at ??= now();
        $founder->save();

        $this->command?->info("FounderSeeder: fundador pronto em {$email}.");
    }
}
