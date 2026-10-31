<?php

namespace App\Modules\Offer\Providers;

use App\Modules\Offer\Models\Offer;
use App\Modules\Offer\Policies\OfferPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class OfferServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        Gate::policy(Offer::class, OfferPolicy::class);
    }
}
