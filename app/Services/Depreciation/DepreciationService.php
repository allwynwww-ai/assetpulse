<?php

namespace App\Services\Depreciation;

use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\AuditLog;
use App\Models\DepreciationEntry;
use App\Models\DepreciationRun;
use App\Models\DepreciationRunParameter;
use App\Models\FinancialYear;
use App\Models\PeriodLock;
use App\Services\DocumentNumber;
use App\Support\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Orchestrates depreciation for the current company:
 *   preview → post → finalize (locks the period), or reverse a posted run.
 * All maths lives in DepreciationCalculator.
 */
class DepreciationService
{
    public function fyStartMonth(): int
    {
        return (int) (Tenant::company()?->fy_start_month ?: 4);
    }

    public function calculator(?DepreciationRunParameter $p = null): DepreciationCalculator
    {
        return DepreciationCalculator::fromParameter($p ?? DepreciationRunParameter::default(), $this->fyStartMonth());
    }

    /** [start, end, label] of the period containing $date for a frequency. */
    public function periodFor(string $frequency, $date): array
    {
        $d = CarbonImmutable::parse($date);
        $m = $this->fyStartMonth();
        $fyYear = $d->month >= $m ? $d->year : $d->year - 1;
        $fyStart = CarbonImmutable::create($fyYear, $m, 1);
        $fyLabel = 'FY '.$fyYear.'-'.substr((string) ($fyYear + 1), 2);

        return match ($frequency) {
            'yearly' => [$fyStart, $fyStart->addYear()->subDay(), $fyLabel],
            'quarterly' => (function () use ($d, $fyStart, $fyLabel) {
                $q = intdiv((int) $fyStart->diffInMonths($d->startOfMonth()), 3);
                $s = $fyStart->addMonths($q * 3);

                return [$s, $s->addMonths(3)->subDay(), 'Q'.($q + 1).' '.$fyLabel];
            })(),
            default => [$d->startOfMonth(), $d->endOfMonth()->startOfDay(), $d->format('M-Y')],
        };
    }

    public function lastPeriodicRun(): ?DepreciationRun
    {
        return DepreciationRun::where('run_type', 'periodic')->whereIn('status', ['posted', 'finalized'])->orderByDesc('period_end')->first();
    }

    /** Suggested next period for the run screen. */
    public function nextPeriod(?DepreciationRunParameter $p = null): array
    {
        $p ??= DepreciationRunParameter::default();
        $last = $this->lastPeriodicRun();
        if ($last) {
            return $this->periodFor($p->frequency ?? 'monthly', $last->period_end->copy()->addDay());
        }
        $first = Asset::min('capitalization_date');
        $fy = FinancialYear::orderBy('start_date')->first();
        $start = collect([$first, $fy?->start_date])->filter()->map(fn ($d) => CarbonImmutable::parse($d))->max() ?? CarbonImmutable::now();

        return $this->periodFor($p->frequency ?? 'monthly', $start);
    }

    /** Accumulated depreciation of an asset strictly before a date. */
    public function accumulatedBefore(Asset $asset, $date): float
    {
        $d = CarbonImmutable::parse($date)->toDateString();
        $opening = ($asset->opening_balance_date && $asset->opening_balance_date->toDateString() < $d) ? (float) $asset->opening_accumulated_depreciation : 0;

        return round($opening + (float) DepreciationEntry::where('asset_id', $asset->id)->where('period_end', '<', $d)->sum('depreciation_amount'), 2);
    }

    public function assetInput(Asset $asset, string $from): array
    {
        [$fyStart] = $this->periodFor('yearly', $from);
        $through = collect([$asset->last_depreciation_date, $asset->opening_balance_date])->filter()->max();

        return [
            'method' => $asset->depreciationMethod?->code ?? 'SLM',
            'cost' => (float) $asset->original_cost,
            'residual' => (float) $asset->residual_value,
            'life_months' => $asset->total_life_months,
            'cap_date' => $asset->capitalization_date->toDateString(),
            'wdv_rate' => $asset->wdv_rate !== null ? (float) $asset->wdv_rate : ($asset->category?->wdv_rate !== null ? (float) $asset->category->wdv_rate : null),
            'acc_before' => (float) $asset->accumulated_depreciation,
            'acc_at_fy_start' => $this->accumulatedBefore($asset, $fyStart),
            'depreciated_through' => $through?->toDateString(),
            'total_units' => $asset->total_units_capacity !== null ? (float) $asset->total_units_capacity : null,
            'units_consumed' => (float) $asset->units_consumed,
        ];
    }

