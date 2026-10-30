<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\PmSchedule;
use App\Models\WorkOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Preventive-maintenance scheduling and the work-order lifecycle
 * (open → in progress → completed / cancelled). An open breakdown puts the
 * asset into "Under Maintenance" until every breakdown is closed.
 */
class MaintenanceService
{
    /** Next due date for a PM: calendar units add the interval; meter units forecast from the daily average. */
    public function nextDue(PmSchedule $pm): ?Carbon
    {
        if (in_array($pm->frequency_unit, ['running_hours', 'shots'], true)) {
            $avg = (float) $pm->meter_daily_average;
            $base = $pm->last_done_date ?? $pm->start_date ?? now();
            if ($avg <= 0) {
                return $pm->next_due_date ?? $base->copy();
            }
            $since = max(0, (float) $pm->meter_current_reading - (float) $pm->meter_last_service_reading);
            $left = (float) $pm->frequency_interval - $since;
            // Forecast is from today for the remaining units (reading taken today)
            return now()->startOfDay()->addDays((int) floor($left / $avg));
        }

        return $pm->computeNextDue(($pm->last_done_date ?? $pm->start_date ?? now())->copy());
    }

    public function refreshNextDue(PmSchedule $pm): PmSchedule
    {
        $pm->next_due_date = $this->nextDue($pm)?->toDateString();

        return $pm;
    }

    public function createWorkOrder(Asset $asset, array $data): WorkOrder
    {
        if ($asset->isDisposed()) {
            throw ValidationException::withMessages(['asset_id' => 'Work orders cannot be raised on a disposed asset.']);
        }

        return DB::transaction(function () use ($asset, $data) {
            $wo = new WorkOrder($data);
            $wo->asset_id = $asset->id;
            $wo->status = $data['status'] ?? 'open';
            $wo->wo_number = DocumentNumber::next(WorkOrder::class, 'wo_number', 'WO', $data['reported_date'] ?? now());
            $wo->created_by = Auth::id();
            $wo->save();
            $this->syncAssetStatus($asset);

            return $wo;
        });
    }

    public function start(WorkOrder $wo, $at = null): WorkOrder
    {
        $this->assertOpen($wo);
        $wo->update(['status' => 'in_progress', 'started_at' => $wo->started_at ?? Carbon::parse($at ?? now())]);
        $this->syncAssetStatus($wo->asset);

        return $wo;
    }

    public function complete(WorkOrder $wo, array $data): WorkOrder
    {
        $this->assertOpen($wo);
        $completedAt = Carbon::parse($data['completed_at'] ?? now());
        if ($completedAt->lt($wo->reported_date)) {
            throw ValidationException::withMessages(['completed_at' => 'Completion cannot be before the reported date ('.fmt_date($wo->reported_date).').']);
        }

        return DB::transaction(function () use ($wo, $data, $completedAt) {
            $wo->fill(collect($data)->only(['labour_cost', 'parts_cost', 'external_cost', 'downtime_hours', 'root_cause', 'action_taken', 'assigned_to', 'vendor_id'])->all());
            $wo->status = 'completed';
            $wo->started_at ??= $completedAt;
            $wo->completed_at = $completedAt;
            $wo->completed_by = Auth::id();
            $wo->save();

            if ($wo->pmSchedule) {
                $pm = $wo->pmSchedule;
                $pm->last_done_date = $completedAt->toDateString();
                if (isset($data['meter_reading']) && $data['meter_reading'] !== '' && $data['meter_reading'] !== null) {
                    $pm->meter_last_service_reading = $data['meter_reading'];
                    $pm->meter_current_reading = $data['meter_reading'];
                } elseif (in_array($pm->frequency_unit, ['running_hours', 'shots'], true)) {
                    $pm->meter_last_service_reading = $pm->meter_current_reading;
                }
                $this->refreshNextDue($pm);
                if (in_array($pm->frequency_unit, ['running_hours', 'shots'], true) && (float) $pm->meter_daily_average > 0) {
                    $pm->next_due_date = $completedAt->copy()->startOfDay()->addDays((int) floor($pm->frequency_interval / (float) $pm->meter_daily_average))->toDateString();
                }
                $pm->save();
            }
            $this->syncAssetStatus($wo->asset);

            return $wo;
        });
    }

    public function cancel(WorkOrder $wo, ?string $reason = null): WorkOrder
    {
        $this->assertOpen($wo);
        $wo->update(['status' => 'cancelled', 'action_taken' => trim(($wo->action_taken ? $wo->action_taken."\n" : '').'Cancelled: '.($reason ?: '—'))]);
        $this->syncAssetStatus($wo->asset);

        return $wo;
    }

    /** Active ↔ Under Maintenance driven by open breakdown work orders. */
    public function syncAssetStatus(?Asset $asset): void
    {
        if (! $asset || $asset->isDisposed()) {
            return;
        }
        $code = $asset->status?->code;
        if (! in_array($code, [AssetStatus::ACTIVE, AssetStatus::UNDER_MAINTENANCE, null], true)) {
            return;
        }
        $open = WorkOrder::where('asset_id', $asset->id)->where('type', 'breakdown')->whereIn('status', ['open', 'in_progress', 'on_hold'])->exists();
        $target = $open ? AssetStatus::UNDER_MAINTENANCE : AssetStatus::ACTIVE;
        if ($code !== $target) {
            $asset->asset_status_id = AssetStatus::idFor($target);
            $asset->save();
            $asset->load('status');
        }
    }

    protected function assertOpen(WorkOrder $wo): void
    {
        if (in_array($wo->status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages(['status' => 'This work order is already '.$wo->status.'.']);
        }
    }

    /** PMs needing attention (overdue or within their alert window / $days). */
    public static function dueCount(): int
    {
        return PmSchedule::where('is_active', true)->whereNotNull('next_due_date')
            ->whereHas('asset')
            ->whereRaw('next_due_date <= DATE_ADD(CURDATE(), INTERVAL GREATEST(alert_days_before, 0) DAY)')
            ->count();
    }
}
