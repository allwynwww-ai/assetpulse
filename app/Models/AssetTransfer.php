<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetTransfer extends TenantModel
{
    protected function casts(): array
    {
        return ['transfer_date' => 'date', 'approved_at' => 'datetime', 'affects_depreciation' => 'boolean'];
    }

    public static function searchable(): array { return ['transfer_number', 'reason']; }

    public function asset(): BelongsTo { return $this->belongsTo(Asset::class)->withTrashed(); }
    public function fromLocation(): BelongsTo { return $this->belongsTo(Location::class, 'from_location_id')->withTrashed(); }
    public function toLocation(): BelongsTo { return $this->belongsTo(Location::class, 'to_location_id')->withTrashed(); }
    public function fromDepartment(): BelongsTo { return $this->belongsTo(Department::class, 'from_department_id')->withTrashed(); }
    public function toDepartment(): BelongsTo { return $this->belongsTo(Department::class, 'to_department_id')->withTrashed(); }
    public function fromCostCenter(): BelongsTo { return $this->belongsTo(CostCenter::class, 'from_cost_center_id')->withTrashed(); }
    public function toCostCenter(): BelongsTo { return $this->belongsTo(CostCenter::class, 'to_cost_center_id')->withTrashed(); }
    public function requestedBy(): BelongsTo { return $this->belongsTo(User::class, 'requested_by')->withTrashed(); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by')->withTrashed(); }
}
