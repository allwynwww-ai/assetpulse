<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\CostCenterRequest;
use App\Models\CostCenter;
use App\Models\Department;
use Illuminate\Database\Eloquent\Model;

class CostCenterController extends MasterController
{
    protected string $model = CostCenter::class;
    protected string $request = CostCenterRequest::class;
    protected string $routeName = 'admin.cost-centers';
    protected string $title = 'Cost Centers';
    protected string $singular = 'Cost Center';
    protected string $description = 'Cost centers to which depreciation is charged.';
    protected string $icon = 'banknotes';
    protected array $with = ['department'];

    protected function query(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::query()->withCount('assets');
    }

    protected function filters(): array
    {
        return ['department_id' => ['label' => 'Department', 'options' => Department::orderBy('name')->pluck('name', 'id')->all()]];
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Code', 'value' => fn ($r) => '<span class="font-mono text-xs font-semibold text-slate-700">'.e($r->code).'</span>'],
            ['label' => 'Name', 'value' => fn ($r) => self::strong($r->name)],
            ['label' => 'Department', 'value' => fn ($r) => self::muted($r->department?->name)],
            ['label' => 'GL code', 'value' => fn ($r) => self::muted($r->gl_code)],
            ['label' => 'Assets', 'class' => 'text-right', 'value' => fn ($r) => $r->assets_count],
        ];
    }

    protected function fields(Model $record): array
    {
        return [
            ['name' => 'code', 'label' => 'Code', 'required' => true, 'placeholder' => 'CC-1010'],
            ['name' => 'name', 'label' => 'Name', 'required' => true, 'span' => 2],
            ['name' => 'department_id', 'label' => 'Department', 'type' => 'select', 'options' => Department::orderBy('name')->pluck('name', 'id')->all()],
            ['name' => 'gl_code', 'label' => 'GL code'],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'span' => 'full'],
        ];
    }

    protected function blockDeactivation(Model $record): ?string
    {
        return $record->assets()->exists() ? 'Assets are charged to this cost center. Reassign them first.' : null;
    }
}
