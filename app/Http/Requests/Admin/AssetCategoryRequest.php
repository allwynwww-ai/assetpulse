<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\TenantFormRequest;

class AssetCategoryRequest extends TenantFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->code)),
            'asset_code_prefix' => strtoupper(trim((string) $this->asset_code_prefix)),
            'useful_life_months' => $this->useful_life_months ?: 0,
            'capitalization_threshold' => $this->capitalization_threshold ?: 0,
        ]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', $this->uniqueInCompany('asset_categories', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'asset_code_prefix' => ['required', 'string', 'max:10', 'alpha_dash'],
            'parent_id' => ['nullable', 'integer', $this->existsInCompany('asset_categories'), 'not_in:'.($this->recordId() ?? 0)],
            'description' => ['nullable', 'string', 'max:2000'],
            'depreciation_method_id' => ['required', 'integer', $this->existsInCompany('depreciation_methods')],
            'useful_life_years' => ['required', 'integer', 'min:0', 'max:100'],
            'useful_life_months' => ['integer', 'min:0', 'max:11'],
            'residual_value_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'wdv_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'schedule_ii_reference' => ['nullable', 'string', 'max:255'],
            'capitalization_threshold' => ['numeric', 'min:0', 'max:9999999999999999'],
            'gl_asset_account' => ['nullable', 'string', 'max:30'],
            'gl_accumulated_depreciation_account' => ['nullable', 'string', 'max:30'],
            'gl_depreciation_expense_account' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ((int) $this->useful_life_years === 0 && (int) $this->useful_life_months === 0) {
                $v->errors()->add('useful_life_years', 'Useful life must be greater than zero.');
            }
        });
    }
}
