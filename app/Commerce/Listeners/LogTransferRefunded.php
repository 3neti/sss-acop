<?php

namespace App\Commerce\Listeners;

use App\Commerce\Events\TransferRefunded;
use Illuminate\Support\Facades\Log;

class LogTransferRefunded
{
    public function handle(TransferRefunded $event): void
    {
        Log::info('Refund completed', [
            'refund_uuid' => $event->uuid,
            'original_uuid' => $event->originalUuid,
            'from' => $event->fromId,
            'to' => $event->toId,
            'meta' => $event->meta,
        ]);
    }
}
