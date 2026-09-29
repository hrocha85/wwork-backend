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
        $this->app->bind(StripeBilling::class, LiveStripeBilling::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
