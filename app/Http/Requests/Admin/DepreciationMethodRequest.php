<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\TenantFormRequest;

class DepreciationMethodRequest extends TenantFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->code))]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:10', 'alpha_dash', $this->uniqueInCompany('depreciation_methods', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
