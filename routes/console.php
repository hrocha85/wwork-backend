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
