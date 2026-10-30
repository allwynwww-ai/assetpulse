<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Asset extends TenantModel
{
    protected $auditExclude = [];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'capitalization_date' => 'date',
            'invoice_date' => 'date',
            'opening_balance_date' => 'date',
            'last_depreciation_date' => 'date',
            'warranty_expiry_date' => 'date',
            'insurance_expiry_date' => 'date',
            'original_cost' => 'decimal:2',
            'residual_value' => 'decimal:2',
            'opening_accumulated_depreciation' => 'decimal:2',
            'accumulated_depreciation' => 'decimal:2',
            'current_book_value' => 'decimal:2',
            'wdv_rate' => 'decimal:4',
            'total_units_capacity' => 'decimal:2',
            'units_consumed' => 'decimal:2',
            'useful_life_years' => 'integer',
            'useful_life_months' => 'integer',
            'quantity' => 'integer',
        ];
    }

    public static function searchable(): array
    {
        return ['asset_code', 'name', 'description', 'serial_number', 'asset_tag', 'invoice_number', 'purchase_order_number', 'manufacturer', 'model'];
    }

    protected static function booted(): void
    {
        static::saving(function (Asset $asset) {
            // Until the depreciation engine posts entries, accumulated = opening (takeover) balance.
            if ($asset->last_depreciation_date === null) {
                $asset->accumulated_depreciation = $asset->opening_accumulated_depreciation ?? 0;
            }
            $asset->current_book_value = round((float) $asset->original_cost - (float) $asset->accumulated_depreciation, 2);
        });
    }

    // Relationships -------------------------------------------------------
    public function category(): BelongsTo { return $this->belongsTo(AssetCategory::class, 'asset_category_id')->withTrashed(); }
    public function location(): BelongsTo { return $this->belongsTo(Location::class)->withTrashed(); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class)->withTrashed(); }
    public function costCenter(): BelongsTo { return $this->belongsTo(CostCenter::class)->withTrashed(); }
    public function vendor(): BelongsTo { return $this->belongsTo(Vendor::class)->withTrashed(); }
    public function depreciationMethod(): BelongsTo { return $this->belongsTo(DepreciationMethod::class)->withTrashed(); }
    public function status(): BelongsTo { return $this->belongsTo(AssetStatus::class, 'asset_status_id')->withTrashed(); }
    public function parentAsset(): BelongsTo { return $this->belongsTo(self::class, 'parent_asset_id'); }
    public function components(): HasMany { return $this->hasMany(self::class, 'parent_asset_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by')->withTrashed(); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by')->withTrashed(); }
    public function attachments(): HasMany { return $this->hasMany(AssetAttachment::class)->latest(); }
    public function depreciationEntries(): HasMany { return $this->hasMany(DepreciationEntry::class)->orderBy('period_end'); }
    public function transfers(): HasMany { return $this->hasMany(AssetTransfer::class)->latest('transfer_date'); }
    public function pmSchedules(): HasMany { return $this->hasMany(PmSchedule::class); }
    public function workOrders(): HasMany { return $this->hasMany(WorkOrder::class)->latest('reported_date'); }
    public function disposals(): HasMany { return $this->hasMany(AssetDisposal::class); }

    // Derived values ------------------------------------------------------
    public function getTotalLifeMonthsAttribute(): int
    {
        return (int) $this->useful_life_years * 12 + (int) $this->useful_life_months;
    }

    public function getUsefulLifeLabelAttribute(): string
    {
        return trim(($this->useful_life_years ? $this->useful_life_years.' yrs ' : '').($this->useful_life_months ? $this->useful_life_months.' mths' : '')) ?: '—';
    }

    public function getDepreciableAmountAttribute(): float
    {
        return max(0, (float) $this->original_cost - (float) $this->residual_value);
    }

    public function getEndOfLifeDateAttribute(): ?Carbon
    {
        if (! $this->capitalization_date || $this->total_life_months <= 0) {
            return null;
        }

        return $this->capitalization_date->copy()->addMonthsNoOverflow($this->total_life_months)->subDay();
    }

    public function getRemainingLifeMonthsAttribute(): ?int
    {
        $eol = $this->end_of_life_date;
        if (! $eol) {
            return null;
        }

        return max(0, (int) floor(now()->diffInMonths($eol, false)));
    }

    public function getLifeConsumedPercentAttribute(): float
    {
        if (! $this->capitalization_date || $this->total_life_months <= 0) {
            return 0;
        }
        $used = $this->capitalization_date->diffInMonths(now());

        return round(min(100, max(0, $used / $this->total_life_months * 100)), 1);
    }

    public function getDepreciatedPercentAttribute(): float
    {
        $base = $this->depreciable_amount;

        return $base > 0 ? round(min(100, (float) $this->accumulated_depreciation / $base * 100), 1) : 0;
    }

    public function isDisposed(): bool
    {
        return in_array($this->status?->code, [AssetStatus::DISPOSED, AssetStatus::SCRAPPED], true);
    }

    /** Public URL encoded in the QR label. */
    public function getQrUrlAttribute(): string
    {
        return route('assets.show', $this);
    }
}
