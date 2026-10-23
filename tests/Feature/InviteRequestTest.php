<?php

namespace Tests\Feature;

use App\Models\InviteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InviteRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_request_invite_with_valid_email(): void
    {
        $response = $this->postJson('/api/v1/invites/request', [
            'email' => 'novato@exemplo.com',
            'locale' => 'pt',
        ]);

        $response->assertCreated();
        $response->assertJson([
            'ok' => true,
            'already_registered' => false,
            'already_user' => false,
        ]);

        $this->assertDatabaseHas('invite_requests', [
            'email' => 'novato@exemplo.com',
            'locale' => 'pt',
            'status' => 'pending',
        ]);
    }

    public function test_can_request_invite_with_trade(): void
    {
        $response = $this->postJson('/api/v1/invites/request', [
            'email' => 'profissional@exemplo.com',
            'locale' => 'pt',
            'trade' => 'Limpeza e Pintura',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('invite_requests', [
            'email' => 'profissional@exemplo.com',
            'notes' => 'Limpeza e Pintura',
        ]);
    }

    public function test_validates_invalid_email_format(): void
    {
        $response = $this->postJson('/api/v1/invites/request', [
            'email' => 'email-invalido',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_returns_friendly_message_for_duplicate_request(): void
    {
        InviteRequest::create([
            'email' => 'repetido@exemplo.com',
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/v1/invites/request', [
            'email' => 'repetido@exemplo.com',
        ]);

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
            'already_registered' => true,
        ]);
    }

    public function test_identifies_existing_registered_user(): void
    {
        $user = User::create([
            'name' => 'Existing User',
            'email' => 'existente@wwork.test',
            'password' => Hash::make('secret123'),
            'role' => 'owner',
            'locale' => 'pt',
        ]);

        $response = $this->postJson('/api/v1/invites/request', [
            'email' => $user->email,
        ]);

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
            'already_user' => true,
        ]);
    }
}
