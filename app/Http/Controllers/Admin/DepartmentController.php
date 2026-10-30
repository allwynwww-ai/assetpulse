<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\DepartmentRequest;
use App\Models\Department;
use App\Models\Location;
use Illuminate\Database\Eloquent\Model;

class DepartmentController extends MasterController
{
    protected string $model = Department::class;
    protected string $request = DepartmentRequest::class;
    protected string $routeName = 'admin.departments';
    protected string $title = 'Departments';
    protected string $singular = 'Department';
    protected string $description = 'Functional departments that own and use assets.';
    protected string $icon = 'office';
    protected array $with = ['location'];

    protected function query(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::query()->withCount('assets');
    }

    protected function filters(): array
    {
        return ['location_id' => ['label' => 'Location', 'options' => Location::orderBy('name')->pluck('name', 'id')->all()]];
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Code', 'value' => fn ($r) => '<span class="font-mono text-xs font-semibold text-slate-700">'.e($r->code).'</span>'],
            ['label' => 'Name', 'value' => fn ($r) => self::strong($r->name, $r->head_name ? 'Head: '.$r->head_name : null)],
            ['label' => 'Location', 'value' => fn ($r) => self::muted($r->location?->name)],
            ['label' => 'Assets', 'class' => 'text-right', 'value' => fn ($r) => $r->assets_count],
        ];
    }

    protected function fields(Model $record): array
    {
        return [
            ['name' => 'code', 'label' => 'Code', 'required' => true, 'placeholder' => 'PROD'],
            ['name' => 'name', 'label' => 'Name', 'required' => true, 'span' => 2],
            ['name' => 'location_id', 'label' => 'Location / Plant', 'type' => 'select', 'options' => Location::orderBy('name')->pluck('name', 'id')->all()],
            ['name' => 'head_name', 'label' => 'Department head'],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
        ];
    }

    protected function blockDeactivation(Model $record): ?string
    {
        return $record->assets()->exists() ? 'Assets are assigned to this department. Reassign them first.' : null;
    }
}
