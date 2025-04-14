<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Bavix\Wallet\Traits\{CanConfirm, CanPayFloat, HasWalletFloat};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Bavix\Wallet\Interfaces\{Confirmable, Customer, WalletFloat};
use App\Commerce\Traits\CanTransferUnconfirmed;
use Illuminate\Notifications\Notifiable;
use App\KYC\Contracts\KYCUserInterface;
use App\KYC\Traits\HasKYCUser;

class User extends Authenticatable implements KYCUserInterface, Customer, WalletFloat, Confirmable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use CanConfirm, CanPayFloat, HasWalletFloat;
    use HasFactory, Notifiable;
    use CanTransferUnconfirmed;
    use HasKYCUser;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password'
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

    public function balance(): float
    {
        return $this->balanceFloat;
    }

    public function hasSufficientBalance(float $amount): bool
    {
        return $this->balanceFloat >= $amount;
    }
}
