<?php

namespace App\Services;

use App\Models\AssetStatus;
use App\Models\Company;
use App\Models\DepreciationMethod;
use App\Models\DepreciationRunParameter;
use App\Models\FinancialYear;
use App\Support\Tenant;
use Illuminate\Support\Carbon;

/**
 * Seeds the system masters every new company needs: depreciation methods,
 * asset statuses, a default run parameter set and financial years.
 */
class CompanyProvisioner
{
    public function provision(Company $company): void
    {
        Tenant::run($company->id, function () use ($company) {
            $methods = [
                ['SLM', 'Straight Line Method', 'Equal charge over useful life: (Cost − Residual) ÷ Useful life. Schedule II default for most companies.'],
                ['WDV', 'Written Down Value', 'Fixed rate on opening WDV. Rate = 1 − (Residual ÷ Cost)^(1/n) per Schedule II.'],
                ['UOP', 'Units of Production', 'Charge in proportion to units produced / shots / running hours (Ind AS 16 para 62).'],
            ];
            foreach ($methods as [$code, $name, $desc]) {
                DepreciationMethod::firstOrCreate(['code' => $code], ['name' => $name, 'description' => $desc, 'is_system' => true]);
            }

            $statuses = [
                [AssetStatus::ACTIVE, 'Active', 'emerald', true, 1],
                [AssetStatus::UNDER_MAINTENANCE, 'Under Maintenance', 'amber', true, 2],
                [AssetStatus::TRANSFERRED, 'Transferred', 'sky', true, 3],
                [AssetStatus::DISPOSED, 'Disposed', 'rose', false, 4],
                [AssetStatus::SCRAPPED, 'Scrapped', 'slate', false, 5],
            ];
            foreach ($statuses as [$code, $name, $color, $dep, $sort]) {
                AssetStatus::firstOrCreate(['code' => $code], ['name' => $name, 'color' => $color, 'is_depreciable' => $dep, 'is_system' => true, 'sort_order' => $sort]);
            }

            DepreciationRunParameter::firstOrCreate(['name' => 'Companies Act – Monthly (Schedule II)'], [
                'frequency' => 'monthly', 'book' => 'companies_act', 'proration_basis' => 'days',
                'depreciate_in_month_of_capitalization' => true, 'depreciate_in_month_of_disposal' => true,
                'low_value_threshold' => 5000, 'shift_basis' => 'single', 'rounding_precision' => 2,
                'auto_lock_on_finalize' => true, 'is_default' => true,
                'notes' => 'Pro-rata on days from put-to-use date; assets ≤ ₹5,000 fully depreciated in year of purchase.',
            ]);

            if (FinancialYear::count() === 0) {
                $this->createFinancialYears($company, now()->year - 2, 3);
            }
        });
    }

    /** Create $count financial years starting in calendar year $firstStartYear. */
    public function createFinancialYears(Company $company, int $firstStartYear, int $count): void
    {
        $m = $company->fy_start_month ?: 4;
        for ($i = 0; $i < $count; $i++) {
            $start = Carbon::create($firstStartYear + $i, $m, 1);
            $end = $start->copy()->addYear()->subDay();
            $name = $m === 1 ? 'FY '.$start->year : 'FY '.$start->year.'-'.$end->format('y');
            FinancialYear::firstOrCreate(['name' => $name], [
                'start_date' => $start->toDateString(), 'end_date' => $end->toDateString(),
                'is_current' => now()->between($start, $end->copy()->endOfDay()),
                'status' => 'open',
            ]);
        }
    }
}
