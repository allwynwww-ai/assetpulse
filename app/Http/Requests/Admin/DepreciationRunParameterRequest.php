<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\TenantFormRequest;

class DepreciationRunParameterRequest extends TenantFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->booleans(['depreciate_in_month_of_capitalization', 'depreciate_in_month_of_disposal', 'auto_lock_on_finalize', 'is_default']);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', $this->uniqueInCompany('depreciation_run_parameters', 'name')],
            'frequency' => ['required', 'in:monthly,quarterly,yearly'],
            'book' => ['required', 'in:companies_act,income_tax'],
            'proration_basis' => ['required', 'in:days,months'],
            'shift_basis' => ['required', 'in:single,double,triple'],
            'low_value_threshold' => ['required', 'numeric', 'min:0'],
            'rounding_precision' => ['required', 'integer', 'min:0', 'max:4'],
            'depreciate_in_month_of_capitalization' => ['boolean'],
            'depreciate_in_month_of_disposal' => ['boolean'],
            'auto_lock_on_finalize' => ['boolean'],
            'is_default' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
