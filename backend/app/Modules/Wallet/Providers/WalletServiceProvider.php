<?php

namespace App\Modules\Wallet\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class WalletServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('wallet-write', fn (Request $request) => Limit::perMinute(5)
            ->by('wallet-write:'.$request->user()?->id));
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
    }
}
