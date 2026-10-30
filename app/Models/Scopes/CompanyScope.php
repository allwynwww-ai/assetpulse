<?php

namespace App\Models\Scopes;

use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! Tenant::shouldScope()) {
            return; // console / seeders without an explicit tenant
        }

        $companyId = Tenant::id();
        $column = $model->qualifyColumn('company_id');

        if ($companyId === null) {
            // Fail closed: authenticated but no tenant resolved.
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($column, $companyId);
    }
}
