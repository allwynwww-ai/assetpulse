<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepreciationEntry extends TenantModel
{
    protected function casts(): array
    {
        return [
            'period_start' => 'date', 'period_end' => 'date',
            'opening_gross_block' => 'decimal:2', 'opening_accumulated_depreciation' => 'decimal:2',
            'opening_wdv' => 'decimal:2', 'rate' => 'decimal:4', 'shift_factor' => 'decimal:2',
            'depreciation_amount' => 'decimal:2', 'closing_accumulated_depreciation' => 'decimal:2',
            'closing_wdv' => 'decimal:2', 'calculation_details' => 'array', 'is_reversal' => 'boolean',
            'days' => 'integer',
        ];
    }

    public static function searchable(): array { return ['method_code']; }

    public function run(): BelongsTo { return $this->belongsTo(DepreciationRun::class, 'depreciation_run_id'); }
    public function asset(): BelongsTo { return $this->belongsTo(Asset::class)->withTrashed(); }
    public function financialYear(): BelongsTo { return $this->belongsTo(FinancialYear::class)->withTrashed(); }
    public function costCenter(): BelongsTo { return $this->belongsTo(CostCenter::class)->withTrashed(); }
    public function location(): BelongsTo { return $this->belongsTo(Location::class)->withTrashed(); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by')->withTrashed(); }
}
