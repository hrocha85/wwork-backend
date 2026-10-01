<?php

use App\Http\Controllers\Api\V1\AgencyController;
use App\Http\Controllers\Api\V1\AgendaController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\ClientPortalController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PasswordController;
use App\Http\Controllers\Api\V1\PublicAgencyController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\VisitController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/refresh', [AuthController::class, 'refresh']);
Route::post('/forgot-password', [PasswordController::class, 'forgot']);
Route::post('/reset-password', [PasswordController::class, 'reset']);
Route::post('/invites/{token}/accept', [TeamController::class, 'accept']);
Route::get('/invoices/share/{token}', [InvoiceController::class, 'sharedPdf']);
Route::get('/agenda/{token}', [AgendaController::class, 'show'])->where('token', '(?!blocks$)[A-Za-z0-9-]+');
Route::post('/agenda/{token}/notify', [AgendaController::class, 'notify']);
Route::get('/book/{token}/logo', [BookingController::class, 'publicLogo']);
Route::post('/book/{token}/quotes', [BookingController::class, 'publicQuote']);
Route::get('/book/{token}/quotes/{publicToken}/photo', [BookingController::class, 'publicQuotePhoto']);
Route::post('/book/{token}/quotes/{publicToken}', [BookingController::class, 'publicQuoteAnswer']);
Route::get('/book/{token}/quotes/{publicToken}', [BookingController::class, 'publicQuoteShow']);
Route::get('/book/{token}', [BookingController::class, 'publicShow']);
Route::post('/book/{token}', [BookingController::class, 'publicStore']);
Route::get('/search', [ClientPortalController::class, 'search']);
Route::get('/join/{token}', [ClientPortalController::class, 'preview']);
Route::post('/join/{token}', [ClientPortalController::class, 'accept']);
Route::get('/agency/{slug}/logo', [PublicAgencyController::class, 'logo']);
Route::get('/agency/{slug}/photos/{photo}', [PublicAgencyController::class, 'photo']);
Route::get('/agency/{slug}/avatars/{client}', [PublicAgencyController::class, 'avatar']);
Route::get('/agency/{slug}/reviews', [PublicAgencyController::class, 'reviews']);
Route::get('/agency/{slug}', [PublicAgencyController::class, 'show'])->where('slug', '(?!logo$)[^/]+');
Route::post('/stripe/webhook', [SubscriptionController::class, 'webhook']);
Route::post('/setup-intent', [SubscriptionController::class, 'setup']);
Route::get('/pricing', [SubscriptionController::class, 'pricing']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/session', [AuthController::class, 'session']);
    Route::get('/me', [MeController::class, 'show']);
    Route::get('/client/home', [ClientPortalController::class, 'home']);
    Route::get('/client/invoices/{invoice}/pdf', [ClientPortalController::class, 'invoicePdf']);
    Route::post('/client/reviews', [ClientPortalController::class, 'review']);
    Route::post('/client/avatar', [ClientPortalController::class, 'avatar']);
    Route::post('/password/change', [PasswordController::class, 'change']);

    Route::middleware(['app.password', 'subscription.write'])->group(function () {
        Route::patch('/me', [MeController::class, 'update']);
        Route::post('/me/avatar', [MeController::class, 'avatar']);
        Route::get('/me/avatar', [MeController::class, 'showAvatar']);
        Route::patch('/agency', [AgencyController::class, 'update']);
        Route::post('/onboarding', [AgencyController::class, 'onboarding']);
        Route::post('/agency/logo', [AgencyController::class, 'logo']);
        Route::get('/agency/logo', [AgencyController::class, 'showLogo']);
        Route::patch('/agency/profile', [PublicAgencyController::class, 'update']);
        Route::post('/agency/portfolio', [PublicAgencyController::class, 'storePhoto']);
        Route::delete('/agency/portfolio/{photo}', [PublicAgencyController::class, 'destroyPhoto']);
        Route::post('/me/location', [LocationController::class, 'update']);
        Route::get('/team/locations', [LocationController::class, 'index']);

        Route::post('/partners', [TeamController::class, 'store']);
        Route::get('/team', [TeamController::class, 'index']);
        Route::get('/team/{userId}/avatar', [TeamController::class, 'avatar'])->whereNumber('userId');
        Route::post('/invites/{invite}/resend', [TeamController::class, 'resend']);
        Route::post('/invites/{invite}/cancel', [TeamController::class, 'cancel']);
        Route::patch('/team/{userId}/rate', [TeamController::class, 'updateRate']);
        Route::delete('/team/{userId}', [TeamController::class, 'destroy']);

        Route::get('/clients', [ClientController::class, 'index']);
        Route::post('/clients', [ClientController::class, 'store']);
        Route::post('/clients/import', [ClientController::class, 'import']);
        Route::post('/clients/import/calendar', [ClientController::class, 'importCalendar']);
        Route::get('/clients/{client}', [ClientController::class, 'show']);
        Route::patch('/clients/{client}', [ClientController::class, 'update']);
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
        Route::get('/invoices/pending', [InvoiceController::class, 'pending']);
        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::post('/invoices', [InvoiceController::class, 'store']);
        Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf']);
        Route::post('/invoices/{invoice}/share', [InvoiceController::class, 'share']);
        Route::post('/invoices/{invoice}/paid', [InvoiceController::class, 'paid']);
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);

        Route::get('/billing', [BillingController::class, 'show']);
        Route::post('/payouts/{payout}/paid', [BillingController::class, 'pay']);

        Route::post('/clients/{client}/agenda-link', [AgendaController::class, 'link']);
        Route::post('/clients/{client}/join-link', [ClientPortalController::class, 'link']);
        Route::get('/agenda/blocks', [AgendaController::class, 'blocks']);
        Route::post('/agenda/blocks', [AgendaController::class, 'storeBlock']);
        Route::delete('/agenda/blocks/{block}', [AgendaController::class, 'destroyBlock']);

        Route::get('/booking', [BookingController::class, 'show']);
        Route::put('/booking', [BookingController::class, 'update']);
        Route::get('/booking/requests', [BookingController::class, 'requests']);
        Route::post('/booking/requests/{id}/reply', [BookingController::class, 'reply']);
        Route::post('/booking/requests/{id}', [BookingController::class, 'decide']);

        Route::get('/subscription/plans', [SubscriptionController::class, 'plans']);
        Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel']);
        Route::post('/subscription/annual', [SubscriptionController::class, 'annual']);
        Route::post('/subscription/upgrade', [SubscriptionController::class, 'upgrade']);
        Route::post('/subscription/payment-method', [SubscriptionController::class, 'paymentMethod']);
        Route::get('/subscription/invoices', [SubscriptionController::class, 'invoices']);
    });
});
