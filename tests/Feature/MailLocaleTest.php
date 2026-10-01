<?php

namespace Tests\Feature;

use App\Enums\Locale;
use App\Mail\PartnerInvited;
use App\Mail\PasswordChangedMail;
use App\Mail\VisitAnsweredMail;
use App\Mail\VisitOfferedMail;
use App\Models\Client;
use App\Models\User;
use App\Support\MailNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Garante que o idioma do e-mail é sempre o do destinatário.
 *
 * A cobertura dos textos em si (render de todo Mailable em todo idioma) está em
 * NotificationMailTest::test_every_notice_renders_in_every_locale_without_raw_keys.
 */
class MailLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Carbon::setTestNow(Carbon::parse('2026-09-28 08:00:00', 'Europe/London'));
        $this->withHeader('referer', config('app.url'));
        $this->withHeader('Accept', 'application/json');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_every_mail_key_and_placeholder_exists_in_every_supported_locale(): void
    {
        $catalogues = [];

        foreach (Locale::cases() as $case) {
            $file = lang_path($case->value.'/mail.php');
            $this->assertFileExists($file, 'Catálogo de e-mail ausente para o idioma '.$case->value);

            $catalogues[$case->value] = $this->flatten(include $file);
        }

        $keys = [];
        foreach ($catalogues as $translations) {
            $keys = array_unique(array_merge($keys, array_keys($translations)));
        }
        sort($keys);

        $this->assertNotSame([], $keys);

        foreach ($catalogues as $locale => $translations) {
            $missing = array_values(array_diff($keys, array_keys($translations)));

            $this->assertSame([], $missing, 'Chaves faltando em lang/'.$locale.'/mail.php: '.implode(', ', $missing));
        }

        // Uma chave com placeholder diferente em outro idioma deixaria a variável
        // sem preencher: o dado dinâmico teria que continuar igual em todos.
        foreach ($keys as $key) {
            $placeholders = [];

            foreach ($catalogues as $locale => $translations) {
                preg_match_all('/:[a-zA-Z_]+/', (string) $translations[$key], $matches);
                sort($matches[0]);
                $placeholders[$locale] = implode(',', $matches[0]);
            }

            $this->assertCount(
                1,
                array_unique($placeholders),
                'Placeholders divergentes para '.$key.': '.json_encode($placeholders, JSON_UNESCAPED_UNICODE),
            );
        }
    }

    public function test_mail_is_delivered_in_the_recipients_language_not_the_actors(): void
    {
        Mail::fake();

        $owner = $this->user('owner@wwork.test');
        $partner = $this->user('invited@wwork.test');

        $owner->forceFill(['locale' => Locale::En])->save();
        $partner->forceFill(['locale' => Locale::Pt])->save();

        // Quem age fala inglês; quem recebe fala português.
        $this->login('owner@wwork.test');
        $created = $this->postJson('/api/v1/visits', $this->visitBody($this->house($owner), $partner->id))->assertCreated();

        Mail::assertSent(VisitOfferedMail::class, function (VisitOfferedMail $mail): bool {
            $html = $mail->render();

            return $mail->locale === 'pt'
                && $mail->hasTo('invited@wwork.test')
                && str_contains($html, 'Você tem um serviço novo para aceitar')
                && ! str_contains($html, 'You have a new job to accept');
        });

        // Agora quem age fala português e quem recebe fala inglês.
        $this->postJson('/api/v1/logout')->assertNoContent();
        $this->login('invited@wwork.test');
        $this->postJson('/api/v1/visits/'.$created->json('id').'/accept')->assertOk();

        Mail::assertSent(VisitAnsweredMail::class, function (VisitAnsweredMail $mail): bool {
            $html = $mail->render();

            return $mail->locale === 'en'
                && $mail->hasTo('owner@wwork.test')
                && str_contains($html, 'Job accepted')
                && ! str_contains($html, 'Serviço aceito');
        });
    }

    public function test_invite_mail_uses_the_system_language_because_the_invitee_has_no_account(): void
    {
        Mail::fake();

        $owner = $this->user('owner@wwork.test');
        $owner->forceFill(['locale' => Locale::Pt])->save();

        $this->login('owner@wwork.test');
        $this->postJson('/api/v1/partners', ['email' => 'novo.parceiro@wwork.test', 'rate' => 50])->assertCreated();

        Mail::assertSent(PartnerInvited::class, function (PartnerInvited $mail): bool {
            $html = $mail->render();

            return $mail->locale === config('app.locale')
                && $mail->locale !== 'pt'
                && $mail->hasTo('novo.parceiro@wwork.test')
                && str_contains($html, 'Welcome to WWork')
                && ! str_contains($html, 'Boas-vindas ao WWork');
        });
    }

    public function test_mail_falls_back_to_the_system_language_when_the_locale_is_missing_or_invalid(): void
    {
        Mail::fake();

        $owner = $this->user('owner@wwork.test');

        // Conta sem idioma definido.
        $owner->forceFill(['locale' => null]);
        app(MailNotifier::class)->toUser('test.locale_missing', $owner, new PasswordChangedMail($owner->name));

        // Valor gravado direto na coluna, fora do enum (o cast do modelo lançaria).
        $attributes = $owner->getAttributes();
        $attributes['locale'] = 'pt-BR';
        $owner->setRawAttributes($attributes);
        app(MailNotifier::class)->toUser('test.locale_invalid', $owner, new PasswordChangedMail($owner->name));

        $sent = [];
        Mail::assertSent(PasswordChangedMail::class, function (PasswordChangedMail $mail) use (&$sent): bool {
            $sent[] = [$mail->locale, $mail->render()];

            return true;
        });

        $this->assertCount(2, $sent);

        foreach ($sent as [$locale, $html]) {
            $this->assertSame(config('app.locale'), $locale);
            $this->assertDoesNotMatchRegularExpression('/\bmail\.[a-z_]+\.[a-z_]+/', $html);
            $this->assertStringContainsString('Your WWork password was changed', $html);
        }
    }

    private function login(string $email): void
    {
        $this->postJson('/api/v1/login', ['email' => $email, 'password' => 'demo-seed-test'])->assertOk();
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    private function house(User $owner): int
    {
        return Client::query()->create([
            'agency_id' => $owner->membership->agency_id,
            'created_by' => $owner->id,
            'name' => 'John Smith',
            'phone' => '+447911123456',
            'address' => '10 Downing Street, London',
            'lat' => 51.5034,
            'lng' => -0.1276,
        ])->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function visitBody(int $client, int $assignee): array
    {
        return [
            'client_id' => $client,
            'date' => '2026-09-28',
            'time' => '09:00',
            'price_pence' => 8000,
            'assignee_id' => $assignee,
            'lat' => 51.5,
            'lng' => -0.1,
            'goals' => [['text' => 'Clean kitchen']],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function flatten(array $translations, string $prefix = ''): array
    {
        $flat = [];

        foreach ($translations as $key => $value) {
            $path = $prefix === '' ? $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $flat += $this->flatten($value, $path);
            } else {
                $flat[$path] = $value;
            }
        }

        return $flat;
    }
}
