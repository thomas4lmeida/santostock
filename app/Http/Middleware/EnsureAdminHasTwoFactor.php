<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminHasTwoFactor
{
    /**
     * Route names / path fragments the middleware must never block,
     * otherwise enrollment, logout, and 2FA API calls would loop.
     */
    private const ALLOWED_PATHS = [
        'settings/security',
        'settings/password',
        'user/two-factor',
        'two-factor-challenge',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole(Role::Administrador->value)) {
            return $next($request);
        }

        if ($user->two_factor_secret !== null) {
            return $next($request);
        }

        foreach (self::ALLOWED_PATHS as $path) {
            if ($request->is($path) || $request->is("{$path}/*")) {
                return $next($request);
            }
        }

        return redirect()->route('security.edit');
    }
}
