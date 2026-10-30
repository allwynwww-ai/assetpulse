<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class PeriodLock extends TenantModel
{
    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'is_locked' => 'boolean', 'locked_at' => 'datetime', 'unlocked_at' => 'datetime'];
    }

    public static function searchable(): array { return ['period_label']; }

    public function financialYear(): BelongsTo { return $this->belongsTo(FinancialYear::class)->withTrashed(); }
    public function run(): BelongsTo { return $this->belongsTo(DepreciationRun::class, 'depreciation_run_id'); }
    public function lockedBy(): BelongsTo { return $this->belongsTo(User::class, 'locked_by')->withTrashed(); }

    /** Is the given date inside a locked period for the current tenant? */
    public static function isLocked($date): bool
    {
        $d = Carbon::parse($date)->toDateString();

        return static::where('is_locked', true)->where('period_start', '<=', $d)->where('period_end', '>=', $d)->exists();
    }
}
