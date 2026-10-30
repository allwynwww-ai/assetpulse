<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\FinancialYearRequest;
use App\Models\FinancialYear;
use Illuminate\Database\Eloquent\Model;

class FinancialYearController extends MasterController
{
    protected string $model = FinancialYear::class;
    protected string $request = FinancialYearRequest::class;
    protected string $routeName = 'admin.financial-years';
    protected string $title = 'Financial Years';
    protected string $singular = 'Financial Year';
    protected string $description = 'Accounting years (April–March) used for depreciation periods and locks.';
    protected string $icon = 'calendar';
    protected string $orderBy = 'start_date';

    protected function columns(): array
    {
        return [
            ['label' => 'Year', 'value' => fn ($r) => self::strong($r->name).($r->is_current ? ' '.self::badge('Current', 'emerald') : '')],
            ['label' => 'Start', 'value' => fn ($r) => fmt_date($r->start_date)],
            ['label' => 'End', 'value' => fn ($r) => fmt_date($r->end_date)],
            ['label' => 'Status', 'value' => fn ($r) => $r->status === 'open' ? self::badge('Open', 'sky') : self::badge('Closed', 'slate')],
        ];
    }

    protected function fields(Model $record): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'required' => true, 'placeholder' => 'FY 2026-27'],
            ['name' => 'start_date', 'label' => 'Start date', 'type' => 'date', 'required' => true],
            ['name' => 'end_date', 'label' => 'End date', 'type' => 'date', 'required' => true],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['open' => 'Open', 'closed' => 'Closed'], 'placeholder' => false, 'required' => true],
            ['name' => 'is_current', 'label' => 'Current financial year', 'type' => 'checkbox', 'help' => 'Only one year can be current; others are cleared automatically.', 'span' => 2],
        ];
    }

    protected function prepare(array $data, ?Model $record): array
    {
        if ($data['status'] === 'closed' && $record?->status !== 'closed') {
            $data['closed_at'] = now();
            $data['closed_by'] = auth()->id();
        } elseif ($data['status'] === 'open') {
            $data['closed_at'] = null;
            $data['closed_by'] = null;
        }

        return $data;
    }

    protected function afterSave(Model $record, array $data, bool $created): void
    {
        if ($record->is_current) {
            FinancialYear::whereKeyNot($record->id)->where('is_current', true)->get()->each(fn ($fy) => $fy->update(['is_current' => false]));
        }
    }

    protected function blockDeactivation(Model $record): ?string
    {
        if ($record->is_current) {
            return 'The current financial year cannot be deactivated. Mark another year as current first.';
        }

        return $record->depreciationRuns()->exists() ? 'Depreciation has been run in this year; it cannot be deactivated.' : null;
    }
}
