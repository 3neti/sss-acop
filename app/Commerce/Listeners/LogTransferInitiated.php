<?php

namespace App\Commerce\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use App\Commerce\Events\TransferInitiated;
use Illuminate\Support\Facades\Log;

class LogTransferInitiated implements ShouldQueue
{
    public function handle(TransferInitiated $event): void
    {
        Log::info('Transfer initiated', [
            'uuid' => $event->uuid,
            'from' => $event->fromId,
            'to' => $event->toId,
            'meta' => $event->meta,
        ]);
    }
}
