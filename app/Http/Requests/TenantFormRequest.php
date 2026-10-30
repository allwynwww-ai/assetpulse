<?php

namespace App\Http\Requests;

use App\Support\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Base request for tenant data: unique / exists rules are always confined
 * to the current company so IDs from another tenant can never be referenced.
 */
abstract class TenantFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canManageMasters();
    }

    protected function recordId(): ?int
    {
        $id = $this->route('id') ?? $this->route('asset')?->id ?? null;

        return $id ? (int) (is_object($id) ? $id->id : $id) : null;
    }

    protected function uniqueInCompany(string $table, string $column): Unique
    {
        return Rule::unique($table, $column)->where('company_id', Tenant::id())->ignore($this->recordId());
    }

    protected function existsInCompany(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->where('company_id', Tenant::id())->whereNull('deleted_at');
    }

    /** Convert unchecked checkboxes to false. */
    protected function booleans(array $fields): void
    {
        $merge = [];
        foreach ($fields as $f) {
            $merge[$f] = $this->boolean($f);
        }
        $this->merge($merge);
    }
}
