<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetCategory extends TenantModel
{
    protected function casts(): array
    {
        return [
            'useful_life_years' => 'integer',
            'useful_life_months' => 'integer',
            'residual_value_percent' => 'decimal:2',
            'wdv_rate' => 'decimal:4',
            'capitalization_threshold' => 'decimal:2',
        ];
    }

    public static function searchable(): array
    {
        return ['code', 'name', 'asset_code_prefix', 'schedule_ii_reference'];
    }

    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id'); }
    public function depreciationMethod(): BelongsTo { return $this->belongsTo(DepreciationMethod::class)->withTrashed(); }
    public function assets(): HasMany { return $this->hasMany(Asset::class); }

    public function getTotalLifeMonthsAttribute(): int
    {
        return $this->useful_life_years * 12 + $this->useful_life_months;
    }

    public function getUsefulLifeLabelAttribute(): string
    {
        return trim(($this->useful_life_years ? $this->useful_life_years.' yrs ' : '').($this->useful_life_months ? $this->useful_life_months.' mths' : '')) ?: '—';
    }

    /**
     * WDV rate implied by Schedule II: rate = 1 - (residual/cost)^(1/n).
     */
    public static function computeWdvRate(float $lifeYears, float $residualPercent): ?float
    {
        if ($lifeYears <= 0) {
            return null;
        }
        $r = max($residualPercent, 0.01) / 100; // avoid 0 (rate would be 100%)

        return round((1 - pow($r, 1 / $lifeYears)) * 100, 4);
    }
}
