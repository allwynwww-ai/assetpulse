<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DepreciationRun extends TenantModel
{
    protected function casts(): array
    {
        return [
            'period_start' => 'date', 'period_end' => 'date', 'run_at' => 'datetime',
            'finalized_at' => 'datetime', 'reversed_at' => 'datetime',
            'total_depreciation' => 'decimal:2', 'total_assets' => 'integer',
        ];
    }

    public static function searchable(): array { return ['run_number', 'period_label', 'status']; }

    public function financialYear(): BelongsTo { return $this->belongsTo(FinancialYear::class)->withTrashed(); }
    public function parameter(): BelongsTo { return $this->belongsTo(DepreciationRunParameter::class, 'depreciation_run_parameter_id')->withTrashed(); }
    public function entries(): HasMany { return $this->hasMany(DepreciationEntry::class); }
    public function periodLock(): HasOne { return $this->hasOne(PeriodLock::class); }
    public function runBy(): BelongsTo { return $this->belongsTo(User::class, 'run_by')->withTrashed(); }
    public function finalizedBy(): BelongsTo { return $this->belongsTo(User::class, 'finalized_by')->withTrashed(); }

    public function isEditable(): bool { return $this->status === 'draft'; }
}
