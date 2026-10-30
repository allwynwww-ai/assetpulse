<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('role:company_admin,asset_manager').
 * Super Admin always passes (platform owner).
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        if ($user->isSuperAdmin() || in_array($user->role->value, $roles, true)) {
            return $next($request);
        }
        abort(403, 'Your role ('.$user->role->label().') does not allow this action.');
    }
}
