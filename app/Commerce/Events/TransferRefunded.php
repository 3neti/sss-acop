<?php

namespace App\Commerce\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Queue\SerializesModels;

class TransferRefunded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $uuid,
        public string $originalUuid,
        public int $fromId,
        public int $toId,
        public array $meta = [],
    ) {}
}
