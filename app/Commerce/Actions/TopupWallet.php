<?php

namespace App\Commerce\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use App\Commerce\Events\WalletToppedUp;
use App\Commerce\Models\Transfer;
use Illuminate\Support\Number;
use InvalidArgumentException;
use Brick\Money\Money;
use RuntimeException;
use App\Models\User;


class TopupWallet
{
    use AsAction;

    /**
     * Top up a user’s wallet from the system user.
     *
     * @param  User $user
     * @param  Money|float|int|string $amount
     * @param  array $meta
     * @return Transfer
     */
    public function handle(User $user, Money|float|int|string $amount, array $meta = []): Transfer
    {
        $system = User::systemUser();
        $money = $this->normalizeAmount($amount);

        if ($money->isLessThanOrEqualTo(Money::zero($money->getCurrency()->getCurrencyCode()))) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        /** @var Transfer $transfer */
        $transfer = $system->transferFloat($user, $money->getAmount()->toFloat());

        if (! $transfer instanceof Transfer) {
            throw new RuntimeException('Expected custom Transfer model was not returned.');
        }

        if (!empty($meta)) {
            $transfer->update([
                'extra' => array_merge($transfer->extra ?? [], $meta),
            ]);
        }

        // ✅ Dispatch WalletToppedUp event
        event(WalletToppedUp::from(
            user: $user,
            amount: $money,
            transfer: $transfer,
            meta: $meta
        ));

        return $transfer;
    }

    /**
     * Normalize amount into a Brick\Money\Money instance.
     *
     * @param  Money|float|int|string $amount
     * @return Money
     */
    protected function normalizeAmount(Money|float|int|string $amount): Money
    {
        if ($amount instanceof Money) {
            return $amount;
        }

        if (is_numeric($amount)) {
            $currency = Number::defaultCurrency(); // e.g., 'PHP'
            return Money::of($amount, $currency);
        }

        throw new InvalidArgumentException('Amount must be a Money instance, float, int, or numeric string.');
    }
}
