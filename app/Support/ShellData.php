<?php

namespace App\Support;

use App\Models\Asset;
use App\Models\DepreciationRun;
use App\Models\FinancialYear;
use App\Models\PmSchedule;
use App\Models\WorkOrder;
use App\Services\MaintenanceService;

/**
 * Live counts for the app shell (sidebar badges, notification bell, mobile tab bar).
 * Computed once per request, tenant-scoped through the models' global scope.
 */
final class ShellData
{
    public static function get(): array
    {
        if (Tenant::id() === null) {
            return ['assets' => 0, 'postedRuns' => 0, 'pmDue' => 0, 'pmOverdue' => 0, 'openWo' => 0, 'breakdowns' => 0, 'fy' => null, 'alerts' => []];
        }
        $attrs = request()->attributes;
        $cached = $attrs->get('ap.shell');
        if (is_array($cached) && $cached['tenant'] === Tenant::id()) {
            return $cached;
        }

        $postedRuns = DepreciationRun::where('status', 'posted')->where('run_type', 'periodic')->orderBy('period_end')->get(['id', 'period_label', 'period_end']);
        $pmOverdue = PmSchedule::where('is_active', true)->whereHas('asset')->whereNotNull('next_due_date')->where('next_due_date', '<', now()->toDateString())->count();
        $breakdowns = WorkOrder::where('type', 'breakdown')->whereIn('status', ['open', 'in_progress', 'on_hold'])->count();

        $alerts = [];
        if ($pmOverdue) {
            $alerts[] = ['tone' => 'rose', 'icon' => 'calendar', 'title' => $pmOverdue.' PM task'.($pmOverdue > 1 ? 's' : '').' overdue', 'url' => route('pm-schedules.index', ['view' => 'overdue'])];
        }
        if ($breakdowns) {
            $alerts[] = ['tone' => 'amber', 'icon' => 'wrench', 'title' => $breakdowns.' open breakdown'.($breakdowns > 1 ? 's' : ''), 'url' => route('work-orders.index', ['type' => 'breakdown', 'status' => 'open'])];
        }
        if ($postedRuns->isNotEmpty()) {
            $alerts[] = ['tone' => 'brand', 'icon' => 'calculator', 'title' => 'Depreciation '.$postedRuns->pluck('period_label')->implode(', ').' awaiting finalisation', 'url' => route('depreciation.show', $postedRuns->first())];
        }

        $data = [
            'tenant' => Tenant::id(),
            'assets' => Asset::count(),
            'postedRuns' => $postedRuns->count(),
            'pmDue' => MaintenanceService::dueCount(),
            'pmOverdue' => $pmOverdue,
            'openWo' => WorkOrder::whereIn('status', ['open', 'in_progress', 'on_hold'])->count(),
            'breakdowns' => $breakdowns,
            'fy' => FinancialYear::current(),
            'alerts' => $alerts,
        ];
        $attrs->set('ap.shell', $data);

        return $data;
    }
}
