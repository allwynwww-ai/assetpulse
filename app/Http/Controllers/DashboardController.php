<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\FinancialYear;
use App\Support\Tenant;
use Illuminate\Http\Request;

/**
 * Phase 1 placeholder dashboard: headline register totals only.
 * Charts, depreciation forecasts and alerts arrive in Phase 2.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        if (Tenant::id() === null) {
            return $request->user()->isSuperAdmin()
                ? redirect()->route('admin.companies.index')
                : abort(403, 'Your account is not linked to a company.');
        }

        $totals = Asset::query()->toBase()
            ->selectRaw('COUNT(*) n, COALESCE(SUM(original_cost),0) cost, COALESCE(SUM(accumulated_depreciation),0) acc, COALESCE(SUM(current_book_value),0) nbv')
            ->first();

        $byCategory = AssetCategory::query()
            ->withCount('assets')
            ->withSum('assets as cost', 'original_cost')
            ->withSum('assets as nbv', 'current_book_value')
            ->orderByDesc('cost')
            ->get()
            ->filter(fn ($c) => $c->assets_count > 0);

        $recent = Asset::with(['category', 'location', 'status'])->latest('id')->limit(6)->get();
        $currentFy = FinancialYear::where('is_current', true)->first();

        return view('dashboard', compact('totals', 'byCategory', 'recent', 'currentFy'));
    }
}
