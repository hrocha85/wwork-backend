<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PasswordController;
use App\Http\Controllers\Api\V1\TeamController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [PasswordController::class, 'forgot']);
Route::post('/reset-password', [PasswordController::class, 'reset']);
Route::post('/invites/{token}/accept', [TeamController::class, 'accept']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [MeController::class, 'show']);
    Route::post('/password/change', [PasswordController::class, 'change']);

    Route::middleware('app.password')->group(function () {
        Route::patch('/me', [MeController::class, 'update']);

        Route::post('/partners', [TeamController::class, 'store']);
        Route::get('/team', [TeamController::class, 'index']);
        Route::post('/invites/{invite}/resend', [TeamController::class, 'resend']);
        Route::patch('/team/{userId}/rate', [TeamController::class, 'updateRate']);
        Route::delete('/team/{userId}', [TeamController::class, 'destroy']);
    });
});
