<?php

namespace App\Providers;

use App\Services\RedisService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Ensure Laravel reuses the exact same RedisService instance throughout an entire request
        $this->app->singleton(RedisService::class, fn (): RedisService => new RedisService);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
