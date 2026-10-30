<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class FinancialYear extends TenantModel
{
    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'is_current' => 'boolean', 'closed_at' => 'datetime'];
    }

    public static function searchable(): array { return ['name', 'status']; }

    public function closedBy(): BelongsTo { return $this->belongsTo(User::class, 'closed_by'); }
    public function depreciationRuns(): HasMany { return $this->hasMany(DepreciationRun::class); }
    public function periodLocks(): HasMany { return $this->hasMany(PeriodLock::class); }

    public static function current(): ?self
    {
        return static::where('is_current', true)->first()
            ?? static::forDate(now());
    }

    public static function forDate($date): ?self
    {
        $d = Carbon::parse($date)->toDateString();

        return static::where('start_date', '<=', $d)->where('end_date', '>=', $d)->first();
    }

    public function contains($date): bool
    {
        return Carbon::parse($date)->betweenIncluded($this->start_date, $this->end_date);
    }
}
