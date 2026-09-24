<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Health sem autenticação.
 *
 * O banco é o MariaDB online e não há cópia local, então saber se a conexão
 * está de pé é metade do diagnóstico quando a tela não carrega.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $database = 'up';
        $status = 'ok';

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $exception) {
            $database = 'down';
            $status = 'degraded';
        }

        return response()->json([
            'status' => $status,
            'app' => config('app.name'),
            'version' => 'v1',
            'timezone' => config('app.timezone'),
            'currency' => config('wwork.currency'),
            'database' => [
                'connection' => config('database.default'),
                'status' => $database,
            ],
            'time' => now()->toIso8601String(),
        ], $status === 'ok' ? 200 : 503);
    }
}
