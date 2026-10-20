<?php

namespace App\Console\Commands;

use App\Actions\Marketplace\GuestVisitAccess;
use App\Mail\ProfileInviteMail;
use App\Models\Client;
use App\Models\Visit;
use App\Support\MailNotifier;
use Illuminate\Console\Command;

class SendProfileInvites extends Command
{
    protected $signature = 'clients:send-profile-invites';

    protected $description = 'Convida o cliente lead a criar o perfil aos 7 e 30 dias do último convite.';

    public function handle(): int
    {
        $sent = 0;

        foreach ([[1, 7, 2], [2, 30, 3]] as [$fromStage, $days, $toStage]) {
            $clients = Client::query()
                ->whereNull('user_id')
                ->whereNull('profile_invite_declined_at')
                ->whereNotNull('email')
                ->where('profile_invite_stage', $fromStage)
                ->where('profile_invite_last_at', '<=', now()->subDays($days))
                ->with('agency.subscription')
                ->get();

            foreach ($clients as $client) {
                if (! $client->agency?->hasActiveSubscription()) {
                    continue;
                }
                $visit = Visit::query()->where('client_id', $client->id)->orderByDesc('id')->first();
                if ($visit === null) {
                    continue;
                }
                $url = app(GuestVisitAccess::class)->url($visit);
                app(MailNotifier::class)->send('client.profile_invite', (string) $client->email, null, new ProfileInviteMail($client, $url), $client->agency_id);
                $client->forceFill(['profile_invite_stage' => $toStage, 'profile_invite_last_at' => now()])->save();
                $sent++;
            }
        }

        $this->info("Invites sent: {$sent}");

        return self::SUCCESS;
    }
}
