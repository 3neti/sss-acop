<?php

namespace App\Commerce\Http\Middleware;

use Symfony\Component\HttpFoundation\Response;
use App\Commerce\Models\{System, Vendor};
use Illuminate\Http\Request;
use Closure;

/**
 * Middleware alias: `vendor`
 * Use in routes for vendor-only access.
 *
 * Allows:
 * - Vendor users
 * - System users if `commerce.system.allowed_as_vendor` is true
 */
class EnsureVendor
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user instanceof Vendor) {
            return $next($request);
        }

        if ($user instanceof System && config('sss-acop.system.allowed_as_vendor')) {
            return $next($request);
        }

        return response()->json(['message' => 'Unauthorized vendor.'], Response::HTTP_UNAUTHORIZED);
    }
}
