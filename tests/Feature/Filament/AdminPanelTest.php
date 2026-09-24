<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O /admin é do fundador, e só dele.
 *
 * O corte mora em User::canAccessPanel(): owner (o Líder Exausto) e invited
 * recebem 403 mesmo autenticados, e o visitante cai no login do Filament.
 */
class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_founder_entra_no_admin_e_vê_o_painel_vazio(): void
    {
        $founder = User::factory()->founder()->create();

        $this->actingAs($founder)
            ->get('/admin')
            ->assertOk();
    }

    public function test_owner_recebe_403_no_admin(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_invited_recebe_403_no_admin(): void
    {
        $invited = User::factory()->invited()->create();

        $this->actingAs($invited)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_visitante_e_redirecionado_para_o_login_do_filament(): void
    {
        $this->get('/admin')
            ->assertRedirect('/admin/login');
    }

    public function test_founder_abre_a_tela_de_login_do_filament(): void
    {
        User::factory()->founder()->create();

        $this->get('/admin/login')->assertOk();
    }

    public function test_owner_nao_consegue_usar_o_login_do_filament_para_entrar(): void
    {
        $owner = User::factory()->owner()->create();

        // O Filament expulsa quem já tem sessão da tela de login, e o
        // destino — /admin — responde 403 para o dono do perfil. Em nenhum
        // ponto ele chega a ver o painel.
        $this->actingAs($owner)
            ->followingRedirects()
            ->get('/admin/login')
            ->assertForbidden();
    }
}
