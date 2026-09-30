<?php

namespace App\Providers;

use App\Services\ShopeeApiClient;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ShopeeApiClient::class, fn (): ShopeeApiClient => new ShopeeApiClient(
            app(HttpFactory::class),
            (array) config('shopee-api'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
