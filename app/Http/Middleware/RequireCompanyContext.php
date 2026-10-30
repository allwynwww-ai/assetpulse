<?php

namespace App\Http\Middleware;

use App\Support\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Tenant screens need a company; send the Super Admin to pick one first. */
class RequireCompanyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Tenant::id() === null) {
            if ($request->user()?->isSuperAdmin()) {
                return redirect()->route('admin.companies.index')
                    ->with('warning', 'Select a company (Enter ›) to work inside its data.');
            }
            abort(403, 'No company assigned to your account.');
        }

        return $next($request);
    }
}
