<?php

namespace App\Commerce\Services;

use App\Commerce\Events\TransferFinalized;
use App\Commerce\Events\TransferInitiated;
use App\Commerce\Events\TransferRefunded;
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

            $transfer = Transfer::create([
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
                'extra' => $meta,
            ]);

//            event(new TransferInitiated($transfer)); // ✅ Dispatch here
            event(new TransferInitiated(
                uuid: $transfer->uuid,
                fromId: $transfer->from_id,
                toId: $transfer->to_id,
                meta: $transfer->extra
            ));

            return $transfer;
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

    function faceLoginMeta(string $voucherCode): array
    {
        return [
            'initiated_by' => 'face_login',
            'transfer_type' => 'voucher_redemption', // or 'cashback', 'manual_refund', etc.
            'reason' => "payment for voucher {$voucherCode}",
        ];
    }

    public function finalizeTransfer(Transfer $transfer): bool
    {
        $transfer->status = Transfer::STATUS_PAID;
        $transfer->save();

        event(new TransferFinalized(
            uuid: $transfer->uuid,
            fromId: $transfer->from_id,
            toId: $transfer->to_id,
            meta: $transfer->extra ?? []
        ));

        return true;
    }

    public function refundTransfer(Transfer $original): ?Transfer
    {
        ['from' => $from, 'to' => $to] = $this->resolveRefundParticipants($original);

        // Ensure the refund amount is always positive
        $amount = abs($original->withdraw->amountFloat);

        $meta = array_merge($original->extra ?? [], [
            'transfer_type' => 'refund',
            'refunded_transfer_uuid' => $original->uuid,
        ]);

        dump([
            'step' => 'start',
            'from_balance' => $from->balanceFloat,
            'to_balance' => $to->balanceFloat,
            'amount' => $amount,
            'meta' => $meta,
        ]);

        return DB::transaction(function () use ($from, $to, $amount, $meta, $original): ?Transfer {
            try {
                dump([
                    'step' => 'before withdraw',
                    'from_id' => $from->getKey(),
                    'from_balance' => $from->balanceFloat,
                ]);

                $withdraw = $from->withdrawFloat($amount, $meta, confirmed: true);

                dump([
                    'step' => 'after withdraw',
                    'withdraw_id' => $withdraw->id,
                    'from_balance' => $from->balanceFloat,
                ]);

                $deposit = $to->depositFloat($amount, $meta, confirmed: true);

                dump([
                    'step' => 'after deposit',
                    'deposit_id' => $deposit->id,
                    'to_balance' => $to->balanceFloat,
                ]);

                $refund = Transfer::create([
                    'uuid' => Str::uuid()->toString(),
                    'deposit_id' => $deposit->id,
                    'withdraw_id' => $withdraw->id,
                    'from_type' => $from->getMorphClass(),
                    'from_id' => $from->getKey(),
                    'to_type' => $to->getMorphClass(),
                    'to_id' => $to->getKey(),
                    'status' => Transfer::STATUS_REFUND,
                    'discount' => 0,
                    'fee' => 0,
                    'extra' => $meta,
                ]);

                dump([
                    'step' => 'refund created',
                    'refund_uuid' => $refund->uuid,
                ]);

                event(new TransferRefunded(
                    uuid: $refund->uuid,
                    originalUuid: $original->uuid,
                    fromId: $from->getKey(),
                    toId: $to->getKey(),
                    meta: $meta,
                ));

                return $refund;
            } catch (\Throwable $e) {
                dump([
                    'step' => 'error',
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                report($e);
                return null;
            }
        });
    }

    protected function resolveRefundParticipants(Transfer $original): array
    {
        return [
            'from' => $original->deposit->payable,
            'to' => $original->withdraw->payable,
        ];
    }
}
