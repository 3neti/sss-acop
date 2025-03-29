<?php

namespace App\Pipelines;

use Closure;

class MapIdType
{
    protected array $map = [
        'phl_dl' => 'Philippine Driver\'s License',
        'phl_umid' => 'Unified Multi-purpose ID',
        // Add more...
    ];

    public function handle(string $idType, Closure $next): string
    {
        return $next($this->map[$idType] ?? $idType);
    }
}
