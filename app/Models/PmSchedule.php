<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class PmSchedule extends TenantModel
{
    public const FREQUENCY_UNITS = ['days' => 'Days', 'weeks' => 'Weeks', 'months' => 'Months', 'years' => 'Years', 'running_hours' => 'Running hours', 'shots' => 'Shots (mould)'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date', 'last_done_date' => 'date', 'next_due_date' => 'date',
            'estimated_cost' => 'decimal:2', 'estimated_hours' => 'decimal:2', 'is_active' => 'boolean',
            'frequency_interval' => 'integer', 'alert_days_before' => 'integer',
        ];
    }

    public static function searchable(): array { return ['code', 'title', 'assigned_to']; }

    public function asset(): BelongsTo { return $this->belongsTo(Asset::class)->withTrashed(); }
    public function vendor(): BelongsTo { return $this->belongsTo(Vendor::class)->withTrashed(); }
    public function workOrders(): HasMany { return $this->hasMany(WorkOrder::class); }

    public function scopeDueWithin($query, int $days)
    {
        return $query->where('is_active', true)->whereNotNull('next_due_date')
            ->where('next_due_date', '<=', now()->addDays($days)->toDateString());
    }

    /** Next due date from a given completion date (calendar based units only). */
    public function computeNextDue(?Carbon $from = null): ?Carbon
    {
        $from ??= $this->last_done_date ?? $this->start_date ?? now();
        $n = max(1, $this->frequency_interval);

        return match ($this->frequency_unit) {
            'days' => $from->copy()->addDays($n),
            'weeks' => $from->copy()->addWeeks($n),
            'months' => $from->copy()->addMonthsNoOverflow($n),
            'years' => $from->copy()->addYearsNoOverflow($n),
            default => null, // meter based – driven by readings
        };
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->next_due_date && $this->next_due_date->isPast() && ! $this->next_due_date->isToday();
    }
}
