<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\TenantFormRequest;

class VendorRequest extends TenantFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->code)),
            'gstin' => $this->gstin ? strtoupper(trim($this->gstin)) : null,
            'pan' => $this->pan ? strtoupper(trim($this->pan)) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', $this->uniqueInCompany('vendors', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'gstin' => ['nullable', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'pan' => ['nullable', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:12'],
            'website' => ['nullable', 'url', 'max:255'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ];
    }

    public function messages(): array
    {
        return ['gstin.regex' => 'Enter a valid 15-character GSTIN.', 'pan.regex' => 'Enter a valid PAN (e.g. AAACP1234Q).'];
    }
}
