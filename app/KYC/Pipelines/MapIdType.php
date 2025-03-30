<?php

namespace App\KYC\Pipelines;

use App\KYC\Enums\HypervergeIdType;
use Closure;

class MapIdType
{
    public function handle(HypervergeIdType|string $idType, Closure $next): string
    {
        $value = $idType instanceof HypervergeIdType
            ? $idType->value
            : $idType;

        return $next($value);
    }
}
