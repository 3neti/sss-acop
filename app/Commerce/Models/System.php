<?php

namespace App\Commerce\Models;

use Database\Factories\UserFactory;
use Parental\HasParent;
use App\Models\User;

class System extends User
{
    use HasParent;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new()
            ->state([
                'type' => 'system',
            ])
            ->withSystemIdentification();
    }
}
