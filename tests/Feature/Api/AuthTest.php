<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function owner(string $password = 'secret-pass'): User
    {
        return User::factory()->owner()->create([
            'email' => 'lider@wwork.test',
            'password' => Hash::make($password),
        ]);
    }

    public function test_login_com_credencial_valida_abre_a_sessao(): void
    {
        $owner = $this->owner();

        $this->postJson('/api/v1/login', [
            'email' => 'lider@wwork.test',
            'password' => 'secret-pass',
        ], $this->pwaHeaders())
            ->assertOk()
            ->assertJsonPath('data.id', $owner->id)
            ->assertJsonPath('data.role', 'owner')
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonMissingPath('data.password');

        $this->assertAuthenticatedAs($owner->fresh());
    }

    public function test_login_com_credencial_invalida_nao_autentica(): void
    {
        $this->owner();

        $this->postJson('/api/v1/login', [
            'email' => 'lider@wwork.test',
            'password' => 'senha-errada',
        ], $this->pwaHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'auth.invalid_credentials');

        $this->assertGuest();
    }

    public function test_login_exige_email_e_senha(): void
    {
        $this->postJson('/api/v1/login', [], $this->pwaHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_atualiza_last_seen_at(): void
    {
        $owner = $this->owner();
        $this->assertNull($owner->last_seen_at);

        $this->postJson('/api/v1/login', [
            'email' => 'lider@wwork.test',
            'password' => 'secret-pass',
        ], $this->pwaHeaders())->assertOk();

        $this->assertNotNull($owner->fresh()->last_seen_at);
    }

    public function test_me_devolve_o_usuario_e_o_papel(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->getJson('/api/v1/me', $this->pwaHeaders())
            ->assertOk()
            ->assertJsonPath('data.id', $owner->id)
            ->assertJsonPath('data.email', 'lider@wwork.test')
            ->assertJsonPath('data.role', 'owner')
            ->assertJsonPath('data.sync_uuid', $owner->sync_uuid);
    }

    public function test_me_sem_sessao_devolve_401(): void
    {
        $this->getJson('/api/v1/me', $this->pwaHeaders())
            ->assertStatus(401)
            ->assertJsonPath('message', 'auth.unauthenticated');
    }

    public function test_logout_encerra_a_sessao(): void
    {
        $this->owner();

        // withCredentials é o que faz o postJson carregar cookies: sem ele o
        // navegador PWA e o teste andariam por caminhos diferentes.
        $this->withCredentials();

        $login = $this->postJson('/api/v1/login', [
            'email' => 'lider@wwork.test',
            'password' => 'secret-pass',
        ], $this->pwaHeaders());

        $login->assertOk();

        $sessionCookie = $login->getCookie(config('session.cookie'));
        $this->assertNotNull($sessionCookie, 'O login precisa devolver o cookie de sessão.');

        // Em produção cada request é um processo novo e o AuthManager não
        // herda o guard da anterior. Sem esquecer os guards aqui, o guard do
        // Sanctum reutilizado seguiria enxergando o usuário da request de
        // login e mascararia uma sessão mal encerrada.
        $this->app['auth']->forgetGuards();

        // O navegador guarda o cookie do login e o reenvia sozinho, como o
        // PWA faz entre app. e api.. O logout tem que valer com ele.
        $this->withCookie(config('session.cookie'), $sessionCookie->getValue());

        $this->postJson('/api/v1/logout', [], $this->pwaHeaders())
            ->assertOk()
            ->assertJsonPath('status', 'logged_out');

        $this->app['auth']->forgetGuards();

        $this->assertGuest();

        $this->getJson('/api/v1/me', $this->pwaHeaders())->assertStatus(401);
    }

    public function test_o_convidado_tambem_entra_e_recebe_o_proprio_papel(): void
    {
        $invited = User::factory()->invited()->create([
            'email' => 'parceiro@wwork.test',
            'password' => Hash::make('secret-pass'),
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'parceiro@wwork.test',
            'password' => 'secret-pass',
        ], $this->pwaHeaders())
            ->assertOk()
            ->assertJsonPath('data.role', 'invited');

        $this->assertAuthenticatedAs($invited->fresh());
    }
}
