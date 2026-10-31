<?php

namespace App\Http\Controllers;

use App\Models\AssetCategory;
use App\Models\CostCenter;
use App\Models\DepreciationEntry;
use App\Models\DepreciationRun;
use App\Models\DepreciationRunParameter;
use App\Models\PeriodLock;
use App\Services\Depreciation\DepreciationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class DepreciationRunController extends Controller
{
    public function __construct(private DepreciationService $service) {}

    public function index(Request $request)
    {
        $runs = DepreciationRun::with(['runBy:id,name', 'finalizedBy:id,name', 'financialYear:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('type'), fn ($q) => $q->where('run_type', $request->type))
            ->orderByDesc('period_end')->orderByDesc('id')
            ->paginate(15)->withQueryString();
        $locks = PeriodLock::with('lockedBy:id,name')->orderByDesc('period_end')->limit(12)->get();
        $last = $this->service->lastPeriodicRun();
        [$nextFrom, $nextTo, $nextLabel] = $this->service->nextPeriod();
        $stats = [
            'posted_open' => DepreciationRun::where('status', 'posted')->count(),
            'locked_through' => PeriodLock::where('is_locked', true)->max('period_end'),
            'fy_total' => (float) DepreciationEntry::whereBetween('period_end', $this->fyRange())->sum('depreciation_amount'),
            'last' => $last,
        ];

        return view('depreciation.index', compact('runs', 'locks', 'stats', 'nextFrom', 'nextTo', 'nextLabel'));
    }

    public function create(Request $request)
    {
        $parameters = DepreciationRunParameter::orderByDesc('is_default')->get();
        $param = $parameters->firstWhere('id', (int) $request->parameter) ?? $parameters->first();
        [$defFrom, $defTo, $label] = $this->service->nextPeriod($param);
        $from = $request->input('from', $defFrom->toDateString());
        $to = $request->input('to', $defTo->toDateString());
        $preview = null;
        $error = null;
        if ($request->boolean('preview')) {
            $request->validate(['from' => 'required|date', 'to' => 'required|date|after_or_equal:from']);
            try {
                $this->service->validateNewRun(CarbonImmutable::parse($from), CarbonImmutable::parse($to));
            } catch (\Illuminate\Validation\ValidationException $e) {
                $error = collect($e->errors())->flatten()->first();
            }
            $preview = $this->service->preview($from, $to, $param);
        }

        return view('depreciation.create', compact('parameters', 'param', 'from', 'to', 'label', 'preview', 'error'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'from' => 'required|date', 'to' => 'required|date|after_or_equal:from',
            'parameter' => ['nullable', 'integer'], 'remarks' => 'nullable|string|max:1000',
        ]);
        $param = isset($data['parameter']) ? DepreciationRunParameter::find($data['parameter']) : null;
        $run = $this->service->post($data['from'], $data['to'], $param, $data['remarks'] ?? null);

        return redirect()->route('depreciation.show', $run)->with('success', "Run {$run->run_number} posted: ".inr($run->total_depreciation)." on {$run->total_assets} assets. Review it, then finalise to lock the period.");
    }

    public function show(Request $request, DepreciationRun $run)
    {
        $run->load(['runBy:id,name', 'finalizedBy:id,name', 'financialYear:id,name', 'parameter', 'periodLock']);
        $q = DepreciationEntry::withTrashed()->where('depreciation_run_id', $run->id)
            ->with(['asset:id,asset_code,name,asset_category_id', 'asset.category:id,name', 'costCenter:id,code,name', 'location:id,name'])
            ->when($request->filled('category'), fn ($q) => $q->where('asset_category_id', $request->category))
            ->when($request->filled('cost_center'), fn ($q) => $q->where('cost_center_id', $request->cost_center))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('asset', fn ($a) => $a->withTrashed()->where(fn ($w) => $w->whereLike('asset_code', '%'.$request->q.'%')->orWhereLike('name', '%'.$request->q.'%'))));
        $totals = (clone $q)->toBase()->selectRaw('COUNT(*) n, COALESCE(SUM(depreciation_amount),0) dep, COALESCE(SUM(opening_gross_block),0) gross, COALESCE(SUM(closing_wdv),0) wdv')->first();
        $entries = $q->join('assets', 'assets.id', '=', 'depreciation_entries.asset_id')->orderBy('assets.asset_code')
            ->select('depreciation_entries.*')->paginate(50)->withQueryString();
        $byCategory = DepreciationEntry::withTrashed()->where('depreciation_run_id', $run->id)
            ->join('asset_categories', 'asset_categories.id', '=', 'depreciation_entries.asset_category_id')
            ->groupBy('asset_categories.name')->selectRaw('asset_categories.name, COUNT(*) n, SUM(depreciation_amount) dep')->orderByDesc('dep')->get();
        $categories = AssetCategory::orderBy('name')->pluck('name', 'id');
        $costCenters = CostCenter::orderBy('code')->get()->mapWithKeys(fn ($c) => [$c->id => $c->code.' – '.$c->name]);
        $canReverse = $run->status === 'posted' && $run->run_type === 'periodic' && ! DepreciationRun::where('run_type', 'periodic')->whereIn('status', ['posted', 'finalized'])->where('period_end', '>', $run->period_end)->exists();
        $canFinalize = $run->status === 'posted' && $run->run_type === 'periodic' && ! DepreciationRun::where('run_type', 'periodic')->where('status', 'posted')->where('period_end', '<', $run->period_start)->exists();

        return view('depreciation.show', compact('run', 'entries', 'totals', 'byCategory', 'categories', 'costCenters', 'canReverse', 'canFinalize'));
    }

    public function finalize(DepreciationRun $run)
    {
        $this->service->finalize($run);

        return back()->with('success', "Run {$run->run_number} finalised. {$run->period_label} is now locked – no re-runs, back-dated transfers or disposals, or cost changes in this period.");
    }

    public function reverse(Request $request, DepreciationRun $run)
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:500']);
        $this->service->reverse($run, $data['reason']);

        return redirect()->route('depreciation.index')->with('success', "Run {$run->run_number} ({$run->period_label}) reversed. Asset balances restored; you can now re-run the period.");
    }

    public function unlock(Request $request, PeriodLock $lock)
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:500']);
        abort_unless($lock->is_locked, 422, 'This period is not locked.');
        $this->service->unlock($lock, $data['reason']);

        return back()->with('warning', "Period {$lock->period_label} unlocked. Its run is back to 'posted' and can be reversed and re-run; remember to finalise again.");
    }

    private function fyRange(): array
    {
        [$s, $e] = $this->service->periodFor('yearly', now());

        return [$s->toDateString(), $e->toDateString()];
    }
}
