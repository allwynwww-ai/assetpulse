<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\DepreciationEntry;
use App\Models\DepreciationRun;
use App\Models\FinancialYear;
use App\Models\PmSchedule;
use App\Models\WorkOrder;
use App\Services\PeriodGuard;
use App\Support\Sql;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private const PALETTE = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444', '#06b6d4', '#ec4899', '#84cc16', '#f97316', '#6366f1', '#14b8a6', '#a855f7'];

    public function __invoke(Request $request)
    {
        if (Tenant::id() === null) {
            return $request->user()->isSuperAdmin()
                ? redirect()->route('admin.companies.index')
                : abort(403, 'Your account is not linked to a company.');
        }

        $closedIds = AssetStatus::whereIn('code', [AssetStatus::DISPOSED, AssetStatus::SCRAPPED])->pluck('id');
        $live = fn () => Asset::query()->whereNotIn('assets.asset_status_id', $closedIds);

        $totals = $live()->toBase()
            ->selectRaw('COUNT(*) n, COALESCE(SUM(original_cost),0) cost, COALESCE(SUM(accumulated_depreciation),0) acc, COALESCE(SUM(current_book_value),0) nbv')
            ->first();
        $underMaintenance = Asset::whereHas('status', fn ($q) => $q->where('code', AssetStatus::UNDER_MAINTENANCE))->count();

        $fy = FinancialYear::current() ?? FinancialYear::forDate(now());
        $fyDep = $fy ? (float) DepreciationEntry::whereBetween('period_end', [$fy->start_date->toDateString(), $fy->end_date->toDateString()])->sum('depreciation_amount') : 0.0;

        $lastRun = DepreciationRun::where('run_type', 'periodic')->whereIn('status', ['posted', 'finalized'])->latest('period_end')->first();
        $monthDep = $lastRun ? (float) DepreciationEntry::whereHas('run', fn ($q) => $q->whereKey($lastRun->id))->sum('depreciation_amount') : 0.0;
        $pendingRuns = DepreciationRun::where('run_type', 'periodic')->where('status', 'posted')->orderBy('period_end')->get();

        $maintYtd = $fy ? (float) WorkOrder::where('status', 'completed')
            ->whereBetween('completed_at', [$fy->start_date->startOfDay(), $fy->end_date->endOfDay()])->sum('total_cost') : 0.0;
        $openWo = WorkOrder::whereIn('status', ['open', 'in_progress', 'on_hold'])->count();
        $openBreakdowns = WorkOrder::with('asset:id,asset_code,name')->where('type', 'breakdown')->whereIn('status', ['open', 'in_progress', 'on_hold'])->latest('reported_date')->limit(5)->get();

        // Charts --------------------------------------------------------------
        $byCategory = $live()->toBase()->join('asset_categories as c', 'c.id', '=', 'assets.asset_category_id')
            ->groupBy('c.id', 'c.name')->selectRaw('c.name, COUNT(*) n, SUM(assets.original_cost) cost, SUM(assets.current_book_value) nbv')
            ->orderByDesc('cost')->get();
        $byLocation = $live()->toBase()->leftJoin('locations as l', 'l.id', '=', 'assets.location_id')
            ->groupBy('l.id', 'l.name')->selectRaw("COALESCE(l.name, 'Unassigned') name, COUNT(*) n, SUM(assets.original_cost) cost, SUM(assets.current_book_value) nbv")
            ->orderByDesc('cost')->get();

        $end = Carbon::parse($lastRun?->period_end ?? now())->endOfMonth();
        $start = $end->copy()->subMonthsNoOverflow(11)->startOfMonth();
        $trendRaw = DepreciationEntry::query()->toBase()->whereBetween('period_end', [$start->toDateString(), $end->toDateString()])
            ->whereNull('deleted_at')->selectRaw(Sql::yearMonth('period_end').' ym, SUM(depreciation_amount) amt')->groupByRaw(Sql::yearMonth('period_end'))->pluck('amt', 'ym');
        $trend = collect(range(0, 11))->map(function ($i) use ($start, $trendRaw) {
            $m = $start->copy()->addMonthsNoOverflow($i);

            return ['label' => $m->format('M y'), 'value' => round((float) ($trendRaw[$m->format('Y-m')] ?? 0), 2)];
        });

        $charts = [
            'category' => ['labels' => $byCategory->pluck('name'), 'values' => $byCategory->pluck('cost')->map(fn ($v) => round((float) $v, 2)), 'counts' => $byCategory->pluck('n'), 'colors' => array_slice(array_merge(self::PALETTE, self::PALETTE), 0, max(1, $byCategory->count()))],
            'location' => ['labels' => $byLocation->pluck('name'), 'cost' => $byLocation->pluck('cost')->map(fn ($v) => round((float) $v, 2)), 'nbv' => $byLocation->pluck('nbv')->map(fn ($v) => round((float) $v, 2))],
            'trend' => ['labels' => $trend->pluck('label'), 'values' => $trend->pluck('value')],
        ];

        // Lists ---------------------------------------------------------------
        $upcomingPm = PmSchedule::with('asset:id,asset_code,name')->where('is_active', true)->whereHas('asset')
            ->whereNotNull('next_due_date')->where('next_due_date', '<=', now()->addDays(30)->toDateString())
            ->orderBy('next_due_date')->limit(8)->get();
        $pmOverdue = PmSchedule::where('is_active', true)->whereHas('asset')->where('next_due_date', '<', now()->toDateString())->count();
        $pmDue30 = PmSchedule::where('is_active', true)->whereHas('asset')->whereBetween('next_due_date', [now()->toDateString(), now()->addDays(30)->toDateString()])->count();

        $recent = Asset::with(['category', 'location', 'status'])->latest('id')->limit(6)->get();

        $eolExpr = Sql::addMonths('capitalization_date', 'useful_life_years * 12 + useful_life_months', 1);
        $endingLife = $live()->with('category:id,name')->select('assets.*')->selectRaw("$eolExpr as eol")
            ->whereRaw("$eolExpr BETWEEN ? AND ?", [now()->toDateString(), now()->addMonths(12)->toDateString()])
            ->orderBy('eol')->limit(8)->get();

        return view('dashboard', [
            'totals' => $totals, 'underMaintenance' => $underMaintenance, 'currentFy' => $fy, 'fyDep' => $fyDep,
            'lastRun' => $lastRun, 'monthDep' => $monthDep, 'pendingRuns' => $pendingRuns, 'lockedThrough' => PeriodGuard::lockedThrough(),
            'maintYtd' => $maintYtd, 'openWo' => $openWo, 'openBreakdowns' => $openBreakdowns,
            'charts' => $charts, 'byCategory' => $byCategory,
            'upcomingPm' => $upcomingPm, 'pmOverdue' => $pmOverdue, 'pmDue30' => $pmDue30,
            'recent' => $recent, 'endingLife' => $endingLife,
        ]);
    }
}
