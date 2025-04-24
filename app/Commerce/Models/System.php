<?php

namespace App\Commerce\Models;

use Database\Factories\UserFactory;
use Parental\HasParent;
use App\Models\User;

class System extends User
{
    use HasParent;

    protected static function newFactory()
    {
        return UserFactory::new()->state([
            'type' => 'system',
            'id_type' => config('sss-acop.system.user.id_type'),
            'id_value' => config('sss-acop.system.user.id_value'),
        ]);
    }
}
