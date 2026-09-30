<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OneSignalPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OneSignalPushTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'wwork.onesignal_app_id' => 'app-test-id',
            'wwork.onesignal_rest_key' => 'rest-test-key',
            'wwork.frontend_url' => 'https://app.test',
        ]);
    }

    public function test_sends_to_external_id_with_backend_key(): void
    {
        Http::fake([OneSignalPush::ENDPOINT => Http::response(['id' => 'notif-1'])]);
        $user = User::factory()->create();

        $result = app(OneSignalPush::class)->toUsers([$user], 'Title', 'Body', '/visits', '6f1c1c52-5d0e-4e53-9a3a-0d6f4c8f7a11');

        $this->assertTrue($result['sent']);
        $this->assertSame('notif-1', $result['id']);
        Http::assertSent(fn (Request $request) => $request->url() === OneSignalPush::ENDPOINT
            && $request->hasHeader('Authorization', 'Key rest-test-key')
            && $request['app_id'] === 'app-test-id'
            && $request['target_channel'] === 'push'
            && $request['include_aliases'] === ['external_id' => ['user-'.$user->id]]
            && $request['headings'] === ['en' => 'Title']
            && $request['contents'] === ['en' => 'Body']
            && $request['url'] === 'https://app.test/visits'
            && $request['idempotency_key'] === '6f1c1c52-5d0e-4e53-9a3a-0d6f4c8f7a11');
    }

    public function test_reports_unsubscribed_user_as_not_sent(): void
    {
        Http::fake([OneSignalPush::ENDPOINT => Http::response(['id' => '', 'errors' => ['invalid_aliases' => ['external_id' => ['user-1']]]])]);
        $user = User::factory()->create();

        $result = app(OneSignalPush::class)->toUsers([$user], 'Title', 'Body');

        $this->assertFalse($result['sent']);
        $this->assertNull($result['id']);
    }

    public function test_does_nothing_without_configuration(): void
    {
        config(['wwork.onesignal_rest_key' => null]);
        Http::fake();

        $result = app(OneSignalPush::class)->toUsers([User::factory()->create()], 'Title', 'Body');

        $this->assertFalse($result['sent']);
        Http::assertNothingSent();
    }

    public function test_push_test_command_never_prints_the_rest_key(): void
    {
        Http::fake([OneSignalPush::ENDPOINT => Http::response(['id' => 'notif-2'])]);
        $user = User::factory()->create(['email' => 'push@example.com']);

        $this->artisan('wwork:push-test', ['email' => 'push@example.com'])
            ->expectsOutput('ONESIGNAL_REST_API_KEY=(definida)')
            ->expectsOutput('external_id=user-'.$user->id)
            ->doesntExpectOutputToContain('rest-test-key')
            ->assertSuccessful();
    }
}
