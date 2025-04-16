<?php

namespace App\KYC\Providers;

use App\KYC\Http\Middleware\EnsureEmailMiddleware;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class AppServiceProvider  extends ServiceProvider
{
    public function register(): void
    {

    }

    public function boot(): void
    {
        Route::aliasMiddleware('email', EnsureEmailMiddleware::class);
    }
}
