<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetDisposal;
use App\Services\DisposalService;
use App\Services\PeriodGuard;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DisposalController extends Controller
{
    public function __construct(private DisposalService $svc) {}

    public function index(Request $request)
    {
        $q = AssetDisposal::with(['asset:id,asset_code,name,asset_category_id', 'asset.category:id,name'])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->whereLike('disposal_number', "%{$request->q}%")->orWhereLike('buyer_name', "%{$request->q}%")
                ->orWhereHas('asset', fn ($a) => $a->withTrashed()->whereLike('asset_code', "%{$request->q}%")->orWhereLike('name', "%{$request->q}%"))))
            ->when($request->filled('type'), fn ($q) => $q->where('disposal_type', $request->type))
            ->when($request->filled('from'), fn ($q) => $q->where('disposal_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->where('disposal_date', '<=', $request->to));
        $totals = (clone $q)->toBase()->selectRaw('COUNT(*) n, COALESCE(SUM(gross_block_at_disposal),0) gross, COALESCE(SUM(nbv_at_disposal),0) nbv, COALESCE(SUM(sale_value),0) proceeds, COALESCE(SUM(profit_loss),0) pl')->first();
        $disposals = $q->orderByDesc('disposal_date')->orderByDesc('id')->paginate(20)->withQueryString();

        return view('disposals.index', compact('disposals', 'totals'));
    }

    public function create(Request $request)
    {
        $asset = $request->filled('asset') ? Asset::with(['category', 'status'])->findOrFail($request->asset) : null;
        if ($asset?->isDisposed()) {
            return redirect()->route('assets.show', $asset)->with('error', 'This asset has already been disposed.');
        }
        $assets = Asset::whereHas('status', fn ($q) => $q->whereNotIn('code', ['DISPOSED', 'SCRAPPED']))->where('original_cost', '>', 0)->orderBy('asset_code')->get(['id', 'asset_code', 'name']);

        return view('disposals.create', ['asset' => $asset, 'assets' => $assets, 'lockedThrough' => PeriodGuard::lockedThrough()]);
    }

    /** JSON preview of pro-rata depreciation, NBV and the P/L for the form. */
    public function preview(Request $request)
    {
        $request->validate(['asset_id' => 'required|integer', 'disposal_date' => 'required|date']);
        $asset = Asset::findOrFail($request->asset_id);
        $p = $this->svc->preview($asset, $request->disposal_date);
        $net = (float) $request->input('sale_value', 0) - (float) $request->input('disposal_cost', 0);
        $warning = null;
        if (PeriodGuard::isLocked($request->disposal_date)) {
            $warning = 'This date falls in a locked period.';
        } elseif ($asset->last_depreciation_date && $asset->last_depreciation_date->gt($request->disposal_date)) {
            $warning = 'Depreciation is posted up to '.fmt_date($asset->last_depreciation_date).'; reverse that run first or pick a later date.';
        }

        return response()->json($p + [
            'last_dep' => fmt_date($asset->last_depreciation_date), 'pl' => round($net - $p['nbv'], 2), 'warning' => $warning,
            'fmt' => ['charge' => inr($p['charge']), 'accumulated' => inr($p['accumulated']), 'gross' => inr($p['gross']), 'nbv' => inr($p['nbv']), 'pl' => inr(abs($net - $p['nbv']))],
        ]);
    }

    public function store(Request $request)
    {
        $cid = Tenant::id();
        $data = $request->validate([
            'asset_id' => ['required', Rule::exists('assets', 'id')->where('company_id', $cid)->whereNull('deleted_at')],
            'disposal_date' => ['required', 'date', 'before_or_equal:today'],
            'disposal_type' => ['required', Rule::in(array_keys(AssetDisposal::TYPES))],
            'buyer_name' => ['nullable', 'string', 'max:255', 'required_if:disposal_type,sale'],
            'buyer_gstin' => ['nullable', 'string', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'sale_invoice_number' => ['nullable', 'string', 'max:60'],
            'sale_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999', 'required_if:disposal_type,sale', Rule::when($request->disposal_type === 'sale', 'gt:0')],
            'disposal_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'confirm' => ['accepted'],
        ], ['buyer_gstin.regex' => 'Enter a valid 15-character GSTIN.', 'confirm.accepted' => 'Please confirm – disposal is final and makes the asset read-only.', 'sale_value.gt' => 'A sale needs a sale value above zero.']);
        $asset = Asset::findOrFail($data['asset_id']);
        $d = $this->svc->dispose($asset, $data);
        $pl = (float) $d->profit_loss;

        return redirect()->route('disposals.show', $d)->with('success', "{$asset->asset_code} disposed ({$d->disposal_number}). ".($pl >= 0 ? 'Profit' : 'Loss').' on disposal: '.inr(abs($pl)).'.');
    }

    public function show(AssetDisposal $disposal)
    {
        $disposal->load(['asset.category', 'asset.location', 'asset.status', 'creator', 'approvedBy', 'auditLogs']);

        return view('disposals.show', ['d' => $disposal]);
    }
}
