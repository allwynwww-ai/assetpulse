<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\AssetCategoryRequest;
use App\Models\AssetCategory;
use App\Models\DepreciationMethod;
use Illuminate\Database\Eloquent\Model;

class AssetCategoryController extends MasterController
{
    protected string $model = AssetCategory::class;
    protected string $request = AssetCategoryRequest::class;
    protected string $routeName = 'admin.asset-categories';
    protected string $title = 'Asset Categories';
    protected string $singular = 'Asset Category';
    protected string $description = 'Category defaults (method, Schedule II life, residual value, GL accounts) drive every new asset.';
    protected string $icon = 'tag';
    protected array $with = ['depreciationMethod', 'parent'];

    protected function query(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::query()->withCount('assets');
    }

    protected function filters(): array
    {
        return ['depreciation_method_id' => ['label' => 'Method', 'options' => DepreciationMethod::orderBy('code')->pluck('code', 'id')->all()]];
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Code', 'value' => fn ($r) => '<span class="font-mono text-xs font-semibold text-slate-700">'.e($r->code).'</span><div class="text-[11px] text-slate-400">Prefix '.e($r->asset_code_prefix).'</div>'],
            ['label' => 'Category', 'value' => fn ($r) => self::strong($r->name, $r->schedule_ii_reference)],
            ['label' => 'Method', 'value' => fn ($r) => self::badge($r->depreciationMethod?->code ?? '—', 'sky')],
            ['label' => 'Useful life', 'value' => fn ($r) => e($r->useful_life_label)],
            ['label' => 'Residual', 'class' => 'text-right', 'value' => fn ($r) => rtrim(rtrim(number_format((float) $r->residual_value_percent, 2), '0'), '.').'%'],
            ['label' => 'WDV rate', 'class' => 'text-right', 'value' => fn ($r) => $r->wdv_rate !== null ? number_format((float) $r->wdv_rate, 2).'%' : self::muted(null)],
            ['label' => 'Assets', 'class' => 'text-right', 'value' => fn ($r) => $r->assets_count],
        ];
    }

    protected function fields(Model $record): array
    {
        $parents = AssetCategory::query()->when($record->exists, fn ($q) => $q->whereKeyNot($record->id))->orderBy('name')->pluck('name', 'id')->all();
        $methods = DepreciationMethod::orderBy('code')->get()->mapWithKeys(fn ($m) => [$m->id => $m->label])->all();

        return [
            ['type' => 'section', 'label' => 'Category'],
            ['name' => 'code', 'label' => 'Code', 'required' => true, 'placeholder' => 'PM-IMM'],
            ['name' => 'name', 'label' => 'Name', 'required' => true, 'span' => 2],
            ['name' => 'asset_code_prefix', 'label' => 'Asset code prefix', 'required' => true, 'placeholder' => 'IMM', 'help' => 'New assets are numbered PREFIX-0001, PREFIX-0002…'],
            ['name' => 'parent_id', 'label' => 'Parent category', 'type' => 'select', 'options' => $parents, 'placeholder' => '— None —'],
            ['name' => 'capitalization_threshold', 'label' => 'Capitalisation threshold', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'prefix' => '₹'],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'span' => 'full', 'rows' => 2],
            ['type' => 'section', 'label' => 'Depreciation defaults', 'help' => 'Companies Act 2013 – Schedule II. Copied to each new asset; can be overridden per asset.'],
            ['name' => 'depreciation_method_id', 'label' => 'Method', 'type' => 'select', 'options' => $methods, 'required' => true],
            ['name' => 'useful_life_years', 'label' => 'Useful life (years)', 'type' => 'number', 'min' => 0, 'required' => true],
            ['name' => 'useful_life_months', 'label' => '+ months', 'type' => 'number', 'min' => 0, 'max' => 11],
            ['name' => 'residual_value_percent', 'label' => 'Residual value', 'type' => 'number', 'step' => '0.01', 'suffix' => '%', 'required' => true, 'value' => old('residual_value_percent', $record->residual_value_percent ?? 5)],
            ['name' => 'wdv_rate', 'label' => 'WDV rate', 'type' => 'number', 'step' => '0.0001', 'suffix' => '%', 'help' => 'Leave blank to compute 1 − (residual/cost)^(1/life).'],
            ['name' => 'schedule_ii_reference', 'label' => 'Schedule II reference', 'placeholder' => 'Part C, Item II(i)'],
            ['type' => 'section', 'label' => 'GL accounts'],
            ['name' => 'gl_asset_account', 'label' => 'Asset account'],
            ['name' => 'gl_accumulated_depreciation_account', 'label' => 'Accumulated depreciation'],
            ['name' => 'gl_depreciation_expense_account', 'label' => 'Depreciation expense'],
        ];
    }

    protected function prepare(array $data, ?Model $record): array
    {
        if ($data['wdv_rate'] === null || $data['wdv_rate'] === '') {
            $years = (int) $data['useful_life_years'] + ((int) ($data['useful_life_months'] ?? 0)) / 12;
            $data['wdv_rate'] = AssetCategory::computeWdvRate($years, (float) $data['residual_value_percent']);
        }

        return $data;
    }

    protected function blockDeactivation(Model $record): ?string
    {
        return $record->assets()->exists() ? 'This category has assets. Reclassify them before deactivating.' : null;
    }
}
