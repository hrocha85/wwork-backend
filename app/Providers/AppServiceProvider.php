<?php

namespace App\Providers;

use App\Services\Stripe\LiveStripeBilling;
use App\Services\Stripe\StripeBilling;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(StripeBilling::class, function () {
            $live = new LiveStripeBilling();
            if ($live->available()) {
                return $live;
            }

            return new \App\Services\Stripe\FakeStripeBilling();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
