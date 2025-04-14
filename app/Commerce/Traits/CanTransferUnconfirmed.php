<?php

namespace App\Commerce\Traits;

use App\Commerce\Services\TransferFundsService;
use Bavix\Wallet\Interfaces\Wallet;
use Bavix\Wallet\Models\Transfer;

trait CanTransferUnconfirmed
{
    public function transferUnconfirmed(Wallet $to, float $amount, array $meta = []): Transfer
    {
        return app(TransferFundsService::class)->transferUnconfirmed($this, $to, $amount, $meta);
    }

    public function resetTransferConfirm(Transfer $transfer): bool
    {
        return app(TransferFundsService::class)->rollbackTransfer($transfer);
    }
}
