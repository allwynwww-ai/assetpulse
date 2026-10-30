<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\PmSchedule;
use App\Models\Vendor;
use App\Services\MaintenanceService;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PmScheduleController extends Controller
{
    public function index(Request $request)
    {
        $view = $request->input('view', 'all');
        $today = now()->toDateString();
        $q = PmSchedule::with(['asset:id,asset_code,name,location_id', 'asset.location:id,name', 'vendor:id,name'])
            ->whereHas('asset')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('title', 'like', "%{$request->q}%")->orWhere('code', 'like', "%{$request->q}%")
                ->orWhereHas('asset', fn ($a) => $a->where('asset_code', 'like', "%{$request->q}%")->orWhere('name', 'like', "%{$request->q}%"))));
        match ($view) {
            'overdue' => $q->where('is_active', true)->where('next_due_date', '<', $today),
            'due30' => $q->where('is_active', true)->whereBetween('next_due_date', [$today, now()->addDays(30)->toDateString()]),
            'inactive' => $q->where('is_active', false),
            default => $q->where('is_active', true),
        };
        $schedules = $q->orderByRaw('next_due_date IS NULL')->orderBy('next_due_date')->paginate(25)->withQueryString();
        $counts = [
            'overdue' => PmSchedule::whereHas('asset')->where('is_active', true)->where('next_due_date', '<', $today)->count(),
            'due30' => PmSchedule::whereHas('asset')->where('is_active', true)->whereBetween('next_due_date', [$today, now()->addDays(30)->toDateString()])->count(),
            'all' => PmSchedule::whereHas('asset')->where('is_active', true)->count(),
        ];

        return view('maintenance.pm-index', compact('schedules', 'counts', 'view'));
    }

    public function create(Request $request)
    {
        $pm = new PmSchedule(['frequency_unit' => 'months', 'frequency_interval' => 1, 'alert_days_before' => 7, 'start_date' => now(), 'asset_id' => $request->asset, 'is_active' => true]);

        return view('maintenance.pm-form', $this->lookups() + ['pm' => $pm]);
    }

    public function store(Request $request, MaintenanceService $svc)
    {
        $pm = new PmSchedule($this->validated($request));
        $this->guardAsset($pm->asset_id);
        $pm->next_due_date = $request->filled('next_due_date') ? $request->next_due_date : $svc->nextDue($pm)?->toDateString();
        $pm->save();

        return redirect()->route('pm-schedules.index')->with('success', "PM schedule “{$pm->title}” created – next due ".fmt_date($pm->next_due_date).'.');
    }

    public function edit(PmSchedule $pmSchedule)
    {
        return view('maintenance.pm-form', $this->lookups() + ['pm' => $pmSchedule]);
    }

    public function update(Request $request, PmSchedule $pmSchedule, MaintenanceService $svc)
    {
        $pmSchedule->fill($this->validated($request));
        if ($request->filled('next_due_date') && $request->next_due_date !== optional($pmSchedule->getOriginal('next_due_date'))->toDateString()) {
            $pmSchedule->next_due_date = $request->next_due_date;
        } else {
            $svc->refreshNextDue($pmSchedule);
        }
        $pmSchedule->save();

        return redirect()->route('pm-schedules.index')->with('success', "PM schedule updated – next due ".fmt_date($pmSchedule->next_due_date).'.');
    }

    public function destroy(PmSchedule $pmSchedule)
    {
        $pmSchedule->delete();

        return back()->with('success', 'PM schedule removed.');
    }

    private function validated(Request $request): array
    {
        $cid = Tenant::id();
        $data = $request->validate([
            'asset_id' => ['required', Rule::exists('assets', 'id')->where('company_id', $cid)->whereNull('deleted_at')],
            'vendor_id' => ['nullable', Rule::exists('vendors', 'id')->where('company_id', $cid)->whereNull('deleted_at')],
            'code' => ['nullable', 'string', 'max:30'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'frequency_unit' => ['required', Rule::in(array_keys(PmSchedule::FREQUENCY_UNITS))],
            'frequency_interval' => ['required', 'integer', 'min:1', 'max:1000000'],
            'start_date' => ['required', 'date'],
            'last_done_date' => ['nullable', 'date', 'before_or_equal:today'],
            'next_due_date' => ['nullable', 'date'],
            'meter_last_service_reading' => ['nullable', 'numeric', 'min:0', 'required_if:frequency_unit,running_hours,shots'],
            'meter_current_reading' => ['nullable', 'numeric', 'gte:meter_last_service_reading', 'required_if:frequency_unit,running_hours,shots'],
            'meter_daily_average' => ['nullable', 'numeric', 'min:0', 'required_if:frequency_unit,running_hours,shots'],
            'alert_days_before' => ['required', 'integer', 'min:0', 'max:365'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0'],
            'assigned_to' => ['nullable', 'string', 'max:255'],
            'checklist' => ['nullable', 'string', 'max:4000'],
        ], ['meter_current_reading.gte' => 'Current reading cannot be lower than the reading at last service.']);
        $data['is_active'] = $request->boolean('is_active');
        $data['estimated_cost'] ??= 0;
        unset($data['next_due_date']);

        return $data;
    }

    private function guardAsset($id): void
    {
        $a = Asset::find($id);
        abort_if(! $a || $a->isDisposed(), 422, 'PM cannot be scheduled for a disposed asset.');
    }

    private function lookups(): array
    {
        return [
            'assets' => Asset::whereHas('status', fn ($q) => $q->whereNotIn('code', ['DISPOSED', 'SCRAPPED']))->orderBy('asset_code')->get(['id', 'asset_code', 'name']),
            'vendors' => Vendor::orderBy('name')->pluck('name', 'id'),
        ];
    }
}
