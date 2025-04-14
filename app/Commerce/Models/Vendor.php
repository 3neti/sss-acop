<?php

namespace App\Commerce\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Bavix\Wallet\Traits\{CanConfirm, CanPayFloat, HasWalletFloat};
use Bavix\Wallet\Interfaces\{Confirmable, Customer, WalletFloat};
use App\Commerce\Traits\CanTransferUnconfirmed;
use Illuminate\Database\Eloquent\Model;
use Database\Factories\VendorFactory;

class Vendor extends Model implements WalletFloat, Customer, Confirmable
{
    use CanConfirm, CanPayFloat, HasWalletFloat;
    use CanTransferUnconfirmed;
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'mobile',
    ];

    protected static function newFactory(): VendorFactory
    {
        return VendorFactory::new();
    }

    public function balance(): float
    {
        return $this->balanceFloat;
    }

    public function hasSufficientBalance(float $amount): bool
    {
        return $this->balanceFloat >= $amount;
    }

    public function withdrawAmount(float $amount): bool
    {
        if (! $this->hasSufficientBalance($amount)) {
            return false;
        }

        $this->withdrawFloat($amount);
        return true;
    }

    public function transferTo(Customer $recipient, float $amount): bool
    {
        if (! $this->hasSufficientBalance($amount)) {
            return false;
        }

        $this->transferFloat($recipient, $amount);
        return true;
    }

    public function refundTo(Customer $user, float $amount): bool
    {
        return $this->transferTo($user, $amount);
    }
}
