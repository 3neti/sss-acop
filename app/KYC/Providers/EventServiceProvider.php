<?php

namespace App\KYC\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\KYC\Listeners\CreateUserFromKYCResult;
use Illuminate\Support\Facades\Event;
use App\KYC\Events\KYCResultFetched;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Event::listen(KYCResultFetched::class, CreateUserFromKYCResult::class);
    }
}
