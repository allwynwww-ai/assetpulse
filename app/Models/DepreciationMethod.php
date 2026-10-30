<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class DepreciationMethod extends TenantModel
{
    public const SLM = 'SLM';
    public const WDV = 'WDV';
    public const UOP = 'UOP';

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function assetCategories(): HasMany { return $this->hasMany(AssetCategory::class); }
    public function assets(): HasMany { return $this->hasMany(Asset::class); }

    public function getLabelAttribute(): string { return "{$this->code} – {$this->name}"; }
}
