<?php

namespace App\Pipelines;

use Illuminate\Support\Str;
use Closure;

class TransformName
{
    public function handle(string $name, Closure $next): string
    {
        if (Str::contains($name, ',')) {
            [$last, $first] = explode(',', $name);
            $name = trim($first) . ' ' . trim($last);
        }

        return $next(Str::of($name)->title()->toString());
    }
}
