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
    private const PALETTE = ['#4840D6', '#14B8A6', '#F59E0B', '#8B5CF6', '#F43F5E', '#0EA5E9', '#10B981', '#A2A6FB', '#F97316', '#EC4899', '#84CC16', '#64748B'];

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

        // Hero / KPI extras (all real data) ----------------------------------
        $activeId = AssetStatus::idFor(AssetStatus::ACTIVE);
        $inUse = $activeId ? $live()->where('asset_status_id', $activeId)->count() : 0;
        $additionsFy = $fy ? Asset::query()->toBase()->whereNull('deleted_at')
            ->whereBetween('capitalization_date', [$fy->start_date->toDateString(), $fy->end_date->toDateString()])
            ->selectRaw('COUNT(*) n, COALESCE(SUM(original_cost),0) cost')->first() : null;

        // FY monthly depreciation (Apr..Mar) for the KPI bars
        $fyMonths = collect();
        if ($fy) {
            $fyRaw = DepreciationEntry::query()->toBase()->whereNull('deleted_at')
                ->whereBetween('period_end', [$fy->start_date->toDateString(), $fy->end_date->toDateString()])
                ->selectRaw(Sql::yearMonth('period_end').' ym, SUM(depreciation_amount) amt')->groupByRaw(Sql::yearMonth('period_end'))->pluck('amt', 'ym');
            for ($m = $fy->start_date->copy()->startOfMonth(); $m->lte($fy->end_date); $m->addMonthNoOverflow()) {
                $fyMonths->push(['label' => $m->format('M'), 'value' => round((float) ($fyRaw[$m->format('Y-m')] ?? 0), 2), 'future' => $m->gt(now())]);
            }
        }

        // Completed maintenance spend per month, last 12 months, for the sparkline
        $mStart = now()->startOfMonth()->subMonthsNoOverflow(11);
        $maintRaw = WorkOrder::query()->toBase()->whereNull('deleted_at')->where('status', 'completed')->whereNotNull('completed_at')
            ->where('completed_at', '>=', $mStart->toDateTimeString())
            ->selectRaw(Sql::yearMonth('completed_at').' ym, SUM(total_cost) amt')->groupByRaw(Sql::yearMonth('completed_at'))->pluck('amt', 'ym');
        $maintTrend = collect(range(0, 11))->map(fn ($i) => round((float) ($maintRaw[$mStart->copy()->addMonthsNoOverflow($i)->format('Y-m')] ?? 0), 2));

        // Depreciation not yet run for the last completed month?
        $lastMonthEnd = now()->subMonthNoOverflow()->endOfMonth();
        $depDue = $totals->n > 0 && (! $lastRun || $lastRun->period_end->lt($lastMonthEnd->copy()->startOfDay())) ? $lastMonthEnd : null;

        // Lists ---------------------------------------------------------------
        $upcomingPm = PmSchedule::with(['asset:id,asset_code,name,asset_category_id', 'asset.category:id,name,code'])->where('is_active', true)->whereHas('asset')
            ->whereNotNull('next_due_date')->where('next_due_date', '<=', now()->addDays(30)->toDateString())
            ->orderBy('next_due_date')->limit(8)->get();
        $pmOverdue = PmSchedule::where('is_active', true)->whereHas('asset')->where('next_due_date', '<', now()->toDateString())->count();
        $pmDue30 = PmSchedule::where('is_active', true)->whereHas('asset')->whereBetween('next_due_date', [now()->toDateString(), now()->addDays(30)->toDateString()])->count();

        $recent = Asset::with(['category', 'location', 'status'])->latest('id')->limit(6)->get();

        $eolExpr = Sql::addMonths('capitalization_date', 'useful_life_years * 12 + useful_life_months', 1);
        $endingLife = $live()->with('category:id,name,code')->select('assets.*')->selectRaw("$eolExpr as eol")
            ->whereRaw("$eolExpr BETWEEN ? AND ?", [now()->toDateString(), now()->addMonths(12)->toDateString()])
            ->orderBy('eol')->limit(8)->get();

        return view('dashboard', [
            'totals' => $totals, 'underMaintenance' => $underMaintenance, 'currentFy' => $fy, 'fyDep' => $fyDep,
            'lastRun' => $lastRun, 'monthDep' => $monthDep, 'pendingRuns' => $pendingRuns, 'lockedThrough' => PeriodGuard::lockedThrough(),
            'maintYtd' => $maintYtd, 'openWo' => $openWo, 'openBreakdowns' => $openBreakdowns,
            'charts' => $charts, 'byCategory' => $byCategory,
            'upcomingPm' => $upcomingPm, 'pmOverdue' => $pmOverdue, 'pmDue30' => $pmDue30,
            'recent' => $recent, 'endingLife' => $endingLife,
            'inUse' => $inUse, 'additionsFy' => $additionsFy, 'fyMonths' => $fyMonths, 'maintTrend' => $maintTrend, 'depDue' => $depDue,
        ]);
    }
}
