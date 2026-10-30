<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Base class for every company-owned record: tenant isolation, audit trail
 * and soft deletes ("deactivate") come for free.
 */
abstract class TenantModel extends Model
{
    use Auditable, BelongsToCompany, Searchable, SoftDeletes;

    /** company_id is always set by BelongsToCompany, never mass-assigned. */
    protected $guarded = ['id', 'company_id', 'created_at', 'updated_at', 'deleted_at'];
}
