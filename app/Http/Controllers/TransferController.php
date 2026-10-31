<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\Location;
use App\Services\TransferService;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransferController extends Controller
{
    public function index(Request $request)
    {
        $transfers = AssetTransfer::with(['asset:id,asset_code,name', 'fromLocation:id,name', 'toLocation:id,name', 'fromDepartment:id,name', 'toDepartment:id,name', 'fromCostCenter:id,code', 'toCostCenter:id,code', 'requestedBy:id,name'])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->whereLike('transfer_number', "%{$request->q}%")->orWhereLike('reason', "%{$request->q}%")
                ->orWhereHas('asset', fn ($a) => $a->withTrashed()->whereLike('asset_code', "%{$request->q}%")->orWhereLike('name', "%{$request->q}%"))))
            ->when($request->filled('location'), fn ($q) => $q->where(fn ($w) => $w->where('from_location_id', $request->location)->orWhere('to_location_id', $request->location)))
            ->when($request->filled('from'), fn ($q) => $q->where('transfer_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->where('transfer_date', '<=', $request->to))
            ->orderByDesc('transfer_date')->orderByDesc('id')
            ->paginate(20)->withQueryString();
        $locations = Location::orderBy('name')->pluck('name', 'id');

        return view('transfers.index', compact('transfers', 'locations'));
    }

    public function create(Request $request)
    {
        $asset = $request->filled('asset') ? Asset::with(['location', 'department', 'costCenter'])->findOrFail($request->asset) : null;
        abort_if($asset?->isDisposed(), 422, 'This asset has been disposed and cannot be transferred.');

        return view('transfers.create', $this->lookups() + ['asset' => $asset]);
    }

    public function store(Request $request, TransferService $service)
    {
        $cid = Tenant::id();
        $exists = fn ($t) => Rule::exists($t, 'id')->where('company_id', $cid)->whereNull('deleted_at');
        $data = $request->validate([
            'asset_id' => ['required', $exists('assets')],
            'transfer_date' => ['required', 'date', 'before_or_equal:today'],
            'to_location_id' => ['required', $exists('locations')],
            'to_department_id' => ['nullable', $exists('departments')],
            'to_cost_center_id' => ['nullable', $exists('cost_centers')],
            'to_custodian' => ['nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'affects_depreciation' => ['nullable', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ], [], ['to_location_id' => 'destination location', 'to_department_id' => 'department', 'to_cost_center_id' => 'cost center']);
        $asset = Asset::findOrFail($data['asset_id']);
        $t = $service->transfer($asset, $data + ['affects_depreciation' => $request->boolean('affects_depreciation')]);

        return redirect()->route('assets.show', $asset)->with('success', "Transfer {$t->transfer_number} recorded – {$asset->asset_code} moved to {$t->toLocation?->name}.");
    }

    public function show(AssetTransfer $transfer)
    {
        $transfer->load(['asset', 'fromLocation', 'toLocation', 'fromDepartment', 'toDepartment', 'fromCostCenter', 'toCostCenter', 'requestedBy', 'auditLogs']);

        return view('transfers.show', compact('transfer'));
    }

    private function lookups(): array
    {
        return [
            'assets' => Asset::whereDoesntHave('disposals', fn ($q) => $q->where('status', 'completed'))->orderBy('asset_code')->get(['id', 'asset_code', 'name', 'location_id', 'department_id', 'cost_center_id', 'custodian']),
            'locations' => Location::orderBy('name')->pluck('name', 'id'),
            'departments' => Department::orderBy('name')->pluck('name', 'id'),
            'costCenters' => CostCenter::orderBy('code')->get()->mapWithKeys(fn ($c) => [$c->id => $c->code.' – '.$c->name]),
        ];
    }
}
