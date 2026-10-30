<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrder extends TenantModel
{
    public const TYPES = ['preventive' => 'Preventive (planned)', 'breakdown' => 'Breakdown', 'corrective' => 'Corrective', 'inspection' => 'Inspection'];
    public const STATUSES = ['open' => 'Open', 'in_progress' => 'In progress', 'on_hold' => 'On hold', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
    public const PRIORITIES = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'];

    protected function casts(): array
    {
        return [
            'reported_date' => 'date', 'scheduled_date' => 'date', 'started_at' => 'datetime', 'completed_at' => 'datetime',
            'downtime_hours' => 'decimal:2', 'labour_cost' => 'decimal:2', 'parts_cost' => 'decimal:2',
            'external_cost' => 'decimal:2', 'total_cost' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (WorkOrder $wo) {
            $wo->total_cost = round((float) $wo->labour_cost + (float) $wo->parts_cost + (float) $wo->external_cost, 2);
        });
    }

    public static function searchable(): array { return ['wo_number', 'title', 'assigned_to']; }

    public function asset(): BelongsTo { return $this->belongsTo(Asset::class)->withTrashed(); }
    public function pmSchedule(): BelongsTo { return $this->belongsTo(PmSchedule::class)->withTrashed(); }
    public function vendor(): BelongsTo { return $this->belongsTo(Vendor::class)->withTrashed(); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by')->withTrashed(); }
    public function completer(): BelongsTo { return $this->belongsTo(User::class, 'completed_by')->withTrashed(); }
}
