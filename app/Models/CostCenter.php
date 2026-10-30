<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CostCenter extends TenantModel
{
    public static function searchable(): array { return ['code', 'name', 'gl_code']; }

    public function department(): BelongsTo { return $this->belongsTo(Department::class)->withTrashed(); }
    public function assets(): HasMany { return $this->hasMany(Asset::class); }
}
