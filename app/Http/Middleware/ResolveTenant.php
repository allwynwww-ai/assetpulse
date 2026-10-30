<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs on every authenticated request: blocks users of deactivated companies
 * and shares the current company with all views.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->isSuperAdmin()) {
            $company = $user->company_id ? Company::find($user->company_id) : null;
            if (! $company || ! $company->is_active) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();

                return redirect()->route('login')->withErrors(['email' => 'Your company account is inactive. Contact the platform administrator.']);
            }
        }

        View::share('currentCompany', Tenant::company());

        return $next($request);
    }
}
