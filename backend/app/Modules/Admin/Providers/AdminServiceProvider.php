<?php

namespace App\Modules\Admin\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadRoutes();
    }

    private function loadRoutes(): void
    {
        Route::prefix('api')
            ->middleware('api')
            ->group(base_path('app/Modules/Admin/routes/api.php'));
    }
}
