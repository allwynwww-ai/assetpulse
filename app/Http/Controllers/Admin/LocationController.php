<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\LocationRequest;
use App\Models\Location;
use Illuminate\Database\Eloquent\Model;

class LocationController extends MasterController
{
    protected string $model = Location::class;
    protected string $request = LocationRequest::class;
    protected string $routeName = 'admin.locations';
    protected string $title = 'Locations / Plants';
    protected string $singular = 'Location';
    protected string $description = 'Plants, warehouses, offices and shop-floor areas where assets are installed.';
    protected string $icon = 'map';
    protected array $with = ['parent'];

    protected function query(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::query()->withCount('assets');
    }

    protected function filters(): array
    {
        return ['type' => ['label' => 'Type', 'options' => Location::TYPES]];
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Code', 'value' => fn ($r) => '<span class="font-mono text-xs font-semibold text-slate-700">'.e($r->code).'</span>'],
            ['label' => 'Name', 'value' => fn ($r) => self::strong($r->name, $r->parent ? 'within '.$r->parent->name : null)],
            ['label' => 'Type', 'value' => fn ($r) => self::badge(Location::TYPES[$r->type] ?? $r->type, $r->type === 'plant' ? 'sky' : 'slate')],
            ['label' => 'City', 'value' => fn ($r) => self::muted(trim($r->city.($r->state ? ', '.$r->state : ''), ', '))],
            ['label' => 'Assets', 'class' => 'text-right', 'value' => fn ($r) => $r->assets_count],
        ];
    }

    protected function fields(Model $record): array
    {
        $parents = Location::query()->when($record->exists, fn ($q) => $q->whereKeyNot($record->id))->orderBy('name')->pluck('name', 'id')->all();

        return [
            ['type' => 'section', 'label' => 'Location'],
            ['name' => 'code', 'label' => 'Code', 'required' => true, 'placeholder' => 'PUN-P1'],
            ['name' => 'name', 'label' => 'Name', 'required' => true],
            ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => Location::TYPES, 'required' => true, 'placeholder' => false],
            ['name' => 'parent_id', 'label' => 'Parent location', 'type' => 'select', 'options' => $parents, 'placeholder' => '— None (top level) —'],
            ['name' => 'gstin', 'label' => 'GSTIN', 'placeholder' => '27AAACP1234Q1Z5'],
            ['type' => 'section', 'label' => 'Address & contact'],
            ['name' => 'address', 'label' => 'Address', 'span' => 'full'],
            ['name' => 'city', 'label' => 'City'],
            ['name' => 'state', 'label' => 'State'],
            ['name' => 'pincode', 'label' => 'PIN code'],
            ['name' => 'contact_person', 'label' => 'Contact person'],
            ['name' => 'phone', 'label' => 'Phone'],
        ];
    }

    protected function blockDeactivation(Model $record): ?string
    {
        return $record->assets()->exists() ? 'Assets are installed at this location. Transfer them before deactivating.' : null;
    }
}
