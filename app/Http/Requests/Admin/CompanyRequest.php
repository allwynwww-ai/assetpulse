<?php

namespace App\Http\Requests\Admin;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user?->isSuperAdmin()) {
            return true;
        }

        // Company admins may only edit their own company's profile.
        return $user?->isCompanyAdmin() && $this->isMethod('PUT') && (int) $this->companyId() === (int) $user->company_id;
    }

    protected function companyId(): ?int
    {
        return $this->route('id') ? (int) $this->route('id') : ($this->user()->isSuperAdmin() ? null : $this->user()->company_id);
    }

    protected function creating(): bool
    {
        return $this->isMethod('POST');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->code)),
            'gstin' => $this->gstin ? strtoupper(trim($this->gstin)) : null,
            'pan' => $this->pan ? strtoupper(trim($this->pan)) : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        $rules = [
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('companies', 'code')->ignore($this->companyId())],
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'cin' => ['nullable', 'regex:/^[LU][0-9]{5}[A-Z]{2}[0-9]{4}[A-Z]{3}[0-9]{6}$/'],
            'gstin' => ['nullable', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'pan' => ['nullable', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:12'],
            'currency' => ['required', 'string', 'size:3'],
            'fy_start_month' => ['required', 'integer', 'between:1,12'],
            'depreciation_basis' => ['required', 'in:companies_act,income_tax,ind_as'],
            'asset_code_separator' => ['nullable', 'string', 'max:3'],
            'asset_code_padding' => ['required', 'integer', 'between:2,8'],
            'is_active' => ['boolean'],
        ];

        if (! $this->user()->isSuperAdmin()) {
            unset($rules['is_active'], $rules['code']);
        }

        if ($this->creating()) {
            $rules += [
                'admin_name' => ['required', 'string', 'max:255'],
                'admin_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
                'admin_password' => ['required', Password::min(8)],
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'gstin.regex' => 'Enter a valid 15-character GSTIN.',
            'pan.regex' => 'Enter a valid PAN (e.g. AAACP1234Q).',
            'cin.regex' => 'Enter a valid 21-character CIN.',
        ];
    }
}
