<?php

namespace App\Commerce\Models;

use Database\Factories\UserFactory;
use Parental\HasParent;
use App\Models\User;

class Vendor extends User
{
    use HasParent;

    protected static function newFactory()
    {
        return UserFactory::new()->state([
            'type' => 'vendor',
        ]);
    }

    /** @deprecated  */
    public function withdrawAmount(float $amount): bool
    {
        if (! $this->hasSufficientBalance($amount)) {
            return false;
        }

        $this->withdrawFloat($amount);
        return true;
    }
}
