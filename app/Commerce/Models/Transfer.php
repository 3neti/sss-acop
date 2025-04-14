<?php

namespace App\Commerce\Models;

class Transfer extends \Bavix\Wallet\Models\Transfer
{
    public function getMetaAttribute(): array
    {
        return $this->extra ?? [];
    }
}
