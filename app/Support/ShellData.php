<?php

namespace App\Support;

use App\Models\Asset;
use App\Models\DepreciationRun;
use App\Models\FinancialYear;
use App\Models\PmSchedule;
use App\Models\WorkOrder;
use App\Services\MaintenanceService;
use Illuminate\Support\Str;

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
            $alerts[] = ['tone' => 'rose', 'icon' => 'calendar-check', 'count' => $pmOverdue,
                'title' => $pmOverdue.' '.Str::plural('PM task', $pmOverdue).' overdue',
                'sub' => 'Preventive maintenance past its due date',
                'url' => route('pm-schedules.index', ['view' => 'overdue'])];
        }
        if ($breakdowns) {
            $alerts[] = ['tone' => 'amber', 'icon' => 'wrench', 'count' => $breakdowns,
                'title' => $breakdowns.' open '.Str::plural('breakdown', $breakdowns),
                'sub' => 'Breakdown work orders open, in progress or on hold',
                'url' => route('work-orders.index', ['type' => 'breakdown', 'status' => 'open'])];
        }
        if ($n = $postedRuns->count()) {
            $alerts[] = ['tone' => 'brand', 'icon' => 'calculator', 'count' => $n,
                'title' => $n === 1
                    ? 'Depreciation '.$postedRuns->first()->period_label.' awaiting finalisation'
                    : $n.' depreciation runs awaiting finalisation',
                'sub' => $n === 1 ? 'Posted run – review and finalise' : 'Periods '.$postedRuns->pluck('period_label')->implode(', '),
                'url' => $n === 1 ? route('depreciation.show', $postedRuns->first()) : route('depreciation.index', ['status' => 'posted'])];
        }
        // Same rule as the dashboard: last completed month has no posted/finalised periodic run (writers only)
        $assetCount = Asset::count();
        if ($assetCount && auth()->user()?->canManageAssets()) {
            $lastRun = DepreciationRun::where('run_type', 'periodic')->whereIn('status', ['posted', 'finalized'])->latest('period_end')->first(['id', 'run_number', 'period_end']);
            $lastMonthEnd = now()->subMonthNoOverflow()->endOfMonth();
            if (! $lastRun || $lastRun->period_end->lt($lastMonthEnd->copy()->startOfDay())) {
                $alerts[] = ['tone' => 'violet', 'icon' => 'calendar', 'count' => 1,
                    'title' => 'Depreciation for '.$lastMonthEnd->format('M Y').' not run yet',
                    'sub' => $lastRun ? 'Last run '.$lastRun->run_number.' · '.$lastRun->period_end->format('M Y') : 'No depreciation run has been posted yet',
                    'url' => route('depreciation.create')];
            }
        }

        $data = [
            'tenant' => Tenant::id(),
            'assets' => $assetCount,
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
