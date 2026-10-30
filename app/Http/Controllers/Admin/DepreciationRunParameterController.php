<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\DepreciationRunParameterRequest;
use App\Models\DepreciationRunParameter;
use Illuminate\Database\Eloquent\Model;

class DepreciationRunParameterController extends MasterController
{
    protected string $model = DepreciationRunParameter::class;
    protected string $request = DepreciationRunParameterRequest::class;
    protected string $routeName = 'admin.depreciation-run-parameters';
    protected string $title = 'Depreciation Run Parameters';
    protected string $singular = 'Run Parameter Set';
    protected string $description = 'How depreciation runs are calculated: frequency, book, proration and shift basis.';
    protected string $icon = 'adjust';
    protected string $orderBy = 'name';

    public const FREQ = ['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly'];
    public const BOOK = ['companies_act' => 'Companies Act 2013', 'income_tax' => 'Income Tax Act'];
    public const PRORATE = ['days' => 'Days in use', 'months' => 'Full months'];
    public const SHIFT = ['single' => 'Single shift (1.0×)', 'double' => 'Double shift (1.5×)', 'triple' => 'Triple shift (2.0×)'];

    protected function columns(): array
    {
        return [
            ['label' => 'Name', 'value' => fn ($r) => self::strong($r->name).($r->is_default ? ' '.self::badge('Default', 'emerald') : '')],
            ['label' => 'Frequency', 'value' => fn ($r) => e(self::FREQ[$r->frequency] ?? $r->frequency)],
            ['label' => 'Book', 'value' => fn ($r) => e(self::BOOK[$r->book] ?? $r->book)],
            ['label' => 'Proration', 'value' => fn ($r) => e(self::PRORATE[$r->proration_basis] ?? '')],
            ['label' => 'Shift', 'value' => fn ($r) => e(ucfirst($r->shift_basis))],
            ['label' => 'Low-value limit', 'class' => 'text-right', 'value' => fn ($r) => inr($r->low_value_threshold)],
        ];
    }

    protected function fields(Model $record): array
    {
        $d = fn ($k, $v) => old($k, $record->exists ? $record->{$k} : $v);

        return [
            ['type' => 'section', 'label' => 'Parameter set'],
            ['name' => 'name', 'label' => 'Name', 'required' => true, 'span' => 2],
            ['name' => 'frequency', 'label' => 'Run frequency', 'type' => 'select', 'options' => self::FREQ, 'placeholder' => false, 'required' => true],
            ['name' => 'book', 'label' => 'Book', 'type' => 'select', 'options' => self::BOOK, 'placeholder' => false, 'required' => true],
            ['name' => 'proration_basis', 'label' => 'Proration basis', 'type' => 'select', 'options' => self::PRORATE, 'placeholder' => false, 'required' => true],
            ['name' => 'shift_basis', 'label' => 'Shift basis', 'type' => 'select', 'options' => self::SHIFT, 'placeholder' => false, 'required' => true, 'help' => 'Schedule II extra-shift depreciation for plant & machinery.'],
            ['name' => 'low_value_threshold', 'label' => 'Low-value asset threshold', 'type' => 'number', 'step' => '0.01', 'prefix' => '₹', 'required' => true, 'value' => $d('low_value_threshold', 5000), 'help' => 'Assets at or below this cost are fully depreciated in the year of purchase.'],
            ['name' => 'rounding_precision', 'label' => 'Rounding (decimals)', 'type' => 'number', 'min' => 0, 'max' => 4, 'required' => true, 'value' => $d('rounding_precision', 2)],
            ['type' => 'section', 'label' => 'Rules'],
            ['name' => 'depreciate_in_month_of_capitalization', 'label' => 'Depreciate in month of capitalisation', 'type' => 'checkbox', 'value' => $d('depreciate_in_month_of_capitalization', true)],
            ['name' => 'depreciate_in_month_of_disposal', 'label' => 'Depreciate in month of disposal', 'type' => 'checkbox', 'value' => $d('depreciate_in_month_of_disposal', true)],
            ['name' => 'auto_lock_on_finalize', 'label' => 'Lock period when a run is finalised', 'type' => 'checkbox', 'value' => $d('auto_lock_on_finalize', true)],
            ['name' => 'is_default', 'label' => 'Default parameter set', 'type' => 'checkbox', 'help' => 'Used when a run does not specify parameters.'],
            ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'span' => 'full'],
        ];
    }

    protected function afterSave(Model $record, array $data, bool $created): void
    {
        if ($record->is_default) {
            DepreciationRunParameter::whereKeyNot($record->id)->where('is_default', true)->get()->each(fn ($p) => $p->update(['is_default' => false]));
        }
    }

    protected function blockDeactivation(Model $record): ?string
    {
        return $record->is_default ? 'The default parameter set cannot be deactivated. Make another set the default first.' : null;
    }
}
