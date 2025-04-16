<?php

namespace App\Models;

use Bavix\Wallet\Traits\{CanConfirm, CanPayFloat, HasWalletFloat};
use Bavix\Wallet\Interfaces\{Confirmable, Customer, WalletFloat};
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Commerce\Traits\CanTransferUnconfirmed;
use Illuminate\Notifications\Notifiable;
use App\KYC\Contracts\KYCUserInterface;
use Laravel\Sanctum\HasApiTokens;
use App\Commerce\Models\Vendor;
use App\KYC\Traits\HasKYCUser;
use Parental\HasChildren;

class User extends Authenticatable implements KYCUserInterface, Customer, WalletFloat, Confirmable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use CanConfirm, CanPayFloat, HasWalletFloat;
    use HasFactory, Notifiable;
    use CanTransferUnconfirmed;
    use HasApiTokens;
    use HasChildren;
    use HasKYCUser;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'type'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'balanceFloat' => 'float',
        ];
    }

    protected $childTypes = [
        'admin' => Admin::class,
        'guest' => Guest::class,
        'vendor' => Vendor::class,
    ];

    public function balance(): float
    {
        return $this->balanceFloat;
    }

    public function hasSufficientBalance(float $amount): bool
    {
        return $this->balanceFloat >= $amount;
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
