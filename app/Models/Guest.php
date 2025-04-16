<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Parental\HasParent;

class Guest extends User
{
    use HasParent;

    protected static function newFactory()
    {
        return UserFactory::new()->state([
            'type' => 'guest',
        ]);
    }
}
