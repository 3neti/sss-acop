<?php

use App\KYC\Support\ContextCache;

if (!function_exists('cache_context')) {
    function cache_context(string $prefix): ContextCache
    {
        return new ContextCache($prefix);
    }
}
