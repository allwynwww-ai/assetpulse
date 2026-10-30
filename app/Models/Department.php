<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends TenantModel
{
    public static function searchable(): array { return ['code', 'name', 'head_name']; }

    public function location(): BelongsTo { return $this->belongsTo(Location::class)->withTrashed(); }
    public function costCenters(): HasMany { return $this->hasMany(CostCenter::class); }
    public function assets(): HasMany { return $this->hasMany(Asset::class); }
}
