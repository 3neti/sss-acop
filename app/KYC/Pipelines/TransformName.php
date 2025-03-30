<?php

namespace App\KYC\Pipelines;

use Illuminate\Support\Str;
use Closure;

class TransformName
{
    public function handle(string|null $name, Closure $next): string
    {
        if (!$name) {
            return $next('');
        }

        if (Str::contains($name, ',')) {
            [$last, $first] = explode(',', $name);
            $name = trim($first) . ' ' . trim($last);
        }

        return $next(Str::of($name)->title()->toString());
    }
}
