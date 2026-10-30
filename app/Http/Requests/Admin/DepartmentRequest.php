<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\TenantFormRequest;

class DepartmentRequest extends TenantFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->code))]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', $this->uniqueInCompany('departments', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'location_id' => ['nullable', 'integer', $this->existsInCompany('locations')],
            'head_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
