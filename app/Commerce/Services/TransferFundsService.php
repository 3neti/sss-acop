<?php

namespace App\Commerce\Services;

use Bavix\Wallet\Interfaces\Customer;
use Illuminate\Support\Facades\DB;
use Bavix\Wallet\Models\Transfer;
use Illuminate\Support\Str;

class TransferFundsService
{
    public function transferUnconfirmed(Customer $from, Customer $to, float $amount, array $meta = []): Transfer
    {
        return DB::transaction(function () use ($from, $to, $amount, $meta): Transfer {
            $withdraw = $from->withdrawFloat($amount, $meta, confirmed: false);
            $deposit = $to->depositFloat($amount, $meta, confirmed: false);

            return Transfer::create([
                'uuid' => Str::uuid()->toString(),
                'deposit_id' => $deposit->id,
                'withdraw_id' => $withdraw->id,
                'from_type' => $from->getMorphClass(),
                'from_id' => $from->getKey(),
                'to_type' => $to->getMorphClass(),
                'to_id' => $to->getKey(),
                'status' => Transfer::STATUS_EXCHANGE,
                'discount' => 0,
                'fee' => 0,
            ]);
        });
    }

    public function confirmTransfer(Transfer $transfer): bool
    {
        $withdrawOwner = $transfer->withdraw->payable;
        $depositOwner = $transfer->deposit->payable;

        $withdrawOwner->confirm($transfer->withdraw);
        $depositOwner->confirm($transfer->deposit);

        return true;
    }

    public function rollbackTransfer(Transfer $transfer): bool
    {
        $withdrawOwner = $transfer->withdraw->payable;
        $depositOwner = $transfer->deposit->payable;

        $depositOwner->resetConfirm($transfer->deposit);
        $withdrawOwner->resetConfirm($transfer->withdraw);

        return true;
    }

    public function isResetTransferConfirmSafe(Transfer $transfer): bool
    {
        try {
            return $this->rollbackTransfer($transfer);
        } catch (\Throwable) {
            return false;
        }
    }
}
