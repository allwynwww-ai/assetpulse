<?php

namespace Tests\Unit;

use App\Services\Depreciation\DepreciationCalculator;
use PHPUnit\Framework\TestCase;

class DepreciationCalculatorTest extends TestCase
{
    private function calc(array $opts = []): DepreciationCalculator
    {
        return new DepreciationCalculator(...array_merge(['prorationBasis' => 'days', 'lowValueThreshold' => 5000], $opts));
    }

    private function asset(array $o = []): array
    {
        return array_merge([
            'method' => 'SLM', 'cost' => 1000000, 'residual' => 50000, 'life_months' => 120,
            'cap_date' => '2024-04-01', 'acc_before' => 0, 'depreciated_through' => null,
        ], $o);
    }

    public function test_slm_full_month(): void
    {
        // (10,00,000 − 50,000) / 10 = 95,000 p.a.; April = 30/365
        $r = $this->calc()->calculate($this->asset(), '2024-04-01', '2024-04-30');
        $this->assertSame(round(95000 * 30 / 365, 2), $r->amount); // 7808.22
        $this->assertSame(30, $r->days);
        $this->assertEquals(10.0, $r->rate);
    }

    public function test_slm_full_year_equals_annual_charge(): void
    {
        $r = $this->calc()->calculate($this->asset(), '2024-04-01', '2025-03-31');
        $this->assertSame(95000.0, $r->amount);
    }

    public function test_slm_mid_month_addition_is_prorated_by_days(): void
    {
        $r = $this->calc()->calculate($this->asset(['cap_date' => '2024-04-16']), '2024-04-01', '2024-04-30');
        $this->assertSame(15, $r->days);
        $this->assertSame(round(95000 * 15 / 365, 2), $r->amount);
    }

    public function test_leap_financial_year_uses_366_days(): void
    {
        $r = $this->calc()->calculate($this->asset(['cap_date' => '2023-04-01']), '2023-04-01', '2024-03-31');
        $this->assertSame(95000.0, $r->amount);
        $r = $this->calc()->calculate($this->asset(['cap_date' => '2023-04-01']), '2024-02-01', '2024-02-29');
        $this->assertSame(round(95000 * 29 / 366, 2), $r->amount);
    }

    public function test_disposal_mid_month_stops_on_disposal_date(): void
    {
        $r = $this->calc()->calculate($this->asset(), '2024-05-01', '2024-05-31', '2024-05-10');
        $this->assertSame(10, $r->days);
        $this->assertSame(round(95000 * 10 / 365, 2), $r->amount);
    }

    public function test_never_below_residual_and_stops_at_end_of_life(): void
    {
        // Life ends 2034-03-31; accumulated already 949,000 → only 1,000 left
        $r = $this->calc()->calculate($this->asset(['acc_before' => 949000]), '2034-03-01', '2034-03-31');
        $this->assertSame(1000.0, $r->amount);
        $this->assertSame(50000.0, $r->closingWdv);
        // After end of life nothing more
        $r = $this->calc()->calculate($this->asset(['acc_before' => 950000]), '2034-04-01', '2034-04-30');
        $this->assertSame(0.0, $r->amount);
        $this->assertNotNull($r->skipReason);
    }

    public function test_period_containing_end_of_life_trues_up_to_residual(): void
    {
        $r = $this->calc()->calculate($this->asset(['acc_before' => 940000]), '2034-03-01', '2034-03-31');
        $this->assertSame(10000.0, $r->amount);
    }

    public function test_wdv_rate_formula(): void
    {
        // 1 − 0.05^(1/8) = 31.2344 %
        $this->assertEqualsWithDelta(31.2344, DepreciationCalculator::wdvRatePercent(8, 5), 0.0001);
        $this->assertEqualsWithDelta(18.1035, DepreciationCalculator::wdvRatePercent(15, 5), 0.0001);
    }

    public function test_wdv_uses_opening_wdv_of_financial_year(): void
    {
        $a = $this->asset(['method' => 'WDV', 'life_months' => 96, 'wdv_rate' => 31.2342, 'cap_date' => '2023-04-01']);
        // FY 2023-24 (366 days) full year on cost
        $r = $this->calc()->calculate($a, '2023-04-01', '2024-03-31');
        $this->assertSame(round(1000000 * 0.312342, 2), $r->amount);
        // FY 2024-25 month: base = cost − acc at FY start
        $acc = 312342.0;
        $r = $this->calc()->calculate($a + ['acc_before' => $acc, 'acc_at_fy_start' => $acc], '2024-04-01', '2024-04-30');
        $r2 = $this->calc()->calculate(array_merge($a, ['acc_before' => $acc + 10000, 'acc_at_fy_start' => $acc]), '2024-05-01', '2024-05-31');
        $this->assertSame(round((1000000 - $acc) * 0.312342 * 30 / 365, 2), $this->calc()->calculate(array_merge($a, ['acc_before' => $acc, 'acc_at_fy_start' => $acc]), '2024-04-01', '2024-04-30')->amount);
        $this->assertSame(round((1000000 - $acc) * 0.312342 * 31 / 365, 2), $r2->amount, 'Base stays at FY opening WDV within the year');
    }

    public function test_low_value_asset_written_off_in_first_period(): void
    {
        $r = $this->calc()->calculate($this->asset(['cost' => 4800, 'residual' => 0, 'life_months' => 36, 'cap_date' => '2024-04-20']), '2024-04-01', '2024-04-30');
        $this->assertSame(4800.0, $r->amount);
        $this->assertSame('low_value', $r->details['rule']);
    }

    public function test_units_of_production(): void
    {
        $a = $this->asset(['method' => 'UOP', 'cost' => 3000000, 'residual' => 150000, 'total_units' => 1500000, 'units_consumed' => 30000]);
        $r = $this->calc()->calculate($a, '2024-04-01', '2024-04-30');
        $this->assertSame(57000.0, $r->amount); // 28.5 lakh × 30k / 15 lakh
        $r = $this->calc()->calculate(array_merge($a, ['units_consumed' => 75000, 'acc_before' => 57000]), '2024-05-01', '2024-05-31');
        $this->assertSame(85500.0, $r->amount);
    }

    public function test_month_basis_honours_month_of_capitalisation_flag(): void
    {
        $c = $this->calc(['prorationBasis' => 'months', 'depInMonthOfCapitalization' => false]);
        $this->assertSame(0.0, $c->calculate($this->asset(['cap_date' => '2024-04-16']), '2024-04-01', '2024-04-30')->amount);
        $c = $this->calc(['prorationBasis' => 'months']);
        $this->assertSame(round(95000 / 12, 2), $c->calculate($this->asset(['cap_date' => '2024-04-16']), '2024-04-01', '2024-04-30')->amount);
    }

    public function test_shift_factor_applies(): void
    {
        $r = $this->calc(['shiftFactor' => 1.5])->calculate($this->asset(), '2024-04-01', '2025-03-31');
        $this->assertSame(142500.0, $r->amount);
    }

    public function test_catch_up_window_crosses_financial_years(): void
    {
        $r = $this->calc()->calculate($this->asset(['cap_date' => '2025-03-01']), '2025-03-01', '2025-04-30');
        $this->assertSame(round(95000 * 31 / 365 + 95000 * 30 / 365, 2), $r->amount);
        $this->assertCount(2, $r->details['segments']);
    }

    public function test_not_yet_put_to_use(): void
    {
        $r = $this->calc()->calculate($this->asset(['cap_date' => '2024-06-10']), '2024-04-01', '2024-04-30');
        $this->assertSame(0.0, $r->amount);
    }
}
