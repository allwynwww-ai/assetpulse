<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\TenantFormRequest;
use App\Models\Location;

class LocationRequest extends TenantFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->code)), 'gstin' => $this->gstin ? strtoupper($this->gstin) : null]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', $this->uniqueInCompany('locations', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:'.implode(',', array_keys(Location::TYPES))],
            'parent_id' => ['nullable', 'integer', $this->existsInCompany('locations'), 'not_in:'.($this->recordId() ?? 0)],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'regex:/^[1-9][0-9]{5}$/'],
            'gstin' => ['nullable', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return ['pincode.regex' => 'Enter a valid 6-digit PIN code.', 'gstin.regex' => 'Enter a valid 15-character GSTIN.', 'parent_id.not_in' => 'A location cannot be its own parent.'];
    }
}
