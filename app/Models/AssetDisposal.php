<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetDisposal extends TenantModel
{
    public const TYPES = ['sale' => 'Sale', 'scrap' => 'Scrap', 'write_off' => 'Write-off', 'donation' => 'Donation', 'theft_loss' => 'Theft / Loss', 'insurance_claim' => 'Insurance claim'];

    protected function casts(): array
    {
        return [
            'disposal_date' => 'date', 'approved_at' => 'datetime',
            'sale_value' => 'decimal:2', 'disposal_cost' => 'decimal:2', 'gross_block_at_disposal' => 'decimal:2',
            'accumulated_depreciation_at_disposal' => 'decimal:2', 'depreciation_upto_disposal' => 'decimal:2',
            'nbv_at_disposal' => 'decimal:2', 'profit_loss' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (AssetDisposal $d) {
            $d->nbv_at_disposal = round((float) $d->gross_block_at_disposal - (float) $d->accumulated_depreciation_at_disposal, 2);
            $d->profit_loss = round((float) $d->sale_value - (float) $d->disposal_cost - (float) $d->nbv_at_disposal, 2);
        });
    }

    public static function searchable(): array { return ['disposal_number', 'buyer_name', 'sale_invoice_number']; }

    public function asset(): BelongsTo { return $this->belongsTo(Asset::class)->withTrashed(); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by')->withTrashed(); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by')->withTrashed(); }
}
