<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * A origem do PWA em desenvolvimento.
     *
     * O Sanctum só trata o pedido como stateful — isto é, só deixa o cookie de
     * sessão valer — quando o Origin bate com SANCTUM_STATEFUL_DOMAINS. Testar
     * sem esse cabeçalho testaria outro caminho, o do token Bearer, que não é
     * o que o WWork usa.
     */
    protected const PWA_ORIGIN = 'http://localhost:3000';

    /** @return array<string, string> */
    protected function pwaHeaders(): array
    {
        return ['Origin' => self::PWA_ORIGIN];
    }
}
