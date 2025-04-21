<?php

namespace App\Commerce\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Queue\SerializesModels;
use App\Commerce\Models\Transfer;
use Brick\Money\Money;
use App\Models\User;

class WalletToppedUp
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly Money $amount,
        public readonly Transfer $transfer,
        public readonly array $meta = [],
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->user->id}.wallet"),
        ];
    }

    public static function from(User $user, Money $amount, Transfer $transfer, array $meta = []): self
    {
        return new self($user, $amount, $transfer, $meta);
    }
}
