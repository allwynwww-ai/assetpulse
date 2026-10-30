<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetDisposal;
use App\Models\AssetStatus;
use App\Models\DepreciationEntry;
use App\Models\PmSchedule;
use App\Models\WorkOrder;
use App\Services\Depreciation\DepreciationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sale / scrap / write-off of an asset. Charges pro-rata depreciation up to
 * the disposal date (a one-asset "disposal" run), freezes the asset and books
 * profit or loss = net proceeds − NBV.
 */
class DisposalService
{
    public function __construct(private DepreciationService $dep) {}

    /** Figures shown on the form before saving. */
    public function preview(Asset $asset, $date): array
    {
        $d = Carbon::parse($date)->startOfDay();
        $charge = 0.0;
        $through = $asset->last_depreciation_date ?? $asset->opening_balance_date;
        if (! $through || $through->lt($d)) {
            $from = $through ? $through->copy()->addDay() : $asset->capitalization_date;
            $r = $this->dep->calculator()->calculate($this->dep->assetInput($asset, $from->toDateString()), $from->toDateString(), $d->toDateString(), $d->toDateString());
            $charge = $r->skipReason ? 0 : $r->amount;
        }
        $acc = round((float) $asset->accumulated_depreciation + $charge, 2);

        return ['charge' => $charge, 'accumulated' => $acc, 'gross' => (float) $asset->original_cost, 'nbv' => round((float) $asset->original_cost - $acc, 2)];
    }

    public function dispose(Asset $asset, array $data): AssetDisposal
    {
        $date = Carbon::parse($data['disposal_date'])->startOfDay();
        $fail = fn ($f, $m) => throw ValidationException::withMessages([$f => $m]);
        if ($asset->isDisposed() || $asset->disposals()->where('status', 'completed')->exists()) {
            $fail('asset_id', 'This asset has already been disposed.');
        }
        if ($asset->capitalization_date && $date->lt($asset->capitalization_date)) {
            $fail('disposal_date', 'Disposal date cannot be before the put-to-use date ('.fmt_date($asset->capitalization_date).').');
        }
        if ($date->isAfter(now()->endOfDay())) {
            $fail('disposal_date', 'Disposal date cannot be in the future.');
        }
        PeriodGuard::assertOpen($date, 'disposal_date', 'The disposal');
        if ($asset->last_depreciation_date && $asset->last_depreciation_date->gt($date)) {
            $fail('disposal_date', 'Depreciation has already been posted up to '.fmt_date($asset->last_depreciation_date).', after the disposal date. Reverse that depreciation run first, then record the disposal.');
        }

        return DB::transaction(function () use ($asset, $data, $date) {
            $before = (float) $asset->accumulated_depreciation;
            $through = $asset->last_depreciation_date ?? $asset->opening_balance_date;
            $charge = 0.0;
            if ($asset->status?->is_depreciable !== false && (! $through || $through->lt($date)) && $this->preview($asset, $date)['charge'] > 0) {
                $from = $through ? $through->copy()->addDay() : $asset->capitalization_date->copy();
                $run = $this->dep->post($from, $date, null, 'Pro-rata depreciation to disposal date for '.$asset->asset_code, 'disposal', collect([$asset]), $date->toDateString());
                $charge = (float) $run->total_depreciation;
                $asset->refresh();
            }

            $type = $data['disposal_type'];
            $disp = AssetDisposal::create([
                'asset_id' => $asset->id,
                'disposal_number' => DocumentNumber::next(AssetDisposal::class, 'disposal_number', 'DSP', $date),
                'disposal_date' => $date->toDateString(),
                'disposal_type' => $type,
                'buyer_name' => $data['buyer_name'] ?? null,
                'buyer_gstin' => $data['buyer_gstin'] ?? null,
                'sale_invoice_number' => $data['sale_invoice_number'] ?? null,
                'sale_value' => $data['sale_value'] ?? 0,
                'disposal_cost' => $data['disposal_cost'] ?? 0,
                'gross_block_at_disposal' => $asset->original_cost,
                'accumulated_depreciation_at_disposal' => $asset->accumulated_depreciation,
                'depreciation_upto_disposal' => round($charge, 2),
                'reason' => $data['reason'],
                'status' => 'completed',
                'approved_by' => Auth::id(), 'approved_at' => now(),
                'created_by' => Auth::id(),
            ]);

            $asset->asset_status_id = AssetStatus::idFor($type === 'scrap' ? AssetStatus::SCRAPPED : AssetStatus::DISPOSED);
            $asset->updated_by = Auth::id();
            $asset->save();
            PmSchedule::where('asset_id', $asset->id)->where('is_active', true)->get()->each(fn ($pm) => $pm->update(['is_active' => false]));
            WorkOrder::where('asset_id', $asset->id)->whereIn('status', ['open', 'in_progress', 'on_hold'])->get()
                ->each(fn ($wo) => $wo->update(['status' => 'cancelled', 'action_taken' => trim(($wo->action_taken ?? '')."\nCancelled: asset disposed ({$disp->disposal_number})")]));

            return $disp;
        });
    }
}
