<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * O caminho real de autenticação do PWA: cookie de sessão entre host, com
 * Origin em outro lugar, e não token Bearer no localStorage.
 *
 * Os três pilares estão aqui: CORS com credenciais para a origem do PWA, o
 * cookie de sessão saindo do login e voltando no /me, e o cookie valendo sem
 * nenhum cabeçalho de token.
 */
class CookieSessionTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function pwaOrigin(): array
    {
        return ['Origin' => self::PWA_ORIGIN];
    }

    public function test_preflight_cors_aceita_a_origem_do_pwa_com_credenciais(): void
    {
        $response = $this->call(
            'OPTIONS',
            '/api/v1/login',
            [],
            [],
            [],
            [
                'HTTP_ORIGIN' => self::PWA_ORIGIN,
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            ],
        );

        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', self::PWA_ORIGIN);
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_login_devolve_cookie_e_cabecalhos_cors_para_o_pwa(): void
    {
        User::factory()->owner()->create([
            'email' => 'lider@wwork.test',
            'password' => Hash::make('secret-pass'),
        ]);

        $this->withCredentials();

        $response = $this->postJson('/api/v1/login', [
            'email' => 'lider@wwork.test',
            'password' => 'secret-pass',
        ], $this->pwaOrigin());

        $response->assertOk();
        $response->assertHeader('Access-Control-Allow-Origin', self::PWA_ORIGIN);
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
        $response->assertCookie(config('session.cookie'));
    }

    public function test_cookie_do_login_autentica_o_me_sem_token_nenhum(): void
    {
        $owner = User::factory()->owner()->create([
            'email' => 'lider@wwork.test',
            'password' => Hash::make('secret-pass'),
        ]);

        $this->withCredentials();

        $login = $this->postJson('/api/v1/login', [
            'email' => 'lider@wwork.test',
            'password' => 'secret-pass',
        ], $this->pwaOrigin());

        $login->assertOk();

        $sessionCookie = $login->getCookie(config('session.cookie'));
        $this->assertNotNull($sessionCookie);

        // Processo novo, como na máquina do usuário: só o cookie sobrevive.
        $this->app['auth']->forgetGuards();
        $this->withCookie(config('session.cookie'), $sessionCookie->getValue());

        $this->getJson('/api/v1/me', $this->pwaOrigin())
            ->assertOk()
            ->assertJsonPath('data.id', $owner->id)
            ->assertJsonPath('data.role', 'owner');
    }

    public function test_origem_nao_listada_nao_recebe_cabecalhos_cors(): void
    {
        $response = $this->call(
            'OPTIONS',
            '/api/v1/login',
            [],
            [],
            [],
            [
                'HTTP_ORIGIN' => 'http://evil.test',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            ],
        );

        $response->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}
