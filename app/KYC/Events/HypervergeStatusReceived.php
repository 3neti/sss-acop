<?php

namespace App\KYC\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HypervergeStatusReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $transactionId, public string $status){}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
