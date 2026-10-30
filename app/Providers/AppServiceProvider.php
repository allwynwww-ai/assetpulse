<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \Illuminate\Foundation\AliasLoader::getInstance()->alias('Tenant', \App\Support\Tenant::class);
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(! app()->isProduction());

        // Authorisation gates used by Blade (@can) and routes (can:...)
        Gate::define('manage-platform', fn (User $u) => $u->isSuperAdmin());
        Gate::define('manage-masters', fn (User $u) => $u->canManageMasters());
        Gate::define('manage-assets', fn (User $u) => $u->canManageAssets());
        Gate::define('run-depreciation', fn (User $u) => $u->canManageAssets());
        Gate::define('finalize-depreciation', fn (User $u) => $u->isCompanyAdmin() || $u->isSuperAdmin());

        // Security events into the audit trail
        Event::listen(Login::class, function (Login $e) {
            $user = $e->user;
            $user->forceFill(['last_login_at' => now(), 'last_login_ip' => Request::ip()])->saveQuietly();
            $this->securityLog($user, 'login');
        });
        Event::listen(Logout::class, fn (Logout $e) => $e->user ? $this->securityLog($e->user, 'logout') : null);
        Event::listen(Failed::class, function (Failed $e) {
            AuditLog::create([
                'company_id' => $e->user?->company_id, 'user_id' => $e->user?->id,
                'user_name' => $e->credentials['email'] ?? 'unknown', 'event' => 'login_failed',
                'url' => Request::fullUrl(), 'ip_address' => Request::ip(),
                'user_agent' => mb_substr((string) Request::userAgent(), 0, 500),
            ]);
        });
    }

    protected function securityLog(User $user, string $event): void
    {
        AuditLog::create([
            'company_id' => $user->company_id, 'user_id' => $user->id, 'user_name' => $user->name,
            'event' => $event, 'auditable_type' => User::class, 'auditable_id' => $user->id,
            'auditable_label' => $user->email, 'url' => Request::fullUrl(), 'ip_address' => Request::ip(),
            'user_agent' => mb_substr((string) Request::userAgent(), 0, 500),
        ]);
    }
}
