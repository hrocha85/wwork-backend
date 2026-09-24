<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_responde_sem_autenticacao(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('version', 'v1')
            ->assertJsonPath('timezone', 'Europe/London')
            ->assertJsonPath('currency', 'GBP')
            ->assertJsonPath('database.status', 'up');
    }
}
