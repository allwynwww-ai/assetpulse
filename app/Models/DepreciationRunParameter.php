<?php

namespace App\Models;

class DepreciationRunParameter extends TenantModel
{
    public const SHIFT_FACTORS = ['single' => 1.0, 'double' => 1.5, 'triple' => 2.0];

    protected function casts(): array
    {
        return [
            'depreciate_in_month_of_capitalization' => 'boolean',
            'depreciate_in_month_of_disposal' => 'boolean',
            'auto_lock_on_finalize' => 'boolean',
            'is_default' => 'boolean',
            'low_value_threshold' => 'decimal:2',
            'rounding_precision' => 'integer',
        ];
    }

    public static function searchable(): array { return ['name', 'frequency', 'book']; }

    public static function default(): ?self
    {
        return static::where('is_default', true)->first() ?? static::first();
    }

    public function getShiftFactorAttribute(): float
    {
        return self::SHIFT_FACTORS[$this->shift_basis] ?? 1.0;
    }
}
