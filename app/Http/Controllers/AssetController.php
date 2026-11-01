<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssetRequest;
use App\Models\Asset;
use App\Models\AssetAttachment;
use App\Models\AssetCategory;
use App\Models\AssetStatus;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\DepreciationMethod;
use App\Models\FinancialYear;
use App\Models\Location;
use App\Models\Vendor;
use App\Services\AssetCodeGenerator;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class AssetController extends Controller
{
    public const FILTERS = ['category' => 'asset_category_id', 'location' => 'location_id', 'department' => 'department_id', 'cost_center' => 'cost_center_id', 'status' => 'asset_status_id', 'vendor' => 'vendor_id'];

    public function __construct(protected AssetCodeGenerator $codes) {}

    /** Register tabs: key => label. Counts are computed live for the current search / filters. */
    public const TABS = ['all' => 'All assets', 'in_use' => 'In use', 'maintenance' => 'Under maintenance', 'fully_depreciated' => 'Fully depreciated', 'disposed' => 'Disposed', 'new_fy' => 'Added this FY'];

    public function index(Request $request): View
    {
        $base = Asset::query()->search($request->query('q'));

        foreach (self::FILTERS as $param => $col) {
            if ($request->filled($param)) {
                $base->where($col, $request->query($param));
            }
        }
        if ($request->filled('from')) {
            $base->whereDate('capitalization_date', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $base->whereDate('capitalization_date', '<=', $request->query('to'));
        }
        $inactive = $request->query('show') === 'inactive';
        if ($inactive) {
            $base->onlyTrashed();
        }

        $fy = FinancialYear::current();
        $tab = array_key_exists($request->query('tab'), self::TABS) && ! $inactive ? $request->query('tab') : 'all';
        $tabCounts = [];
        foreach (array_keys(self::TABS) as $key) {
            $tabCounts[$key] = $this->applyTab(clone $base, $key, $fy)->reorder()->count();
        }

        $query = $this->applyTab(clone $base, $tab, $fy)
            ->with(['category:id,name,code', 'location:id,name', 'department:id,name', 'costCenter:id,code,name', 'status:id,name,color,code']);

        $totals = (clone $query)->reorder()->toBase()
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(original_cost),0) as cost, COALESCE(SUM(accumulated_depreciation),0) as acc, COALESCE(SUM(current_book_value),0) as nbv')
            ->first();

        $sort = in_array($request->query('sort'), ['asset_code', 'name', 'capitalization_date', 'original_cost', 'accumulated_depreciation', 'current_book_value'], true) ? $request->query('sort') : 'asset_code';
        $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';

        return view('assets.index', [
            'assets' => $query->orderBy($sort, $dir)->paginate(20)->withQueryString(),
            'totals' => $totals,
            'lookups' => $this->lookups(),
            'sort' => $sort,
            'dir' => $dir,
            'tab' => $tab,
            'tabCounts' => $tabCounts,
            'inactive' => $inactive,
            'filtered' => collect(array_merge(array_keys(self::FILTERS), ['q', 'from', 'to']))->contains(fn ($k) => $request->filled($k)),
        ]);
    }

    /** Narrow a register query to one of the status tabs. DB-agnostic (plain column comparisons). */
    private function applyTab($query, string $tab, ?FinancialYear $fy)
    {
        $closed = array_filter([AssetStatus::idFor(AssetStatus::DISPOSED), AssetStatus::idFor(AssetStatus::SCRAPPED)]);

        return match ($tab) {
            'in_use' => $query->where('asset_status_id', AssetStatus::idFor(AssetStatus::ACTIVE)),
            'maintenance' => $query->where('asset_status_id', AssetStatus::idFor(AssetStatus::UNDER_MAINTENANCE)),
            'disposed' => $query->whereIn('asset_status_id', $closed ?: [0]),
            'fully_depreciated' => $query->whereNotIn('asset_status_id', $closed ?: [0])
                ->where('original_cost', '>', 0)->whereColumn('current_book_value', '<=', 'residual_value'),
            'new_fy' => $fy ? $query->whereDate('capitalization_date', '>=', $fy->start_date)->whereDate('capitalization_date', '<=', $fy->end_date) : $query->whereRaw('1 = 0'),
            default => $query,
        };
    }

    /** JSON for the command palette: tenant-scoped asset search (global scope applies). */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 1) {
            return response()->json(['data' => []]);
        }
        $assets = Asset::query()->search($q)
            ->with(['location:id,name', 'category:id,name', 'status:id,name,color,code'])
            ->orderBy('asset_code')->limit(8)
            ->get(['id', 'asset_code', 'name', 'location_id', 'asset_category_id', 'asset_status_id', 'current_book_value']);

        return response()->json(['data' => $assets->map(fn (Asset $a) => [
            'id' => $a->id,
            'code' => $a->asset_code,
            'name' => $a->name,
            'location' => $a->location?->name,
            'category' => $a->category?->name,
            'status' => $a->status?->name,
            'color' => $a->status?->color ?? 'slate',
            'nbv' => inr_short((float) $a->current_book_value),
            'url' => route('assets.show', $a),
        ])->values()]);
    }

    /** Printable sheet of QR labels for a selection of assets (all roles can print). */
    public function labels(Request $request): View
    {
        $ids = collect((array) $request->query('ids', []))->map(fn ($v) => (int) $v)->filter()->unique()->take(200)->values();
        abort_if($ids->isEmpty(), 404);
        $assets = Asset::with(['category', 'location'])->whereIn('id', $ids)->orderBy('asset_code')->get();
        abort_if($assets->isEmpty(), 404);

        return view('assets.labels', [
            'assets' => $assets,
            'qr' => $assets->mapWithKeys(fn ($a) => [$a->id => $this->qrSvg($a, 180)]),
        ]);
    }

    public function create(Request $request): View
    {
        $asset = new Asset([
            'asset_status_id' => AssetStatus::idFor(AssetStatus::ACTIVE),
            'quantity' => 1,
            'unit_of_measure' => 'Nos',
            'capitalization_date' => now()->toDateString(),
        ]);
        if ($request->filled('category')) {
            $asset->asset_category_id = (int) $request->query('category');
        }

        return view('assets.form', ['asset' => $asset, 'lookups' => $this->lookups(true), 'categoryData' => $this->categoryData()]);
    }

    public function store(AssetRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['attachments', 'attachment_type']);

        $asset = DB::transaction(function () use ($data, $request) {
            $category = AssetCategory::findOrFail($data['asset_category_id']);
            $data['asset_code'] = $data['asset_code'] ?: $this->codes->next($category);
            $asset = new Asset($data);
            $asset->created_by = $asset->updated_by = auth()->id();
            $asset->save();
            $this->storeFiles($asset, $request->file('attachments', []), $request->input('attachment_type', 'other'));

            return $asset;
        });

        return redirect()->route('assets.show', $asset)->with('success', "Asset {$asset->asset_code} – {$asset->name} added to the register.");
    }

    public function show(Asset $asset): View
    {
        $asset->load(['category', 'location', 'department', 'costCenter', 'vendor', 'depreciationMethod', 'status', 'parentAsset', 'components.category', 'components.status', 'attachments.uploader', 'creator', 'updater']);

        $asset->load([
            'transfers.fromLocation', 'transfers.toLocation', 'transfers.fromDepartment', 'transfers.toDepartment',
            'transfers.fromCostCenter', 'transfers.toCostCenter', 'transfers.requestedBy',
            'pmSchedules' => fn ($q) => $q->orderByDesc('is_active')->orderBy('next_due_date'),
            'workOrders' => fn ($q) => $q->limit(25), 'disposals.creator',
        ]);
        $entries = $asset->depreciationEntries()->with('run:id,run_number,status,run_type')->get();

        return view('assets.show', [
            'asset' => $asset,
            'entries' => $entries,
            'maintenanceCost' => (float) $asset->workOrders()->where('status', 'completed')->sum('total_cost'),
            'qrSvg' => $this->qrSvg($asset, 220),
            'history' => $asset->auditLogs()->limit(20)->get(),
            'lifeChart' => $this->lifeChart($asset, $entries),
        ]);
    }

    /**
     * Book value over life: actual points from the posted schedule (closing WDV per period),
     * then a projection from the latest book value down to residual at end of life.
     * Everything is derived from the asset's own figures – nothing is invented.
     */
    private function lifeChart(Asset $asset, $entries): array
    {
        $cost = (float) $asset->original_cost;
        $residual = (float) $asset->residual_value;
        $ms = fn ($d) => $d->copy()->startOfDay()->getTimestamp() * 1000;
        $actual = [];
        if ($asset->capitalization_date && $cost > 0) {
            $actual[] = ['x' => $ms($asset->capitalization_date), 'y' => round($cost, 2)];
        }
        if ($asset->opening_balance_date && (float) $asset->opening_accumulated_depreciation > 0) {
            $actual[] = ['x' => $ms($asset->opening_balance_date), 'y' => round($cost - (float) $asset->opening_accumulated_depreciation, 2)];
        }
        foreach ($entries->filter(fn ($e) => $e->run?->status !== 'reversed') as $e) {
            $actual[] = ['x' => $ms($e->period_end), 'y' => round((float) $e->closing_wdv, 2)];
        }
        usort($actual, fn ($a, $b) => $a['x'] <=> $b['x']);

        $projected = [];
        $end = $asset->end_of_life_date;
        $last = end($actual) ?: null;
        if ($last && $end && ! $asset->isDisposed() && $end->getTimestamp() * 1000 > $last['x'] && $last['y'] > $residual) {
            $from = \Illuminate\Support\Carbon::createFromTimestampMs($last['x']);
            $projected[] = $last;
            $rate = (float) $asset->wdv_rate;
            if ($asset->depreciationMethod?->code === 'WDV' && $rate > 0) {
                $v = $last['y'];
                for ($d = $from->copy()->addYear(); $d->lt($end); $d->addYear()) {
                    $v = max($residual, $v * (1 - $rate / 100));
                    $projected[] = ['x' => $ms($d), 'y' => round($v, 2)];
                }
            }
            $projected[] = ['x' => $ms($end), 'y' => round($residual, 2)];
        }
        $lastEntry = $entries->filter(fn ($e) => $e->run?->status !== 'reversed' && $e->run?->run_type !== 'disposal')->last();

        return [
            'actual' => $actual,
            'projected' => $projected,
            'residual' => round($residual, 2),
            'cost' => round($cost, 2),
            'lastCharge' => $lastEntry ? ['amount' => (float) $lastEntry->depreciation_amount, 'days' => (int) $lastEntry->days, 'period' => $lastEntry->period_end?->format('M Y')] : null,
        ];
    }

    public function edit(Asset $asset): View|RedirectResponse
    {
        if ($asset->isDisposed()) {
            return redirect()->route('assets.show', $asset)->with('error', 'This asset has been disposed and is read-only.');
        }
        return view('assets.form', ['asset' => $asset, 'lookups' => $this->lookups(true, $asset), 'categoryData' => $this->categoryData()]);
    }

    public function update(AssetRequest $request, Asset $asset): RedirectResponse
    {
        $data = $request->safe()->except(['attachments', 'attachment_type']);
        if (empty($data['asset_code'])) {
            unset($data['asset_code']); // keep existing code
        }

        DB::transaction(function () use ($asset, $data, $request) {
            $asset->fill($data);
            $asset->updated_by = auth()->id();
            $asset->save();
            $this->storeFiles($asset, $request->file('attachments', []), $request->input('attachment_type', 'other'));
        });

        return redirect()->route('assets.show', $asset)->with('success', "Asset {$asset->asset_code} updated.");
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        if ($asset->isDisposed()) {
            return back()->with('error', 'A disposed asset is part of the audit record and cannot be deactivated.');
        }
        if ($asset->depreciationEntries()->exists()) {
            return back()->with('error', "{$asset->asset_code} has posted depreciation. Deactivating it would break the books – dispose / scrap it instead, or reverse its depreciation runs first.");
        }
        $asset->delete();

        return redirect()->route('assets.index')->with('success', "Asset {$asset->asset_code} deactivated. It can be restored from the Inactive view.");
    }

    public function restore(int $id): RedirectResponse
    {
        $asset = Asset::onlyTrashed()->findOrFail($id);
        $asset->restore();

        return redirect()->route('assets.show', $asset)->with('success', "Asset {$asset->asset_code} restored.");
    }

    /** JSON: next code for a category (used by the form preview). */
    public function nextCode(AssetCategory $category)
    {
        return response()->json(['code' => $this->codes->preview($category)]);
    }

    public function qr(Request $request, Asset $asset): Response
    {
        if ($request->query('format') === 'png') {
            $png = (new PngWriter)->write($this->qrCode($asset, 600))->getString();

            return response($png, 200, ['Content-Type' => 'image/png', 'Content-Disposition' => 'attachment; filename="'.$asset->asset_code.'-qr.png"']);
        }

        return response($this->qrSvg($asset, 300), 200, ['Content-Type' => 'image/svg+xml']);
    }

    public function label(Asset $asset): View
    {
        $asset->load(['category', 'location']);

        return view('assets.label', ['asset' => $asset, 'qrSvg' => $this->qrSvg($asset, 180)]);
    }

    // ---- Attachments ------------------------------------------------------
    public function uploadAttachment(Request $request, Asset $asset): RedirectResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'max:10'],
            'files.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx'],
            'type' => ['required', 'in:'.implode(',', array_keys(AssetAttachment::TYPES))],
            'title' => ['nullable', 'string', 'max:255'],
        ]);
        $n = $this->storeFiles($asset, $request->file('files'), $request->input('type'), $request->input('title'));

        return back()->with('success', $n.' '.str('file')->plural($n).' attached.');
    }

    public function destroyAttachment(Asset $asset, AssetAttachment $attachment): RedirectResponse
    {
        abort_unless($attachment->asset_id === $asset->id, 404);
        $attachment->delete(); // soft delete; file is retained for audit

        return back()->with('success', 'Attachment removed.');
    }

    // ---- Helpers ------------------------------------------------------------
    protected function storeFiles(Asset $asset, array $files, string $type, ?string $title = null): int
    {
        $n = 0;
        foreach ($files as $file) {
            /** @var UploadedFile $file */
            $path = $file->store('assets/'.$asset->company_id.'/'.$asset->id, 'public');
            $asset->attachments()->create([
                'type' => $type,
                'title' => $title ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => auth()->id(),
            ]);
            $n++;
        }

        return $n;
    }

    protected function qrCode(Asset $asset, int $size): QrCode
    {
        return new QrCode(data: $asset->qr_url, errorCorrectionLevel: ErrorCorrectionLevel::Medium, size: $size, margin: 8);
    }

    protected function qrSvg(Asset $asset, int $size): string
    {
        return (new SvgWriter)->write($this->qrCode($asset, $size), options: [SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true])->getString();
    }

    protected function lookups(bool $forForm = false, ?Asset $asset = null): array
    {
        $opt = fn ($q, $label = 'name') => $q->orderBy($label)->get()->mapWithKeys(fn ($m) => [$m->id => $m->code ? "{$m->code} – {$m->name}" : $m->name])->all();

        $data = [
            'categories' => $opt(AssetCategory::query()),
            'locations' => $opt(Location::query()),
            'departments' => $opt(Department::query()),
            'costCenters' => $opt(CostCenter::query(), 'code'),
            'statuses' => AssetStatus::orderBy('sort_order')->pluck('name', 'id')->all(),
            'vendors' => $opt(Vendor::query()),
        ];
        if ($forForm) {
            $data['methods'] = DepreciationMethod::orderBy('code')->get()->mapWithKeys(fn ($m) => [$m->id => $m->label])->all();
            $data['methodCodes'] = DepreciationMethod::pluck('code', 'id')->all();
            $data['parents'] = Asset::query()->when($asset?->exists, fn ($q) => $q->whereKeyNot($asset->id))
                ->orderBy('asset_code')->limit(500)->get(['id', 'asset_code', 'name'])
                ->mapWithKeys(fn ($a) => [$a->id => "{$a->asset_code} – {$a->name}"])->all();
        }

        return $data;
    }

    /** Category defaults pushed to the form (Alpine) so picking a category pre-fills depreciation fields. */
    protected function categoryData(): array
    {
        return AssetCategory::all()->mapWithKeys(fn ($c) => [$c->id => [
            'method' => $c->depreciation_method_id,
            'years' => $c->useful_life_years,
            'months' => $c->useful_life_months,
            'residual_pct' => (float) $c->residual_value_percent,
            'wdv_rate' => $c->wdv_rate !== null ? (float) $c->wdv_rate : null,
            'next_code' => $this->codes->preview($c),
            'threshold' => (float) $c->capitalization_threshold,
            'schedule' => $c->schedule_ii_reference,
        ]])->all();
    }
}
