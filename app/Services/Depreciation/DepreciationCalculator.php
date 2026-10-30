<?php

namespace App\Services\Depreciation;

use Carbon\CarbonImmutable;

/**
 * Pure depreciation maths (no database access) so it can be unit-tested and
 * reused by period runs, disposals and opening-balance computation.
 *
 * Methods (Companies Act 2013, Schedule II):
 *  - SLM : annual = (cost − residual) / life_years
 *          charge = annual × (days in window / days in that financial year) × shift factor
 *  - WDV : rate   = 1 − (residual / cost)^(1 / life_years)
 *          charge = WDV at start of the financial year × rate × (days / days in FY) × shift factor
 *          (an asset added during the year uses its cost as the opening WDV)
 *  - UOP : charge = (cost − residual) × units consumed to date / lifetime units − depreciation already charged
 *
 * Rules applied to every method:
 *  - Day-based (or full-month) proration from the put-to-use date and up to the disposal date.
 *  - Never depreciate below residual value; stop at the end of useful life
 *    (the period containing the end-of-life date trues up to residual value).
 *  - Low-value rule: cost ≤ threshold → fully depreciated (to residual) in the first period.
 */
final class DepreciationCalculator
{
    public function __construct(
        private string $prorationBasis = 'days',      // days | months
        private bool $depInMonthOfCapitalization = true,
        private bool $depInMonthOfDisposal = true,
        private float $lowValueThreshold = 5000,
        private float $shiftFactor = 1.0,
        private int $precision = 2,
        private int $fyStartMonth = 4,
    ) {}

    public static function fromParameter(?object $p, int $fyStartMonth = 4): self
    {
        return new self(
            $p->proration_basis ?? 'days',
            (bool) ($p->depreciate_in_month_of_capitalization ?? true),
            (bool) ($p->depreciate_in_month_of_disposal ?? true),
            (float) ($p->low_value_threshold ?? 5000),
            match ($p->shift_basis ?? 'single') { 'double' => 1.5, 'triple' => 2.0, default => 1.0 },
            (int) ($p->rounding_precision ?? 2),
            $fyStartMonth,
        );
    }

    /** Schedule II WDV rate (as a percentage) from life and residual %. */
    public static function wdvRatePercent(float $lifeYears, float $residualPercent): ?float
    {
        if ($lifeYears <= 0) {
            return null;
        }
        $r = max($residualPercent, 0.01) / 100;

        return round((1 - pow($r, 1 / $lifeYears)) * 100, 4);
    }

    public function fyBounds(CarbonImmutable $date): array
    {
        $year = $date->month >= $this->fyStartMonth ? $date->year : $date->year - 1;
        $start = CarbonImmutable::create($year, $this->fyStartMonth, 1)->startOfDay();

        return [$start, $start->addYear()->subDay()];
    }

    public static function endOfLife(CarbonImmutable $capDate, int $lifeMonths): CarbonImmutable
    {
        return $capDate->addMonthsNoOverflow($lifeMonths)->subDay();
    }

