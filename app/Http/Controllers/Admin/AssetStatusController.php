<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\AssetStatusRequest;
use App\Models\AssetStatus;
use Illuminate\Database\Eloquent\Model;

class AssetStatusController extends MasterController
{
    protected string $model = AssetStatus::class;
    protected string $request = AssetStatusRequest::class;
    protected string $routeName = 'admin.asset-statuses';
    protected string $title = 'Asset Statuses';
    protected string $singular = 'Asset Status';
    protected string $description = 'Lifecycle states and whether assets in that state are depreciated.';
    protected string $icon = 'flag';
    protected string $orderBy = 'sort_order';

    protected function query(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::query()->withCount('assets');
    }

    protected function columns(): array
    {
        return [
            ['label' => '#', 'class' => 'w-10 text-slate-400', 'value' => fn ($r) => $r->sort_order],
            ['label' => 'Status', 'value' => fn ($r) => self::badge($r->name, $r->color).'<div class="mt-1 font-mono text-[11px] text-slate-400">'.e($r->code).'</div>'],
            ['label' => 'Depreciable', 'value' => fn ($r) => $r->is_depreciable ? self::badge('Yes', 'emerald') : self::badge('No', 'slate')],
            ['label' => 'Assets', 'class' => 'text-right', 'value' => fn ($r) => $r->assets_count],
            ['label' => 'Type', 'value' => fn ($r) => $r->is_system ? self::badge('System', 'sky') : self::badge('Custom', 'slate')],
        ];
    }

    protected function fields(Model $record): array
    {
        return [
            ['name' => 'code', 'label' => 'Code', 'required' => true, 'disabled' => (bool) $record->is_system, 'help' => 'Upper-case, e.g. IDLE.'],
            ['name' => 'name', 'label' => 'Name', 'required' => true],
            ['name' => 'color', 'label' => 'Badge colour', 'type' => 'select', 'options' => AssetStatusRequest::COLORS, 'required' => true, 'placeholder' => false, 'value' => old('color', $record->color ?? 'slate')],
            ['name' => 'sort_order', 'label' => 'Sort order', 'type' => 'number', 'min' => 0],
            ['name' => 'is_depreciable', 'label' => 'Depreciable', 'type' => 'checkbox', 'value' => old('is_depreciable', $record->exists ? $record->is_depreciable : true), 'help' => 'Assets in this status are included in depreciation runs.', 'span' => 2],
        ];
    }

    protected function prepare(array $data, ?Model $record): array
    {
        if ($record?->is_system) {
            unset($data['code']);
        }
        $data['sort_order'] ??= 0;

        return $data;
    }

    protected function blockDeactivation(Model $record): ?string
    {
        if ($record->is_system) {
            return 'System statuses cannot be deactivated.';
        }
        if ($record->assets()->exists()) {
            return 'Assets currently use this status. Change their status first.';
        }

        return null;
    }
}
