<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\TenantFormRequest;

class AssetStatusRequest extends TenantFormRequest
{
    public const COLORS = ['emerald' => 'Green', 'sky' => 'Blue', 'amber' => 'Amber', 'rose' => 'Red', 'violet' => 'Violet', 'slate' => 'Grey', 'orange' => 'Orange', 'teal' => 'Teal'];

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(str_replace([' ', '-'], '_', trim((string) $this->code)))]);
        $this->booleans(['is_depreciable']);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'alpha_dash', $this->uniqueInCompany('asset_statuses', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'in:'.implode(',', array_keys(self::COLORS))],
            'is_depreciable' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }
}
