<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\VendorRequest;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Model;

class VendorController extends MasterController
{
    protected string $model = Vendor::class;
    protected string $request = VendorRequest::class;
    protected string $routeName = 'admin.vendors';
    protected string $title = 'Vendors';
    protected string $singular = 'Vendor';
    protected string $description = 'Suppliers of capital equipment, moulds, IT and vehicles.';
    protected string $icon = 'truck';

    protected function query(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::query()->withCount('assets');
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Code', 'value' => fn ($r) => '<span class="font-mono text-xs font-semibold text-slate-700">'.e($r->code).'</span>'],
            ['label' => 'Vendor', 'value' => fn ($r) => self::strong($r->name, $r->contact_person)],
            ['label' => 'GSTIN', 'value' => fn ($r) => '<span class="font-mono text-xs">'.self::muted($r->gstin).'</span>'],
            ['label' => 'City', 'value' => fn ($r) => self::muted($r->city)],
            ['label' => 'Contact', 'value' => fn ($r) => self::muted($r->email ?: $r->phone)],
            ['label' => 'Assets', 'class' => 'text-right', 'value' => fn ($r) => $r->assets_count],
        ];
    }

    protected function fields(Model $record): array
    {
        return [
            ['type' => 'section', 'label' => 'Vendor'],
            ['name' => 'code', 'label' => 'Code', 'required' => true, 'placeholder' => 'V-ENGEL'],
            ['name' => 'name', 'label' => 'Name', 'required' => true, 'span' => 2],
            ['name' => 'gstin', 'label' => 'GSTIN'],
            ['name' => 'pan', 'label' => 'PAN'],
            ['name' => 'payment_terms_days', 'label' => 'Payment terms', 'type' => 'number', 'min' => 0, 'suffix' => 'days'],
            ['type' => 'section', 'label' => 'Contact'],
            ['name' => 'contact_person', 'label' => 'Contact person'],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
            ['name' => 'phone', 'label' => 'Phone'],
            ['name' => 'website', 'label' => 'Website', 'type' => 'url', 'placeholder' => 'https://', 'span' => 'full'],
            ['type' => 'section', 'label' => 'Address'],
            ['name' => 'address', 'label' => 'Address', 'span' => 'full'],
            ['name' => 'city', 'label' => 'City'],
            ['name' => 'state', 'label' => 'State'],
            ['name' => 'pincode', 'label' => 'PIN / ZIP'],
            ['name' => 'country', 'label' => 'Country', 'required' => true, 'value' => old('country', $record->country ?? 'India')],
        ];
    }
}
