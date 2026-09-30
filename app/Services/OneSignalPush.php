<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends web push through OneSignal to users identified by External ID.
 * The front links each browser with OneSignal.login("user-{id}"), so no device ids are stored here.
 */
class OneSignalPush
{
    public const ENDPOINT = 'https://api.onesignal.com/notifications?c=push';

    public static function externalId(User $user): string
    {
        return 'user-'.$user->id;
    }

    public function configured(): bool
    {
        return filled(config('wwork.onesignal_app_id')) && filled(config('wwork.onesignal_rest_key'));
    }

    /**
     * @param  list<User>  $users
     * @param  string|null  $idempotencyKey  UUID; OneSignal drops repeats of the same key for 30 days.
     * @return array{sent: bool, status: int|null, id: string|null, errors: mixed}
     */
    public function toUsers(array $users, string $title, string $body, ?string $path = null, ?string $idempotencyKey = null): array
    {
        if (! $this->configured() || $users === []) {
            return ['sent' => false, 'status' => null, 'id' => null, 'errors' => 'not_configured_or_no_recipients'];
        }

        $payload = array_filter([
            'app_id' => config('wwork.onesignal_app_id'),
            'target_channel' => 'push',
            'include_aliases' => ['external_id' => array_values(array_map(self::externalId(...), $users))],
            'headings' => ['en' => $title],
            'contents' => ['en' => $body],
            'url' => $path === null ? null : config('wwork.frontend_url').'/'.ltrim($path, '/'),
            'idempotency_key' => $idempotencyKey,
        ], fn ($value) => $value !== null);

        try {
            $response = Http::withHeaders(['Authorization' => 'Key '.config('wwork.onesignal_rest_key')])
                ->acceptJson()
                ->timeout(10)
                ->post(self::ENDPOINT, $payload);
        } catch (Throwable $exception) {
            report($exception);

            return ['sent' => false, 'status' => null, 'id' => null, 'errors' => $exception->getMessage()];
        }

        $id = $response->json('id');
        $result = [
            'sent' => $response->successful() && filled($id),
            'status' => $response->status(),
            'id' => is_string($id) && $id !== '' ? $id : null,
            'errors' => $response->json('errors'),
        ];

        if (! $result['sent']) {
            Log::warning('push.failed', ['status' => $result['status'], 'errors' => $result['errors'], 'recipients' => $payload['include_aliases']['external_id']]);
        }

        return $result;
    }
}
