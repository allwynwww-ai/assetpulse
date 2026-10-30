<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetTransfer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves an asset between location / department / cost centre. Transfers are
 * effective immediately (approval-free) and fully audited; the asset keeps a
 * dated history so depreciation can be reported by cost centre as at any date.
 */
class TransferService
{
    public function transfer(Asset $asset, array $data): AssetTransfer
    {
        $date = Carbon::parse($data['transfer_date'])->startOfDay();
        $fail = fn ($f, $m) => throw ValidationException::withMessages([$f => $m]);

        if ($asset->isDisposed()) {
            $fail('asset_id', 'This asset has been disposed and cannot be transferred.');
        }
        if ($asset->capitalization_date && $date->lt($asset->capitalization_date)) {
            $fail('transfer_date', 'Transfer date cannot be before the put-to-use date ('.fmt_date($asset->capitalization_date).').');
        }
        if ($date->isAfter(now()->endOfDay())) {
            $fail('transfer_date', 'Transfer date cannot be in the future.');
        }
        PeriodGuard::assertOpen($date, 'transfer_date', 'The transfer');
        $latest = $asset->transfers()->reorder()->where('status', 'completed')->max('transfer_date');
        if ($latest && $date->lt(Carbon::parse($latest))) {
            $fail('transfer_date', 'A later transfer already exists (dated '.fmt_date($latest).'). Transfers must be recorded in date order.');
        }
        $to = [
            'location_id' => $data['to_location_id'] ?? $asset->location_id,
            'department_id' => $data['to_department_id'] ?? $asset->department_id,
            'cost_center_id' => $data['to_cost_center_id'] ?? $asset->cost_center_id,
            'custodian' => array_key_exists('to_custodian', $data) && $data['to_custodian'] !== null && $data['to_custodian'] !== '' ? $data['to_custodian'] : $asset->custodian,
        ];
        if ($to['location_id'] == $asset->location_id && $to['department_id'] == $asset->department_id
            && $to['cost_center_id'] == $asset->cost_center_id && $to['custodian'] == $asset->custodian) {
            $fail('to_location_id', 'Nothing changes – choose a different location, department, cost centre or custodian.');
        }

        return DB::transaction(function () use ($asset, $data, $date, $to) {
            $t = AssetTransfer::create([
                'asset_id' => $asset->id,
                'transfer_number' => DocumentNumber::next(AssetTransfer::class, 'transfer_number', 'TRF', $date),
                'transfer_date' => $date->toDateString(),
                'from_location_id' => $asset->location_id, 'to_location_id' => $to['location_id'],
                'from_department_id' => $asset->department_id, 'to_department_id' => $to['department_id'],
                'from_cost_center_id' => $asset->cost_center_id, 'to_cost_center_id' => $to['cost_center_id'],
                'from_custodian' => $asset->custodian, 'to_custodian' => $to['custodian'],
                'reason' => $data['reason'],
                'status' => 'completed',
                'affects_depreciation' => (bool) ($data['affects_depreciation'] ?? false),
                'requested_by' => Auth::id(), 'approved_by' => Auth::id(), 'approved_at' => now(),
                'remarks' => $data['remarks'] ?? null,
            ]);
            $asset->fill($to);
            $asset->updated_by = Auth::id();
            $asset->save();

            return $t;
        });
    }
}
