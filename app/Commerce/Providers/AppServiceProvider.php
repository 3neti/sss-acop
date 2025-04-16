<?php

namespace App\Commerce\Providers;

use App\Commerce\Http\Middleware\EnsureVendor;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class AppServiceProvider  extends ServiceProvider
{
    public function register(): void
    {

    }

    public function boot(): void
    {
        Route::aliasMiddleware('vendor', EnsureVendor::class);
    }
}
