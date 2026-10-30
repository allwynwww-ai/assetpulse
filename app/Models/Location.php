<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends TenantModel
{
    public const TYPES = ['plant' => 'Plant', 'warehouse' => 'Warehouse', 'office' => 'Office', 'site' => 'Site', 'area' => 'Area / Shop floor'];

    public static function searchable(): array { return ['code', 'name', 'city', 'state']; }

    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id'); }
    public function departments(): HasMany { return $this->hasMany(Department::class); }
    public function assets(): HasMany { return $this->hasMany(Asset::class); }
}