    /** Assets eligible for a periodic run. */
    public function eligibleAssets(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $nonDep = AssetStatus::where('is_depreciable', false)->pluck('id');

        return Asset::with(['depreciationMethod:id,code', 'category:id,name,code,wdv_rate'])
            ->whereNotNull('capitalization_date')
            ->where('capitalization_date', '<=', $end->toDateString())
            ->where('original_cost', '>', 0)
            ->whereNotIn('asset_status_id', $nonDep)
            ->whereDoesntHave('disposals', fn ($q) => $q->where('status', 'completed'))
            ->where(fn ($q) => $q->whereNull('last_depreciation_date')->orWhere('last_depreciation_date', '<', $end->toDateString()))
            ->where(fn ($q) => $q->whereNull('opening_balance_date')->orWhere('opening_balance_date', '<', $end->toDateString()))
            ->orderBy('asset_code')
            ->get();
    }

    /**
     * Compute (without saving) the charge for every eligible asset.
     *
     * @return array{rows: Collection, skipped: Collection, total: float}
     */
    public function preview($from, $to, ?DepreciationRunParameter $p = null): array
    {
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($to)->startOfDay();
        $calc = $this->calculator($p);
        $rows = collect();
        $skipped = collect();
        foreach ($this->eligibleAssets($start, $end) as $asset) {
            $r = $calc->calculate($this->assetInput($asset, $start->toDateString()), $start->toDateString(), $end->toDateString());
            if ($r->skipReason !== null || $r->amount <= 0) {
                $skipped->push(['asset' => $asset, 'reason' => $r->skipReason ?? 'Nothing to charge']);

                continue;
            }
            $rows->push(['asset' => $asset, 'result' => $r]);
        }

        return ['rows' => $rows, 'skipped' => $skipped, 'total' => round($rows->sum(fn ($x) => $x['result']->amount), 2)];
    }

    public function validateNewRun(CarbonImmutable $start, CarbonImmutable $end): void
    {
        $err = fn ($m) => throw ValidationException::withMessages(['period' => $m]);
        if ($end->lt($start)) {
            $err('Period end must be on or after the period start.');
        }
        if ($end->gt(CarbonImmutable::now()->endOfMonth())) {
            $err('Depreciation cannot be posted for a future period (after '.CarbonImmutable::now()->endOfMonth()->format('d M Y').').');
        }
        if (! FinancialYear::forDate($start)) {
            $err('No financial year is set up for '.$start->format('d M Y').'. Add it under Financial Years first.');
        }
        if (! $start->isSameAs('Y', $end) && $this->periodFor('yearly', $start)[0]->ne($this->periodFor('yearly', $end)[0])) {
            $err('A run cannot span two financial years.');
        }
        $lock = PeriodLock::where('is_locked', true)->where('period_start', '<=', $end->toDateString())->where('period_end', '>=', $start->toDateString())->first();
        if ($lock) {
            $err("Period {$lock->period_label} is locked (finalised). Unlock it first if a correction is really required.");
        }
        $overlap = DepreciationRun::where('run_type', 'periodic')->whereIn('status', ['posted', 'finalized'])
            ->where('period_start', '<=', $end->toDateString())->where('period_end', '>=', $start->toDateString())->first();
        if ($overlap) {
            $err("Depreciation for this period was already posted in run {$overlap->run_number} ({$overlap->period_label}). Reverse it first to re-run.");
        }
        $last = $this->lastPeriodicRun();
        if ($last && $start->toDateString() !== $last->period_end->copy()->addDay()->toDateString()) {
            $err('Runs must be continuous. The next period must start on '.$last->period_end->copy()->addDay()->format('d M Y').' (last run: '.$last->period_label.').');
        }
    }

