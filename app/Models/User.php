<?php

namespace App\Models;

use Bavix\Wallet\Traits\{CanConfirm, CanPayFloat, HasWalletFloat};
use Bavix\Wallet\Interfaces\{Confirmable, Customer, WalletFloat};
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\KYC\Traits\{HasBridgeIdentifiers, HasKYCUser};
use App\Commerce\Traits\CanTransferUnconfirmed;
use App\Commerce\Models\{System, Vendor};
use Illuminate\Notifications\Notifiable;
use App\KYC\Contracts\KYCUserInterface;
use App\KYC\Models\Identification;
use Laravel\Sanctum\HasApiTokens;
use App\KYC\Enums\KYCIdType;
use Parental\HasChildren;

class User extends Authenticatable implements KYCUserInterface, Customer, WalletFloat, Confirmable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use CanConfirm, CanPayFloat, HasWalletFloat;
    use HasKYCUser, HasBridgeIdentifiers;
    use HasFactory, Notifiable;
    use CanTransferUnconfirmed;
    use HasApiTokens;
    use HasChildren;

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
        'system' => System::class
    ];

    protected static function booted(): void
    {
        static::saved(function (User $user) {
            if ($user->isDirty('email') && $user->email) {
                $user->identifications()->updateOrCreate(
                    ['id_type' => KYCIdType::EMAIL],
                    ['id_value' => $user->email]
                );
            }
        });
    }

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

    public static function findByIdentification(string $idType, string $idValue): ?self
    {
        return static::whereHas('identifications', function ($query) use ($idType, $idValue) {
            $query->where('id_type', $idType)
                ->where('id_value', $idValue);
        })->first();
    }

    public static function findByIdentificationOrFail(string $idType, string $idValue): self
    {
        return static::findByIdentification($idType, $idValue)
            ?? throw (new ModelNotFoundException)->setModel(self::class);
    }

    public static function systemUser(): self
    {
        return static::findByIdentification(
            config('sss-acop.system.user.id_type'),
            config('sss-acop.system.user.id_value'),
        ) ?? throw new \RuntimeException('System user not found.');
    }

    public function identifications(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Identification::class);
    }
}
