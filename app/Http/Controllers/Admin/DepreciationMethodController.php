<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\DepreciationMethodRequest;
use App\Models\DepreciationMethod;
use Illuminate\Database\Eloquent\Model;

class DepreciationMethodController extends MasterController
{
    protected string $model = DepreciationMethod::class;
    protected string $request = DepreciationMethodRequest::class;
    protected string $routeName = 'admin.depreciation-methods';
    protected string $title = 'Depreciation Methods';
    protected string $singular = 'Depreciation Method';
    protected string $description = 'Straight Line, Written Down Value and Units of Production methods.';
    protected string $icon = 'calculator';

    protected function query(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::query()->withCount('assetCategories');
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Code', 'value' => fn ($r) => '<span class="font-mono text-xs font-semibold text-brand-700">'.e($r->code).'</span>'],
            ['label' => 'Name', 'value' => fn ($r) => self::strong($r->name, $r->description ? \Illuminate\Support\Str::limit($r->description, 90) : null)],
            ['label' => 'Categories', 'class' => 'text-right', 'value' => fn ($r) => $r->asset_categories_count],
            ['label' => 'Type', 'value' => fn ($r) => $r->is_system ? self::badge('System', 'sky') : self::badge('Custom', 'slate')],
        ];
    }

    protected function fields(Model $record): array
    {
        return [
            ['name' => 'code', 'label' => 'Code', 'required' => true, 'maxlength' => 10, 'disabled' => (bool) $record->is_system, 'help' => $record->is_system ? 'System codes are used by the depreciation engine and cannot be changed.' : 'Short code, e.g. SLM.'],
            ['name' => 'name', 'label' => 'Name', 'required' => true, 'span' => 2],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'span' => 'full'],
        ];
    }

    protected function prepare(array $data, ?Model $record): array
    {
        if ($record?->is_system) {
            unset($data['code']);
        }

        return $data;
    }

    protected function blockDeactivation(Model $record): ?string
    {
        if ($record->is_system) {
            return 'System depreciation methods cannot be deactivated.';
        }
        if ($record->assetCategories()->exists()) {
            return 'This method is used by asset categories. Reassign them first.';
        }

        return null;
    }
}
