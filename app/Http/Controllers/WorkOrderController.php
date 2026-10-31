<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\PmSchedule;
use App\Models\Vendor;
use App\Models\WorkOrder;
use App\Services\MaintenanceService;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkOrderController extends Controller
{
    public function __construct(private MaintenanceService $svc) {}

    public function index(Request $request)
    {
        $q = WorkOrder::with(['asset:id,asset_code,name', 'vendor:id,name'])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->whereLike('wo_number', "%{$request->q}%")->orWhereLike('title', "%{$request->q}%")
                ->orWhereHas('asset', fn ($a) => $a->withTrashed()->whereLike('asset_code', "%{$request->q}%")->orWhereLike('name', "%{$request->q}%"))))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->input('status') === 'open', fn ($q) => $q->whereIn('status', ['open', 'in_progress', 'on_hold']))
            ->when($request->filled('status') && $request->status !== 'open', fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('from'), fn ($q) => $q->where('reported_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->where('reported_date', '<=', $request->to));
        $totals = (clone $q)->toBase()->selectRaw('COUNT(*) n, COALESCE(SUM(total_cost),0) cost, COALESCE(SUM(downtime_hours),0) downtime')->first();
        $workOrders = $q->orderByRaw(\App\Support\Sql::orderByList('status', ['in_progress', 'open', 'on_hold', 'completed', 'cancelled']))->orderByDesc('reported_date')->orderByDesc('id')->paginate(20)->withQueryString();
        $openCount = WorkOrder::whereIn('status', ['open', 'in_progress', 'on_hold'])->count();
        $openBreakdowns = WorkOrder::where('type', 'breakdown')->whereIn('status', ['open', 'in_progress', 'on_hold'])->count();

        return view('maintenance.wo-index', compact('workOrders', 'totals', 'openCount', 'openBreakdowns'));
    }

    public function create(Request $request)
    {
        $pm = $request->filled('pm') ? PmSchedule::findOrFail($request->pm) : null;
        $wo = new WorkOrder([
            'asset_id' => $pm?->asset_id ?? $request->asset, 'pm_schedule_id' => $pm?->id,
            'type' => $pm ? 'preventive' : $request->input('type', 'breakdown'), 'priority' => 'medium',
            'title' => $pm?->title, 'description' => $pm?->checklist, 'reported_date' => now(), 'scheduled_date' => $pm?->next_due_date,
            'assigned_to' => $pm?->assigned_to, 'vendor_id' => $pm?->vendor_id,
        ]);

        return view('maintenance.wo-form', $this->lookups() + ['wo' => $wo]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $asset = Asset::findOrFail($data['asset_id']);
        $wo = $this->svc->createWorkOrder($asset, $data);

        return redirect()->route('work-orders.show', $wo)->with('success', "Work order {$wo->wo_number} raised".($wo->type === 'breakdown' ? " – {$asset->asset_code} marked Under Maintenance." : '.'));
    }

    public function show(WorkOrder $workOrder)
    {
        $workOrder->load(['asset.status', 'asset.location', 'pmSchedule', 'vendor', 'creator', 'completer', 'auditLogs']);

        return view('maintenance.wo-show', ['wo' => $workOrder, 'vendors' => Vendor::orderBy('name')->pluck('name', 'id')]);
    }

    public function edit(WorkOrder $workOrder)
    {
        abort_if(in_array($workOrder->status, ['completed', 'cancelled']), 422, 'Closed work orders cannot be edited.');

        return view('maintenance.wo-form', $this->lookups() + ['wo' => $workOrder]);
    }

    public function update(Request $request, WorkOrder $workOrder)
    {
        abort_if(in_array($workOrder->status, ['completed', 'cancelled']), 422, 'Closed work orders cannot be edited.');
        $data = $this->validated($request);
        unset($data['asset_id']);
        $workOrder->update($data);
        $this->svc->syncAssetStatus($workOrder->asset);

        return redirect()->route('work-orders.show', $workOrder)->with('success', 'Work order updated.');
    }

    public function start(WorkOrder $workOrder)
    {
        $this->svc->start($workOrder);

        return back()->with('success', "{$workOrder->wo_number} is now in progress.");
    }

    public function complete(Request $request, WorkOrder $workOrder)
    {
        $data = $request->validate([
            'completed_at' => ['required', 'date', 'before_or_equal:now'],
            'labour_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'parts_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'external_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'downtime_hours' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'root_cause' => ['nullable', 'string', 'max:2000', Rule::requiredIf($workOrder->type === 'breakdown')],
            'action_taken' => ['required', 'string', 'max:2000'],
            'meter_reading' => ['nullable', 'numeric', 'min:0'],
        ]);
        $this->svc->complete($workOrder, $data);

        return back()->with('success', "{$workOrder->wo_number} completed – total cost ".inr($workOrder->total_cost).'.');
    }

    public function cancel(Request $request, WorkOrder $workOrder)
    {
        $data = $request->validate(['reason' => 'required|string|min:3|max:500']);
        $this->svc->cancel($workOrder, $data['reason']);

        return back()->with('success', "{$workOrder->wo_number} cancelled.");
    }

    private function validated(Request $request): array
    {
        $cid = Tenant::id();

        $data = $request->validate([
            'asset_id' => ['required', Rule::exists('assets', 'id')->where('company_id', $cid)->whereNull('deleted_at')],
            'pm_schedule_id' => ['nullable', Rule::exists('pm_schedules', 'id')->where('company_id', $cid)->where('asset_id', $request->asset_id)],
            'vendor_id' => ['nullable', Rule::exists('vendors', 'id')->where('company_id', $cid)->whereNull('deleted_at')],
            'type' => ['required', Rule::in(array_keys(WorkOrder::TYPES))],
            'priority' => ['required', Rule::in(array_keys(WorkOrder::PRIORITIES))],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'reported_date' => ['required', 'date', 'before_or_equal:today'],
            'scheduled_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'string', 'max:255'],
            'labour_cost' => ['nullable', 'numeric', 'min:0'],
            'parts_cost' => ['nullable', 'numeric', 'min:0'],
            'external_cost' => ['nullable', 'numeric', 'min:0'],
            'downtime_hours' => ['nullable', 'numeric', 'min:0'],
        ], ['pm_schedule_id.exists' => 'The PM schedule does not belong to the selected asset.']);
        foreach (['labour_cost', 'parts_cost', 'external_cost', 'downtime_hours'] as $f) {
            $data[$f] = (float) ($data[$f] ?? 0);
        }

        return $data;
    }

    private function lookups(): array
    {
        return [
            'assets' => Asset::whereHas('status', fn ($q) => $q->whereNotIn('code', ['DISPOSED', 'SCRAPPED']))->orderBy('asset_code')->get(['id', 'asset_code', 'name']),
            'vendors' => Vendor::orderBy('name')->pluck('name', 'id'),
            'pms' => PmSchedule::where('is_active', true)->orderBy('title')->get(['id', 'asset_id', 'title']),
        ];
    }
}
