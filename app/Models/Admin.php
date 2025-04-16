<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Parental\HasParent;

class Admin extends User
{
    use HasParent;

    protected static function newFactory()
    {
        return UserFactory::new()->state([
            'type' => 'admin',
        ]);
    }
}