    /**
     * @param array{
     *   method:string, cost:float, residual:float, life_months:int, cap_date:string,
     *   wdv_rate?:float|null, acc_before:float, acc_at_fy_start?:float|null,
     *   depreciated_through?:string|null, total_units?:float|null, units_consumed?:float|null
     * } $a
     */
    public function calculate(array $a, string $from, string $to, ?string $disposalDate = null): CalculationResult
    {
        $method = strtoupper($a['method'] ?? 'SLM');
        $cost = (float) $a['cost'];
        $residual = min((float) ($a['residual'] ?? 0), $cost);
        $lifeMonths = (int) $a['life_months'];
        $cap = CarbonImmutable::parse($a['cap_date'])->startOfDay();
        $accBefore = (float) $a['acc_before'];
        $depreciable = round($cost - $residual, 2);
        $remaining = round($depreciable - $accBefore, 2);
        $periodFrom = CarbonImmutable::parse($from)->startOfDay();
        $periodTo = CarbonImmutable::parse($to)->startOfDay();
        $disposal = $disposalDate ? CarbonImmutable::parse($disposalDate)->startOfDay() : null;

        $res = new CalculationResult(method: $method);
        $res->details = ['cost' => $cost, 'residual' => $residual, 'depreciable' => $depreciable, 'acc_before' => $accBefore, 'life_months' => $lifeMonths];

        if ($remaining <= 0.004) {
            return $res->skip('Fully depreciated to residual value');
        }

        // Effective window
        $start = $periodFrom->max($cap);
        if (! empty($a['depreciated_through'])) {
            $start = $start->max(CarbonImmutable::parse($a['depreciated_through'])->addDay()->startOfDay());
        }
        $eol = $lifeMonths > 0 ? self::endOfLife($cap, $lifeMonths) : null;
        $end = $periodTo;
        if ($disposal) {
            $end = $end->min($disposal);
        }
        $reachesEol = $eol && $eol->lte($end);
        if ($eol) {
            $end = $end->min($eol);
        }

        // Low-value rule (cost at/below threshold → full write-off when first depreciated)
        if ($this->lowValueThreshold > 0 && $cost <= $this->lowValueThreshold && $cap->lte($periodTo)) {
            $res->amount = $remaining;
            $res->days = $start->lte($end) ? (int) $start->diffInDays($end) + 1 : 0;
            $res->rate = 100;
            $res->details += ['rule' => 'low_value', 'threshold' => $this->lowValueThreshold, 'formula' => "Cost ≤ low-value threshold {$this->lowValueThreshold}: written off fully (to residual)"];

            return $res->finish($accBefore, $cost, $this->precision);
        }

        if ($method === 'UOP') {
            $total = (float) ($a['total_units'] ?? 0);
            $used = (float) ($a['units_consumed'] ?? 0);
            if ($total <= 0) {
                return $res->skip('Units of production: lifetime units not set');
            }
            if ($cap->gt($periodTo)) {
                return $res->skip('Not yet put to use');
            }
            $target = round($depreciable * min(1, $used / $total), $this->precision);
            $res->amount = max(0, min($remaining, round($target - $accBefore, $this->precision)));
            $res->days = $start->lte($end) ? (int) $start->diffInDays($end) + 1 : 0;
            $res->details += ['total_units' => $total, 'units_to_date' => $used, 'target_accumulated' => $target,
                'formula' => sprintf('(%s × %s / %s) − %s', $depreciable, $used, $total, $accBefore)];

            return $res->finish($accBefore, $cost, $this->precision);
        }

        if ($start->gt($end)) {
            if ($eol && $eol->lt($start) && $periodFrom->lte($periodTo)) {
                // Life already over but book still above residual (e.g. migrated balance): true-up.
                $res->amount = $remaining;
                $res->details += ['rule' => 'end_of_life_true_up', 'end_of_life' => $eol->toDateString(), 'formula' => 'Useful life ended: written down to residual value'];

                return $res->finish($accBefore, $cost, $this->precision);
            }

            return $res->skip($cap->gt($periodTo) ? 'Not yet put to use' : 'No days in period');
        }

        $lifeYears = $lifeMonths / 12;
        $rate = $method === 'WDV'
            ? (float) ($a['wdv_rate'] ?? self::wdvRatePercent($lifeYears, $cost > 0 ? $residual / $cost * 100 : 5))
            : ($lifeYears > 0 ? round(100 / $lifeYears, 4) : 0);
        $res->rate = $rate;

        // Split the window by financial year (catch-up windows can cross 31-Mar)
        $total = 0.0;
        $segments = [];
        $accAtFyStart = $a['acc_at_fy_start'] ?? $accBefore;
        $cursor = $start;
        while ($cursor->lte($end)) {
            [$fyStart, $fyEnd] = $this->fyBounds($cursor);
            $segEnd = $end->min($fyEnd);
            $fyDays = (int) $fyStart->diffInDays($fyEnd) + 1;
            $days = (int) $cursor->diffInDays($segEnd) + 1;
            $fraction = $this->fraction($cursor, $segEnd, $fyDays, $cap, $disposal);

            if ($method === 'WDV') {
                $base = $cap->gte($fyStart) ? $cost : max(0, $cost - $accAtFyStart);
                $annual = $base * $rate / 100;
            } else {
                $base = $depreciable;
                $annual = $lifeYears > 0 ? $depreciable / $lifeYears : 0;
            }
            $charge = $annual * $fraction * $this->shiftFactor;
            $segments[] = ['from' => $cursor->toDateString(), 'to' => $segEnd->toDateString(), 'days' => $days, 'fy_days' => $fyDays,
                'fraction' => round($fraction, 6), 'base' => round($base, 2), 'annual' => round($annual, 2), 'charge' => round($charge, 2)];
            $total += $charge;
            $res->days += $days;
            // Next FY segment: WDV base moves to the new opening WDV
            $accAtFyStart = $accBefore + $total;
            $cursor = $segEnd->addDay();
        }

        $amount = round($total, $this->precision);
        if ($reachesEol) {
            $amount = $remaining; // end of useful life: true-up to residual
            $res->details['rule'] = 'end_of_life';
            $res->details['end_of_life'] = $eol->toDateString();
        }
        $res->amount = max(0, min($remaining, $amount));
        $res->details += [
            'segments' => $segments,
            'shift_factor' => $this->shiftFactor,
            'proration' => $this->prorationBasis,
            'formula' => $method === 'WDV'
                ? 'Opening WDV of FY × rate × days ÷ days-in-FY'
                : '(Cost − Residual) ÷ life years × days ÷ days-in-FY',
        ];
        if ($res->amount < $amount) {
            $res->details['capped_at_residual'] = true;
        }

        return $res->finish($accBefore, $cost, $this->precision);
    }

    private function fraction(CarbonImmutable $from, CarbonImmutable $to, int $fyDays, CarbonImmutable $cap, ?CarbonImmutable $disposal): float
    {
        if ($this->prorationBasis !== 'months') {
            return ((int) $from->diffInDays($to) + 1) / $fyDays;
        }
        $months = 0;
        $m = $from->startOfMonth();
        while ($m->lte($to)) {
            $isCapMonth = $m->isSameMonth($cap);
            $isDispMonth = $disposal && $m->isSameMonth($disposal);
            if (($isCapMonth && ! $this->depInMonthOfCapitalization) || ($isDispMonth && ! $this->depInMonthOfDisposal)) {
                $m = $m->addMonth();

                continue;
            }
            $months++;
            $m = $m->addMonth();
        }

        return $months / 12;
    }
}
