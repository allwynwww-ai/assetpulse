<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Support\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canManageMasters();
    }

    public function rules(): array
    {
        $isSuper = $this->user()->isSuperAdmin();
        $id = $this->route('id');
        $roles = array_keys(Role::options($isSuper && Tenant::id() === null));

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'role' => ['required', Rule::in($roles)],
            // Only the super admin (outside a company) chooses the company; everyone else is pinned to the tenant.
            'company_id' => [Rule::requiredIf($isSuper && Tenant::id() === null && $this->input('role') !== Role::SuperAdmin->value), 'nullable', 'integer', Rule::exists('companies', 'id')->whereNull('deleted_at')],
            'designation' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => [$id ? 'nullable' : 'required', 'confirmed', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return ['company_id.required' => 'Choose the company this user belongs to.'];
    }
}
