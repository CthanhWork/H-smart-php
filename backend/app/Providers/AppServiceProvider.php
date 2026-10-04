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
        RateLimiter::for('auth-login', fn (Request $request) => [
            Limit::perMinute(5)->by('login:'.hash('sha256', strtolower(trim((string) $request->input('email')))).'|'.$request->ip()),
            Limit::perMinute(30)->by('login-ip:'.$request->ip()),
        ]);
        RateLimiter::for('auth-register', fn (Request $request) => Limit::perMinute(5)->by('register:'.$request->ip()));
        RateLimiter::for('auth-verify', fn (Request $request) => Limit::perMinute(20)->by('verify:'.$request->ip()));
        RateLimiter::for('auth-refresh', fn (Request $request) => Limit::perMinute(30)->by('refresh:'.$request->ip()));
        RateLimiter::for('auth-recovery', fn (Request $request) => [
            Limit::perMinute(5)->by('recovery:'.hash('sha256', mb_strtolower(trim((string) $request->input('email')))).'|'.$request->ip()),
            Limit::perMinute(20)->by('recovery-ip:'.$request->ip()),
        ]);
    }
}
