<?php

namespace App\Commerce\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Commerce\Listeners\{LogTransferInitiated, LogTransferRefunded};
use App\Commerce\Events\{TransferInitiated, TransferRefunded};
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(TransferInitiated::class, LogTransferInitiated::class);
        Event::listen(TransferRefunded::class, LogTransferRefunded::class);
    }
}
