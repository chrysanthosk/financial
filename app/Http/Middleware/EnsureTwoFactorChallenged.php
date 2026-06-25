<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureTwoFactorChallenged
{
    public function handle(Request $request, Closure $next)
    {
        // Only enforce when a session is marked as "pending 2FA challenge"
        if (! $request->session()->has('2fa:user:id')) {
            return $next($request);
        }

        // Allow the 2FA challenge routes through
        if ($request->routeIs('two-factor.challenge.*')) {
            return $next($request);
        }

        // Allow login/logout routes (so user can restart auth flow)
        if ($request->routeIs('login') || $request->routeIs('logout')) {
            return $next($request);
        }

        // Allow static assets
        if (
            $request->is('build/*') ||
            $request->is('css/*') ||
            $request->is('js/*') ||
            $request->is('images/*') ||
            $request->is('favicon.ico')
        ) {
            return $next($request);
        }

        // Everything else: force challenge page
        return redirect()->route('two-factor.challenge.show');
    }
}
