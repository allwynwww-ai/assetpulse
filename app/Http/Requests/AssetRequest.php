<?php

namespace App\Http\Requests;

use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\DepreciationEntry;
use App\Models\DepreciationMethod;
use App\Services\PeriodGuard;
use Illuminate\Support\Carbon;

class AssetRequest extends TenantFormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canManageAssets();
    }

    protected function recordId(): ?int
    {
        $a = $this->route('asset');

        return $a ? (int) (is_object($a) ? $a->id : $a) : null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'asset_code' => $this->asset_code ? strtoupper(trim($this->asset_code)) : null,
            'useful_life_months' => $this->useful_life_months ?: 0,
            'residual_value' => $this->residual_value ?: 0,
            'opening_accumulated_depreciation' => $this->opening_accumulated_depreciation ?: 0,
            'units_consumed' => $this->units_consumed ?: 0,
            'quantity' => $this->quantity ?: 1,
            'unit_of_measure' => $this->unit_of_measure ?: 'Nos',
        ]);
    }

    public function rules(): array
    {
        return [
            'asset_code' => ['nullable', 'string', 'max:40', 'regex:/^[A-Z0-9][A-Z0-9\-\/_.]*$/', $this->uniqueInCompany('assets', 'asset_code')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'asset_category_id' => ['required', 'integer', $this->existsInCompany('asset_categories')],
            'asset_status_id' => ['required', 'integer', $this->existsInCompany('asset_statuses')],
            'depreciation_method_id' => ['required', 'integer', $this->existsInCompany('depreciation_methods')],
            'location_id' => ['nullable', 'integer', $this->existsInCompany('locations')],
            'department_id' => ['nullable', 'integer', $this->existsInCompany('departments')],
            'cost_center_id' => ['nullable', 'integer', $this->existsInCompany('cost_centers')],
            'vendor_id' => ['nullable', 'integer', $this->existsInCompany('vendors')],
            'parent_asset_id' => ['nullable', 'integer', $this->existsInCompany('assets'), 'not_in:'.($this->recordId() ?? 0)],
            'purchase_date' => ['nullable', 'date', 'before_or_equal:today'],
            'capitalization_date' => ['required', 'date', 'after_or_equal:purchase_date'],
            'invoice_number' => ['nullable', 'string', 'max:60'],
            'invoice_date' => ['nullable', 'date'],
            'purchase_order_number' => ['nullable', 'string', 'max:60'],
            'original_cost' => ['required', 'numeric', 'gt:0', 'max:9999999999999999'],
            'residual_value' => ['numeric', 'min:0', 'lte:original_cost'],
            'useful_life_years' => ['required', 'integer', 'min:0', 'max:100'],
            'useful_life_months' => ['integer', 'min:0', 'max:11'],
            'wdv_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'total_units_capacity' => ['nullable', 'numeric', 'min:0'],
            'units_consumed' => ['numeric', 'min:0'],
            'opening_balance_date' => ['nullable', 'date', 'required_unless:opening_accumulated_depreciation,0'],
            'opening_accumulated_depreciation' => ['numeric', 'min:0'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'asset_tag' => ['nullable', 'string', 'max:60'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'quantity' => ['integer', 'min:1', 'max:1000000'],
            'unit_of_measure' => ['string', 'max:20'],
            'custodian' => ['nullable', 'string', 'max:255'],
            'warranty_expiry_date' => ['nullable', 'date'],
            'insurance_policy_number' => ['nullable', 'string', 'max:60'],
            'insurance_expiry_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx'],
            'attachment_type' => ['nullable', 'in:invoice,photo,warranty,insurance,manual,other'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            if ((int) $this->useful_life_years === 0 && (int) $this->useful_life_months === 0) {
                $v->errors()->add('useful_life_years', 'Useful life must be greater than zero.');
            }
            $maxOpening = (float) $this->original_cost - (float) $this->residual_value;
            if ((float) $this->opening_accumulated_depreciation > $maxOpening + 0.001) {
                $v->errors()->add('opening_accumulated_depreciation', 'Opening accumulated depreciation cannot exceed cost less residual value ('.inr($maxOpening).').');
            }
            $this->periodGuards($v);
            $method = DepreciationMethod::find($this->depreciation_method_id);
            if ($method?->code === DepreciationMethod::UOP && ! ((float) $this->total_units_capacity > 0)) {
                $v->errors()->add('total_units_capacity', 'Units of Production method needs total units capacity (e.g. lifetime shots).');
            }
        });
    }

    /**
     * Depreciation integrity:
     *  - disposed / scrapped assets are read-only;
     *  - "Disposed"/"Scrapped" can only be reached through the disposal workflow;
     *  - a new asset cannot be capitalised inside a finalised (locked) period;
     *  - once depreciation has been posted, the put-to-use date and the value basis
     *    (cost, residual, life, method, rate, opening balance) are frozen: reverse the
     *    open run(s) first, or – for finalised periods – ask an admin to unlock.
     */
    protected function periodGuards($v): void
    {
        $asset = $this->route('asset');
        $statusCode = AssetStatus::find($this->asset_status_id)?->code;
        $closing = [AssetStatus::DISPOSED, AssetStatus::SCRAPPED];

        if (! $asset instanceof Asset) {
            if (in_array($statusCode, $closing, true)) {
                $v->errors()->add('asset_status_id', 'A new asset cannot be created as disposed or scrapped.');
            }
            if (PeriodGuard::isLocked($this->capitalization_date)) {
                $v->errors()->add('capitalization_date', 'The put-to-use date '.fmt_date($this->capitalization_date).' falls in a finalised depreciation period (locked through '.fmt_date(PeriodGuard::lockedThrough()).'). Use a later date or ask a Company Admin to unlock the period.');
            }

            return;
        }

        if ($asset->isDisposed()) {
            $v->errors()->add('name', 'This asset has been disposed and is read-only.');

            return;
        }
        if (in_array($statusCode, $closing, true) && ! in_array($asset->status?->code, $closing, true)) {
            $v->errors()->add('asset_status_id', 'Use the Dispose action to sell or scrap an asset – it books the pro-rata depreciation and profit / loss.');
        }
        if ($statusCode !== $asset->status?->code && $asset->status?->code === AssetStatus::UNDER_MAINTENANCE && $asset->workOrders()->where('type', 'breakdown')->whereIn('status', ['open', 'in_progress', 'on_hold'])->exists()) {
            $v->errors()->add('asset_status_id', 'The asset has an open breakdown work order; complete or cancel it to change the status.');
        }

        $entries = DepreciationEntry::where('asset_id', $asset->id);
        if (! (clone $entries)->exists()) {
            if ($this->dateChanged($asset->capitalization_date, $this->capitalization_date) && PeriodGuard::isLocked($this->capitalization_date)) {
                $v->errors()->add('capitalization_date', 'The put-to-use date falls in a finalised (locked) period.');
            }

            return;
        }
        $finalised = (clone $entries)->whereHas('run', fn ($q) => $q->where('status', 'finalized'))->exists();
        $why = $finalised
            ? 'is locked because depreciation for this asset has been finalised (locked through '.fmt_date(PeriodGuard::lockedThrough()).'). A Company Admin must unlock the periods and reverse the runs first.'
            : 'cannot change after depreciation has been posted. Reverse the open depreciation run(s) first.';

        if ($this->dateChanged($asset->capitalization_date, $this->capitalization_date)) {
            $v->errors()->add('capitalization_date', 'The put-to-use date '.$why);
        }
        $money = ['original_cost' => 'Original cost', 'residual_value' => 'Residual value', 'opening_accumulated_depreciation' => 'Opening accumulated depreciation'];
        foreach ($money as $f => $label) {
            if (abs((float) $asset->{$f} - (float) $this->input($f)) > 0.004) {
                $v->errors()->add($f, "{$label} {$why}");
            }
        }
        foreach (['useful_life_years' => 'Useful life', 'useful_life_months' => 'Useful life', 'depreciation_method_id' => 'Depreciation method'] as $f => $label) {
            if ((int) $asset->{$f} !== (int) $this->input($f) && ! $v->errors()->has($f)) {
                $v->errors()->add($f, "{$label} {$why}");
            }
        }
        if ($this->filled('wdv_rate') && $asset->wdv_rate !== null && abs((float) $asset->wdv_rate - (float) $this->wdv_rate) > 0.00005) {
            $v->errors()->add('wdv_rate', "WDV rate {$why}");
        }
        if ($this->dateChanged($asset->opening_balance_date, $this->opening_balance_date)) {
            $v->errors()->add('opening_balance_date', "Opening balance date {$why}");
        }
    }

    private function dateChanged($old, $new): bool
    {
        $o = $old ? Carbon::parse($old)->toDateString() : null;
        $n = $new ? Carbon::parse($new)->toDateString() : null;

        return $o !== $n;
    }

    public function attributes(): array
    {
        return [
            'asset_category_id' => 'category', 'asset_status_id' => 'status', 'depreciation_method_id' => 'depreciation method',
            'location_id' => 'location', 'department_id' => 'department', 'cost_center_id' => 'cost center', 'vendor_id' => 'vendor',
            'capitalization_date' => 'put-to-use date', 'original_cost' => 'original cost', 'attachments.*' => 'attachment',
        ];
    }

    public function messages(): array
    {
        return ['asset_code.regex' => 'Asset code may contain only letters, numbers, - / _ and .'];
    }
}
