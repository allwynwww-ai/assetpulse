<?php

namespace App\Http\Requests;

use App\Models\DepreciationMethod;

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
            $method = DepreciationMethod::find($this->depreciation_method_id);
            if ($method?->code === DepreciationMethod::UOP && ! ((float) $this->total_units_capacity > 0)) {
                $v->errors()->add('total_units_capacity', 'Units of Production method needs total units capacity (e.g. lifetime shots).');
            }
        });
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
