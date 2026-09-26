<?php

use App\Http\Controllers\Api\V1\AgendaController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PasswordController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\VisitController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [PasswordController::class, 'forgot']);
Route::post('/reset-password', [PasswordController::class, 'reset']);
Route::post('/invites/{token}/accept', [TeamController::class, 'accept']);
Route::get('/invoices/share/{token}', [InvoiceController::class, 'sharedPdf']);
Route::get('/agenda/{token}', [AgendaController::class, 'show']);
Route::post('/agenda/{token}/notify', [AgendaController::class, 'notify']);
Route::post('/stripe/webhook', [SubscriptionController::class, 'webhook']);
Route::post('/setup-intent', [SubscriptionController::class, 'setup']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [MeController::class, 'show']);
    Route::post('/password/change', [PasswordController::class, 'change']);

    Route::middleware(['app.password', 'subscription.write'])->group(function () {
        Route::patch('/me', [MeController::class, 'update']);

        Route::post('/partners', [TeamController::class, 'store']);
        Route::get('/team', [TeamController::class, 'index']);
        Route::post('/invites/{invite}/resend', [TeamController::class, 'resend']);
        Route::patch('/team/{userId}/rate', [TeamController::class, 'updateRate']);
        Route::delete('/team/{userId}', [TeamController::class, 'destroy']);

        Route::get('/clients', [ClientController::class, 'index']);
        Route::post('/clients', [ClientController::class, 'store']);
        Route::get('/clients/{client}', [ClientController::class, 'show']);
        Route::delete('/clients/{client}', [ClientController::class, 'destroy']);

        Route::get('/visits.ics', [VisitController::class, 'ics']);
        Route::get('/visits', [VisitController::class, 'index']);
        Route::post('/visits', [VisitController::class, 'store']);
        Route::get('/visits/{visit}', [VisitController::class, 'show']);
        Route::patch('/visits/{visit}', [VisitController::class, 'update']);
        Route::delete('/visits/{visit}', [VisitController::class, 'destroy']);
        Route::post('/visits/{visit}/accept', [VisitController::class, 'accept']);
        Route::post('/visits/{visit}/decline', [VisitController::class, 'decline']);
        Route::post('/visits/{visit}/events', [VisitController::class, 'event']);
        Route::post('/visits/{visit}/photos', [VisitController::class, 'photo']);
        Route::patch('/visits/{visit}/goals', [VisitController::class, 'goals']);

        Route::get('/invoice-candidates', [InvoiceController::class, 'candidates']);
        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::post('/invoices', [InvoiceController::class, 'store']);
        Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf']);
        Route::post('/invoices/{invoice}/share', [InvoiceController::class, 'share']);
        Route::post('/invoices/{invoice}/paid', [InvoiceController::class, 'paid']);
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);

        Route::get('/billing', [BillingController::class, 'show']);
        Route::post('/payouts/{payout}/paid', [BillingController::class, 'pay']);

        Route::post('/clients/{client}/agenda-link', [AgendaController::class, 'link']);

        Route::get('/subscription/plans', [SubscriptionController::class, 'plans']);
        Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel']);
        Route::post('/subscription/annual', [SubscriptionController::class, 'annual']);
        Route::post('/subscription/upgrade', [SubscriptionController::class, 'upgrade']);
        Route::post('/subscription/payment-method', [SubscriptionController::class, 'paymentMethod']);
        Route::get('/subscription/invoices', [SubscriptionController::class, 'invoices']);
    });
});
