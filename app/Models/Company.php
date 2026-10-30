<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use Auditable, Searchable, SoftDeletes;

    public static function searchable(): array
    {
        return ['code', 'name', 'legal_name', 'city', 'gstin'];
    }

    protected $fillable = [
        'code', 'name', 'legal_name', 'cin', 'gstin', 'pan', 'email', 'phone',
        'address_line1', 'address_line2', 'city', 'state', 'country', 'pincode',
        'currency', 'fy_start_month', 'depreciation_basis', 'asset_code_separator',
        'asset_code_padding', 'logo_path', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'fy_start_month' => 'integer', 'asset_code_padding' => 'integer'];
    }

    public function users(): HasMany { return $this->hasMany(User::class); }
    public function assets(): HasMany { return $this->hasMany(Asset::class); }
    public function assetCategories(): HasMany { return $this->hasMany(AssetCategory::class); }
    public function locations(): HasMany { return $this->hasMany(Location::class); }
    public function departments(): HasMany { return $this->hasMany(Department::class); }
    public function costCenters(): HasMany { return $this->hasMany(CostCenter::class); }
    public function vendors(): HasMany { return $this->hasMany(Vendor::class); }
    public function financialYears(): HasMany { return $this->hasMany(FinancialYear::class); }
    public function depreciationMethods(): HasMany { return $this->hasMany(DepreciationMethod::class); }
    public function assetStatuses(): HasMany { return $this->hasMany(AssetStatus::class); }
    public function depreciationRunParameters(): HasMany { return $this->hasMany(DepreciationRunParameter::class); }

    public function admins(): HasMany
    {
        return $this->hasMany(User::class)->where('role', \App\Enums\Role::CompanyAdmin->value);
    }

    public function getFullAddressAttribute(): string
    {
        return collect([$this->address_line1, $this->address_line2, $this->city, $this->state, $this->pincode])->filter()->implode(', ');
    }
}