    public function post($from, $to, ?DepreciationRunParameter $p = null, ?string $remarks = null, string $type = 'periodic', ?Collection $only = null, ?string $disposalDate = null): DepreciationRun
    {
        $p ??= DepreciationRunParameter::default();
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($to)->startOfDay();
        if ($type === 'periodic') {
            $this->validateNewRun($start, $end);
        }

        return DB::transaction(function () use ($start, $end, $p, $remarks, $type, $only, $disposalDate) {
            $fy = FinancialYear::forDate($end) ?? FinancialYear::forDate($start);
            if (! $fy) {
                throw ValidationException::withMessages(['period' => 'No financial year covers '.$end->format('d M Y').'.']);
            }
            [, , $label] = $type === 'periodic' ? $this->periodFor($p->frequency, $start) : [null, null, 'Disposal '.$end->format('d-M-Y')];
            if ($type === 'periodic' && ! $start->isSameDay($this->periodFor($p->frequency, $start)[0]) || $type === 'periodic' && ! $end->isSameDay($this->periodFor($p->frequency, $start)[1])) {
                $label = $start->format('d M Y').' – '.$end->format('d M Y');
            }

            $run = DepreciationRun::create([
                'financial_year_id' => $fy->id,
                'depreciation_run_parameter_id' => $p?->id,
                'run_number' => DocumentNumber::next(DepreciationRun::class, 'run_number', $type === 'periodic' ? 'DR' : 'DD', $end, $this->fyStartMonth()),
                'run_type' => $type,
                'frequency' => $p->frequency ?? 'monthly',
                'book' => $p->book ?? 'companies_act',
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'period_label' => $label,
                'status' => 'posted',
                'run_by' => Auth::id(),
                'run_at' => now(),
                'remarks' => $remarks,
            ]);

            $calc = $this->calculator($p);
            $assets = $only ?? $this->eligibleAssets($start, $end);
            $now = now();
            $rows = [];
            $total = 0;
            foreach ($assets as $asset) {
                $asset->loadMissing('depreciationMethod:id,code');
                $r = $calc->calculate($this->assetInput($asset, $start->toDateString()), $start->toDateString(), $end->toDateString(), $disposalDate);
                if ($r->skipReason !== null || $r->amount <= 0) {
                    continue;
                }
                $snap = $this->snapshotAt($asset, $end);
                $details = $r->details + ['run_type' => $type];
                if ($split = $this->costCenterSplit($asset, $start, $end, $r->amount)) {
                    $details['cost_center_split'] = $split;
                }
                $rows[] = [
                    'company_id' => $asset->company_id,
                    'depreciation_run_id' => $run->id,
                    'asset_id' => $asset->id,
                    'financial_year_id' => $fy->id,
                    'cost_center_id' => $snap['cost_center_id'],
                    'location_id' => $snap['location_id'],
                    'department_id' => $snap['department_id'],
                    'asset_category_id' => $asset->asset_category_id,
                    'period_start' => $start->toDateString(),
                    'period_end' => $end->toDateString(),
                    'method_code' => $r->method,
                    'opening_gross_block' => $asset->original_cost,
                    'opening_accumulated_depreciation' => $r->openingAccumulated,
                    'opening_wdv' => $r->openingWdv,
                    'rate' => $r->rate,
                    'days' => $r->days,
                    'shift_factor' => $details['shift_factor'] ?? 1,
                    'depreciation_amount' => $r->amount,
                    'closing_accumulated_depreciation' => $r->closingAccumulated,
                    'closing_wdv' => $r->closingWdv,
                    'calculation_details' => json_encode($details),
                    'is_reversal' => false,
                    'created_by' => Auth::id(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $total += $r->amount;
            }
            foreach (array_chunk($rows, 200) as $chunk) {
                DepreciationEntry::insert($chunk);
            }
            $run->forceFill(['total_assets' => count($rows), 'total_depreciation' => round($total, 2)])->saveQuietly();
            $this->syncAssets(collect($rows)->pluck('asset_id'));
            $this->audit($run, 'posted', ['status' => 'posted', 'period' => $run->period_label, 'assets' => count($rows), 'total_depreciation' => round($total, 2)]);

            return $run;
        });
    }

    public function finalize(DepreciationRun $run): DepreciationRun
    {
        if ($run->status !== 'posted' || $run->run_type !== 'periodic') {
            throw ValidationException::withMessages(['run' => 'Only a posted periodic run can be finalised.']);
        }
        $earlier = DepreciationRun::where('run_type', 'periodic')->where('status', 'posted')->where('period_end', '<', $run->period_start->toDateString())->orderBy('period_end')->first();
        if ($earlier) {
            throw ValidationException::withMessages(['run' => "Finalise earlier run {$earlier->run_number} ({$earlier->period_label}) first – periods are closed in order."]);
        }

        return DB::transaction(function () use ($run) {
            $run->forceFill(['status' => 'finalized', 'finalized_by' => Auth::id(), 'finalized_at' => now()])->saveQuietly();
            // Disposal charges inside the period are closed with it
            DepreciationRun::where('run_type', 'disposal')->where('status', 'posted')->where('period_end', '<=', $run->period_end->toDateString())
                ->update(['status' => 'finalized', 'finalized_by' => Auth::id(), 'finalized_at' => now()]);
            if ($run->parameter?->auto_lock_on_finalize ?? true) {
                PeriodLock::create([
                    'financial_year_id' => $run->financial_year_id,
                    'depreciation_run_id' => $run->id,
                    'period_start' => $run->period_start,
                    'period_end' => $run->period_end,
                    'period_label' => $run->period_label,
                    'is_locked' => true,
                    'locked_by' => Auth::id(),
                    'locked_at' => now(),
                    'reason' => 'Locked on finalisation of '.$run->run_number,
                ]);
            }
            $this->audit($run, 'finalized', ['status' => 'finalized', 'period' => $run->period_label]);

            return $run;
        });
    }

    public function reverse(DepreciationRun $run, ?string $reason = null): DepreciationRun
    {
        if ($run->status !== 'posted' || $run->run_type !== 'periodic') {
            throw ValidationException::withMessages(['run' => $run->status === 'finalized' ? 'A finalised run cannot be reversed. A Company Admin must unlock the period first.' : 'Only a posted run can be reversed.']);
        }
        $later = DepreciationRun::whereIn('status', ['posted', 'finalized'])->where('period_end', '>', $run->period_end->toDateString())->where('run_type', 'periodic')->first();
        if ($later) {
            throw ValidationException::withMessages(['run' => "Reverse the later run {$later->run_number} ({$later->period_label}) first – runs are reversed newest first."]);
        }
        $disp = DepreciationRun::where('run_type', 'disposal')->whereIn('status', ['posted', 'finalized'])->where('period_end', '>=', $run->period_start->toDateString())->first();
        if ($disp) {
            throw ValidationException::withMessages(['run' => "An asset was disposed after this period started ({$disp->run_number}); the run cannot be reversed."]);
        }

        return DB::transaction(function () use ($run, $reason) {
            $assetIds = DepreciationEntry::where('depreciation_run_id', $run->id)->pluck('asset_id');
            DepreciationEntry::where('depreciation_run_id', $run->id)->update(['deleted_at' => now()]);
            $run->forceFill(['status' => 'reversed', 'reversed_by' => Auth::id(), 'reversed_at' => now(), 'remarks' => trim(($run->remarks ? $run->remarks."\n" : '').'Reversed: '.($reason ?: 'no reason given'))])->saveQuietly();
            $this->syncAssets($assetIds);
            $this->audit($run, 'reversed', ['status' => 'reversed', 'reason' => $reason, 'assets' => $assetIds->count(), 'total_depreciation' => (float) $run->total_depreciation]);

            return $run;
        });
    }

    public function unlock(PeriodLock $lock, string $reason): PeriodLock
    {
        $later = PeriodLock::where('is_locked', true)->where('period_start', '>', $lock->period_end->toDateString())->first();
        if ($later) {
            throw ValidationException::withMessages(['lock' => "Unlock the later period {$later->period_label} first – periods are re-opened newest first."]);
        }

        return DB::transaction(function () use ($lock, $reason) {
            $lock->update(['is_locked' => false, 'unlocked_by' => Auth::id(), 'unlocked_at' => now(), 'reason' => $reason]);
            if ($lock->run && $lock->run->status === 'finalized') {
                $lock->run->forceFill(['status' => 'posted', 'finalized_by' => null, 'finalized_at' => null])->saveQuietly();
                $this->audit($lock->run, 'unlocked', ['status' => 'posted', 'reason' => $reason]);
            }

            return $lock;
        });
    }

    /** Recalculate accumulated depreciation / NBV / last depreciation date from entries. */
    public function syncAssets(Collection $assetIds): void
    {
        foreach ($assetIds->unique() as $id) {
            $asset = Asset::withTrashed()->find($id);
            if (! $asset) {
                continue;
            }
            $sum = (float) DepreciationEntry::where('asset_id', $id)->sum('depreciation_amount');
            $last = DepreciationEntry::where('asset_id', $id)->max('period_end');
            $acc = round((float) $asset->opening_accumulated_depreciation + $sum, 2);
            Asset::withTrashed()->whereKey($id)->toBase()->update([
                'accumulated_depreciation' => $acc,
                'current_book_value' => round((float) $asset->original_cost - $acc, 2),
                'last_depreciation_date' => $last,
            ]);
        }
    }

    /** Location / department / cost center of the asset as at a date (from transfer history). */
    public function snapshotAt(Asset $asset, CarbonImmutable $date): array
    {
        $after = $asset->transfers()->reorder()->where('status', 'completed')->where('transfer_date', '>', $date->toDateString())->orderBy('transfer_date')->orderBy('id')->first();
        if (! $after) {
            return ['cost_center_id' => $asset->cost_center_id, 'location_id' => $asset->location_id, 'department_id' => $asset->department_id];
        }

        return [
            'cost_center_id' => $after->from_cost_center_id ?? $asset->cost_center_id,
            'location_id' => $after->from_location_id ?? $asset->location_id,
            'department_id' => $after->from_department_id ?? $asset->department_id,
        ];
    }

    /** Day-based split of a period's charge when a cost-centre transfer flagged "affects depreciation" happens mid-period. */
    public function costCenterSplit(Asset $asset, CarbonImmutable $start, CarbonImmutable $end, float $amount): ?array
    {
        $t = $asset->transfers()->reorder()->where('status', 'completed')->where('affects_depreciation', true)
            ->whereColumn('from_cost_center_id', '!=', 'to_cost_center_id')
            ->where('transfer_date', '>', $start->toDateString())->where('transfer_date', '<=', $end->toDateString())
            ->orderBy('transfer_date')->first();
        if (! $t) {
            return null;
        }
        $days = (int) $start->diffInDays($end) + 1;
        $before = (int) $start->diffInDays(CarbonImmutable::parse($t->transfer_date)) ;
        $a1 = round($amount * $before / $days, 2);

        return [
            ['cost_center_id' => $t->from_cost_center_id, 'days' => $before, 'amount' => $a1],
            ['cost_center_id' => $t->to_cost_center_id, 'days' => $days - $before, 'amount' => round($amount - $a1, 2)],
        ];
    }

    protected function audit(DepreciationRun $run, string $event, array $new): void
    {
        $user = Auth::user();
        AuditLog::create([
            'company_id' => $run->company_id, 'user_id' => $user?->id, 'user_name' => $user?->name ?? 'system',
            'event' => $event, 'auditable_type' => DepreciationRun::class, 'auditable_id' => $run->id,
            'auditable_label' => $run->run_number.' · '.$run->period_label, 'new_values' => $new,
            'url' => app()->runningInConsole() ? 'console' : mb_substr(request()->fullUrl(), 0, 2000),
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'user_agent' => app()->runningInConsole() ? null : mb_substr((string) request()->userAgent(), 0, 500),
        ]);
    }
}
