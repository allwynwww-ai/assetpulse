<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\TenantFormRequest;

class CostCenterRequest extends TenantFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->code))]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', $this->uniqueInCompany('cost_centers', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', $this->existsInCompany('departments')],
            'gl_code' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
