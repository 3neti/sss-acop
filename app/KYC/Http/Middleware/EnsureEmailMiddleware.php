<?php

namespace App\KYC\Http\Middleware;

use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Closure;

class EnsureEmailMiddleware
{
    protected string $register_redirect = 'profile.edit';

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if (!filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->route($this->register_redirect)
                ->withErrors(['email' => 'Valid email address required.']);
        }

        return $next($request);
    }
}
