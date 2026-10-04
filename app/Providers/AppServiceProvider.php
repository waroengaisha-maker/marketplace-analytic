<?php

namespace App\Providers;

use App\Services\ShopeeApiClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ShopeeApiClient::class, fn (HttpFactory $http): ShopeeApiClient => new ShopeeApiClient(
            $http,
            (array) config('shopee-api'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('shopee-api', fn (Request $request) => Limit::perMinute(30)
            ->by('user:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('shopee-sync', fn (Request $request) => Limit::perMinute(6)
            ->by('user:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('report-upload', fn (Request $request) => Limit::perMinute(5)
            ->by('user:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        if (app()->environment('production')) {
            if (app()->hasDebugModeEnabled()) {
                throw new RuntimeException('APP_DEBUG must be false in production.');
            }

            if (config('app.trusted_hosts', []) === []) {
                throw new RuntimeException('TRUSTED_HOSTS must be configured in production.');
            }
        }
    }
}
