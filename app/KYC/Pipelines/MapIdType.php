<?php

namespace App\KYC\Pipelines;

use App\KYC\Enums\KYCIdType;
use Closure;

class MapIdType
{
    public function handle(KYCIdType|string $idType, Closure $next): string
    {
        $value = $idType instanceof KYCIdType
            ? $idType->value
            : $idType;

        return $next($value);
    }
}
