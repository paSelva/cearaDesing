<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        // Voto popular: limite técnico contra automação (Recomendação; não é regra do edital).
        RateLimiter::for('votes', fn (Request $request) => Limit::perMinute(20)->by((string) ($request->user()?->id ?? $request->ip())));
    }
}
