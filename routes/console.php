<?php

use App\Actions\Subscription\RemindOfferEnding;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('subscriptions:remind-offer-ending', function (RemindOfferEnding $remind) {
    $this->info('Sent: '.$remind());
})->purpose('Email owners 30 days before the launch price steps up or the annual renews');

Schedule::command('subscriptions:remind-offer-ending')->dailyAt('09:00')->timezone('Europe/London');

Schedule::command('visits:remind-upcoming')->everyFifteenMinutes();

Schedule::command('visits:extend-recurrence')->dailyAt('06:10')->timezone('Europe/London');

Schedule::command('billing:email-month')->monthlyOn(1, '08:00')->timezone('Europe/London');
Schedule::command('clients:send-profile-invites')->dailyAt('09:00')->timezone('Europe/London');
