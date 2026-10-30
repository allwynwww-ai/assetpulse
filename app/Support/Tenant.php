<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves the "current company" (tenant) for the request.
 *
 * - Company users are always pinned to their own company_id.
 * - The Super Admin works platform-wide and may "enter" a company; that
 *   choice is stored in the session (active_company_id).
 * - Console / queue code may set the tenant explicitly via Tenant::set().
 */
class Tenant
{
    public const SESSION_KEY = 'active_company_id';

    protected static ?int $forcedId = null;

    protected static bool $forced = false;

    public static function set(?int $companyId): void
    {
        static::$forcedId = $companyId;
        static::$forced = true;
    }

    public static function clear(): void
    {
        static::$forcedId = null;
        static::$forced = false;
    }

    /** Run a callback inside a specific tenant context. */
    public static function run(?int $companyId, callable $callback): mixed
    {
        $prevForced = static::$forced;
        $prevId = static::$forcedId;
        static::set($companyId);
        try {
            return $callback();
        } finally {
            static::$forced = $prevForced;
            static::$forcedId = $prevId;
        }
    }

    public static function id(): ?int
    {
        if (static::$forced) {
            return static::$forcedId;
        }

        $user = Auth::user();
        if (! $user) {
            return null;
        }

        if ($user->role === Role::SuperAdmin) {
            $id = session(self::SESSION_KEY);

            return $id ? (int) $id : null;
        }

        return $user->company_id ? (int) $user->company_id : null;
    }

    public static function company(): ?Company
    {
        $id = static::id();

        return $id ? once(fn () => Company::find($id)) : null;
    }

    /**
     * True when tenant-scoped queries must be filtered. Any authenticated
     * request is scoped; a super admin with no company selected sees nothing
     * from tenant tables (fail-closed).
     */
    public static function shouldScope(): bool
    {
        return static::$forced || Auth::hasUser();
    }
}
