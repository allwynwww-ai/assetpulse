<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Models\Scopes\CompanyScope;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\UnauthorizedException;

/**
 * Tenant isolation for every company-owned table.
 *  - Adds a global scope filtering by the current tenant.
 *  - Auto-fills company_id on create.
 *  - Refuses to write a row into a different company than the active tenant.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            $tenantId = Tenant::id();
            if (empty($model->company_id)) {
                $model->company_id = $tenantId;
            }
            if ($tenantId !== null && (int) $model->company_id !== $tenantId) {
                throw new UnauthorizedException('Cross-tenant write blocked.');
            }
            if (empty($model->company_id)) {
                throw new UnauthorizedException('company_id is required for '.static::class);
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty('company_id')) {
                throw new UnauthorizedException('company_id cannot be changed.');
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
