<?php

namespace App\Commerce\Http\Middleware;

use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;
use Closure;

/**
 * Middleware alias: `vendor`
 * Use in routes for vendor-only access.
 */
class EnsureVendor
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user || ! $user instanceof \App\Commerce\Models\Vendor) {
            return response()->json(['message' => 'Unauthorized vendor.'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
