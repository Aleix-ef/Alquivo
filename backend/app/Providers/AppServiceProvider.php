<?php

namespace App\Providers;

use App\Listeners\SyncStripePlan;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Events\WebhookHandled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(WebhookHandled::class, SyncStripePlan::class);
        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(120)->by(
                $request->user()?->getAuthIdentifier() ?? $request->ip()
            );
        });
    }
}
