<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Contrato REST em /api/v1
|--------------------------------------------------------------------------
|
| Fatia 1 só tem health e sessão. Cliente, visita, evento, foto, agenda
| pública, fatura, faturamento, Stripe e sync são fatias 2 a 5.
|
| A sessão é cookie do Sanctum: o statefulApi() em bootstrap/app.php é o que
| faz estas rotas enxergarem o cookie.
|
*/

Route::prefix('v1')->group(function (): void {
    Route::get('health', HealthController::class)->name('api.v1.health');

    Route::post('login', [AuthController::class, 'login'])->name('api.v1.login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout'])->name('api.v1.logout');
        Route::get('me', [AuthController::class, 'me'])->name('api.v1.me');
    });
});
