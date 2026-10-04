<?php

namespace App\Modules\Product\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class ProductServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('product-create', fn (Request $request) => Limit::perMinute(10)
            ->by('product-create:'.$request->user()?->id));

        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
    }
}
