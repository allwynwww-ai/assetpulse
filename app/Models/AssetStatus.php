<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetStatus extends TenantModel
{
    public const ACTIVE = 'ACTIVE';
    public const UNDER_MAINTENANCE = 'UNDER_MAINTENANCE';
    public const DISPOSED = 'DISPOSED';
    public const SCRAPPED = 'SCRAPPED';
    public const TRANSFERRED = 'TRANSFERRED';

    protected function casts(): array
    {
        return ['is_depreciable' => 'boolean', 'is_system' => 'boolean', 'sort_order' => 'integer'];
    }

    public function assets(): HasMany { return $this->hasMany(Asset::class); }

    public static function idFor(string $code): ?int
    {
        return static::where('code', $code)->value('id');
    }
}
