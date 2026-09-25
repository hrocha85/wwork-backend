<?php

namespace App\Actions\OneSignal;

use App\Enums\MembershipRole;
use App\Enums\Trade;
use App\Models\Visit;
use App\Support\RecordActivity;
use Illuminate\Support\Facades\Http;
use Throwable;

class NotifyJobFinished
{
    public function __invoke(Visit $visit, ?int $durationSeconds): void
    {
        $visit->loadMissing('client', 'agency', 'assignee.membership');
        $appId = config('wwork.onesignal_app_id');
        $key = config('wwork.onesignal_rest_key');

        if (! is_string($appId) || $appId === '' || ! is_string($key) || $key === '') {
            return;
        }

        $address = $visit->client->address;
        $owner = $visit->agency->memberships()->where('role', MembershipRole::Owner)->with('user')->first();
        $assigneeIsOwner = $visit->assignee?->membership?->role === MembershipRole::Owner;

        if (! $assigneeIsOwner && filled($owner?->user?->onesignal_player_id)) {
            $this->send($visit, $appId, $key, $owner->user->onesignal_player_id, 'Job finished', trim($address.' '.($durationSeconds === null ? '' : $durationSeconds.'s')));
        }

        if (filled($visit->client->onesignal_player_id)) {
            $this->send($visit, $appId, $key, $visit->client->onesignal_player_id, $this->houseTitle($visit->agency->trade), $address);
        }
    }

    private function send(Visit $visit, string $appId, string $key, string $playerId, string $title, string $body): void
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Key '.$key,
            ])->post('https://api.onesignal.com/notifications', [
                'app_id' => $appId,
                'include_player_ids' => [$playerId],
                'headings' => ['en' => $title],
                'contents' => ['en' => $body],
            ]);

            if ($response->failed()) {
                RecordActivity::add($visit->agency_id, $visit->assignee_id, 'onesignal.failed');
            }
        } catch (Throwable) {
            RecordActivity::add($visit->agency_id, $visit->assignee_id, 'onesignal.failed');
        }
    }

    private function houseTitle(Trade $trade): string
    {
        return match ($trade) {
            Trade::Lawn => 'Your lawn visit is done',
            Trade::Cleaning => 'Your clean is done',
            default => 'Your visit is done',
        };
    }
}
