<?php

namespace App\Services\Reports;

use App\Models\AssetDisposal;
use App\Models\FinancialYear;
use App\Models\WorkOrder;
use App\Services\Depreciation\DepreciationService;
use App\Support\Tenant;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Builds every report as a neutral structure (columns + rows + totals) that
 * is rendered identically to HTML, Excel and PDF. All queries are explicitly
 * filtered by the current tenant because they use the query builder.
 */
class ReportBuilder
{
    public const REPORTS = [
        'asset-register' => ['Fixed Asset Register', 'Every asset with cost, residual value, accumulated depreciation and net book value.', 'cube', 'Registers'],
        'assets-by-location' => ['Assets by Location', 'Gross block, accumulated depreciation and NBV by plant / location.', 'map', 'Registers'],
        'assets-by-category' => ['Assets by Category', 'Gross block, accumulated depreciation and NBV by asset class.', 'tag', 'Registers'],
        'depreciation-detail' => ['Depreciation Detail', 'Asset-wise depreciation entries per period with opening and closing balances.', 'calculator', 'Depreciation'],
        'depreciation-summary' => ['Depreciation Summary – Schedule II / Ind AS 16', 'Gross block and depreciation movement by asset class for a financial year (PPE note format).', 'layers', 'Depreciation'],
        'asset-movement' => ['Asset Movement', 'Additions, transfers between locations / cost centers, and disposals over a period.', 'arrows', 'Operations'],
        'maintenance-history' => ['Maintenance History', 'Work orders with type, downtime and labour / parts / external cost.', 'wrench', 'Operations'],
        'disposal-register' => ['Disposal Register', 'Assets sold, scrapped or written off with NBV and profit / loss on disposal.', 'trash', 'Operations'],
        'audit-trail' => ['Audit Trail', 'Who changed what and when – every create, update, post, finalise and reversal.', 'shield', 'Compliance'],
    ];

    public function __construct(private DepreciationService $dep) {}

    public static function exists(string $slug): bool
    {
        return isset(self::REPORTS[$slug]);
    }

    public function build(string $slug, Request $r): array
    {
        [$title, $subtitle] = self::REPORTS[$slug];
        $method = Str::camel($slug);
        $out = $this->$method($r) + ['notes' => [], 'totals' => null, 'applied' => []];
        $out['title'] = $out['title'] ?? $title;
        $out['subtitle'] = $subtitle;
        $out['slug'] = $slug;
        if ($out['totals'] === null) {
            $out['totals'] = $this->sumColumns($out['columns'], $out['rows']);
        }

        return $out;
    }

    // ------------------------------------------------------------------ helpers

    private function t(string $table, ?string $alias = null): Builder
    {
        $a = $alias ?? $table;

        return DB::table($alias ? "$table as $alias" : $table)->where("$a.company_id", Tenant::id());
    }

    private function sumColumns(array $cols, $rows): ?array
    {
        $tot = [];
        $any = false;
        foreach ($cols as $c) {
            if (! empty($c['sum'])) {
                $tot[$c['key']] = round(collect($rows)->sum(fn ($row) => (float) ($row[$c['key']] ?? 0)), 2);
                $any = true;
            }
        }

        return $any ? $tot : null;
    }

    private function lookup(string $table, string $label = 'name', ?string $order = null): array
    {
        return $this->t($table)->whereNull('deleted_at')->orderBy($order ?? $label)->pluck($label, 'id')->all();
    }

    private function fyOptions(): array
    {
        return FinancialYear::orderByDesc('start_date')->pluck('name', 'id')->all();
    }

    private function fy(Request $r): ?FinancialYear
    {
        return ($r->filled('fy') ? FinancialYear::find($r->fy) : null)
            ?? FinancialYear::where('start_date', '<=', now())->where('end_date', '>=', now())->first()
            ?? FinancialYear::orderByDesc('start_date')->first();
    }

    private function applied(array $filters, Request $r): array
    {
        $out = [];
        foreach ($filters as $f) {
            $v = $r->input($f['name']);
            if ($v === null || $v === '') {
                continue;
            }
            $out[$f['label']] = isset($f['options']) ? ($f['options'][$v] ?? $v) : ($f['type'] === 'date' ? fmt_date($v) : $v);
        }

        return $out;
    }

    private static function col(string $key, string $label, string $type = 'text', bool $sum = false): array
    {
        return compact('key', 'label', 'type', 'sum');
    }

    // ---------------------------------------------------------------- reports

    private function assetRegister(Request $r): array
    {
        $filters = [
            ['name' => 'q', 'label' => 'Search', 'type' => 'text'],
            ['name' => 'category', 'label' => 'Category', 'type' => 'select', 'options' => $this->lookup('asset_categories')],
            ['name' => 'location', 'label' => 'Location', 'type' => 'select', 'options' => $this->lookup('locations')],
            ['name' => 'department', 'label' => 'Department', 'type' => 'select', 'options' => $this->lookup('departments')],
            ['name' => 'cost_center', 'label' => 'Cost center', 'type' => 'select', 'options' => $this->lookup('cost_centers', 'code')],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => DB::table('asset_statuses')->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', Tenant::id()))->whereNull('deleted_at')->orderBy('sort_order')->pluck('name', 'id')->all()],
            ['name' => 'cap_from', 'label' => 'Put to use from', 'type' => 'date'],
            ['name' => 'cap_to', 'label' => 'Put to use to', 'type' => 'date'],
        ];
        $rows = $this->t('assets', 'a')->whereNull('a.deleted_at')
            ->leftJoin('asset_categories as c', 'c.id', '=', 'a.asset_category_id')
            ->leftJoin('locations as l', 'l.id', '=', 'a.location_id')
            ->leftJoin('departments as d', 'd.id', '=', 'a.department_id')
            ->leftJoin('cost_centers as cc', 'cc.id', '=', 'a.cost_center_id')
            ->leftJoin('depreciation_methods as m', 'm.id', '=', 'a.depreciation_method_id')
            ->leftJoin('asset_statuses as s', 's.id', '=', 'a.asset_status_id')
            ->when($r->filled('ids'), fn ($q) => $q->whereIn('a.id', collect(is_array($r->ids) ? $r->ids : explode(',', (string) $r->ids))->map(fn ($v) => (int) $v)->filter()->take(500)->all() ?: [0]))
            ->when($r->filled('q'), fn ($q) => $q->where(fn ($w) => $w->whereLike('a.asset_code', "%{$r->q}%")->orWhereLike('a.name', "%{$r->q}%")->orWhereLike('a.serial_number', "%{$r->q}%")))
            ->when($r->filled('category'), fn ($q) => $q->where('a.asset_category_id', $r->category))
            ->when($r->filled('location'), fn ($q) => $q->where('a.location_id', $r->location))
            ->when($r->filled('department'), fn ($q) => $q->where('a.department_id', $r->department))
            ->when($r->filled('cost_center'), fn ($q) => $q->where('a.cost_center_id', $r->cost_center))
            ->when($r->filled('status'), fn ($q) => $q->where('a.asset_status_id', $r->status))
            ->when($r->filled('cap_from'), fn ($q) => $q->where('a.capitalization_date', '>=', $r->cap_from))
            ->when($r->filled('cap_to'), fn ($q) => $q->where('a.capitalization_date', '<=', $r->cap_to))
            ->orderBy('a.asset_code')
            ->get(['a.asset_code', 'a.name', 'c.name as category', 'l.name as location', 'd.name as department', 'cc.code as cost_center', 'a.capitalization_date',
                'm.code as method', 'a.useful_life_years', 'a.useful_life_months', 'a.original_cost', 'a.residual_value', 'a.accumulated_depreciation', 'a.current_book_value', 's.name as status', 'a.serial_number'])
            ->map(fn ($a) => [
                'asset_code' => $a->asset_code, 'name' => $a->name, 'category' => $a->category, 'location' => $a->location, 'department' => $a->department,
                'cost_center' => $a->cost_center, 'cap_date' => $a->capitalization_date, 'method' => $a->method,
                'life' => trim(($a->useful_life_years ? $a->useful_life_years.'y ' : '').($a->useful_life_months ? $a->useful_life_months.'m' : '')),
                'cost' => (float) $a->original_cost, 'residual' => (float) $a->residual_value, 'acc' => (float) $a->accumulated_depreciation,
                'nbv' => (float) $a->current_book_value, 'status' => $a->status,
            ])->all();

        return [
            'filters' => $filters, 'applied' => $this->applied($filters, $r), 'rows' => $rows,
            'columns' => [self::col('asset_code', 'Asset code', 'code'), self::col('name', 'Description'), self::col('category', 'Category'), self::col('location', 'Location'),
                self::col('department', 'Department'), self::col('cost_center', 'Cost ctr'), self::col('cap_date', 'Put to use', 'date'), self::col('method', 'Method'), self::col('life', 'Life'),
                self::col('cost', 'Cost', 'money', true), self::col('residual', 'Residual', 'money', true), self::col('acc', 'Acc. depreciation', 'money', true), self::col('nbv', 'Net book value', 'money', true), self::col('status', 'Status')],
        ];
    }

    private function assetsByLocation(Request $r): array
    {
        return $this->grouped($r, 'location');
    }

    private function assetsByCategory(Request $r): array
    {
        return $this->grouped($r, 'category');
    }

    private function grouped(Request $r, string $by): array
    {
        $fy = $this->fy($r);
        $filters = [
            ['name' => 'fy', 'label' => 'Depreciation for FY', 'type' => 'select', 'options' => $this->fyOptions(), 'default' => $fy?->id],
            ['name' => 'include_disposed', 'label' => 'Include disposed', 'type' => 'select', 'options' => ['1' => 'Yes']],
        ];
        [$table, $fk, $label] = $by === 'location' ? ['locations', 'location_id', 'name'] : ['asset_categories', 'asset_category_id', 'name'];
        $disposed = DB::table('asset_statuses')->whereIn('code', ['DISPOSED', 'SCRAPPED'])->pluck('id');
        $base = $this->t('assets', 'a')->whereNull('a.deleted_at')->when(! $r->boolean('include_disposed'), fn ($q) => $q->whereNotIn('a.asset_status_id', $disposed));
        $agg = (clone $base)->leftJoin("$table as g", 'g.id', '=', "a.$fk")
            ->groupBy('g.id', "g.$label")->orderBy("g.$label")
            ->selectRaw("g.id gid, g.$label grp, COUNT(*) n, SUM(a.original_cost) cost, SUM(a.accumulated_depreciation) acc, SUM(a.current_book_value) nbv")->get();
        $fyDep = $fy ? $this->t('depreciation_entries', 'e')->whereNull('e.deleted_at')->join('assets as a', 'a.id', '=', 'e.asset_id')
            ->where('e.financial_year_id', $fy->id)
            ->when(! $r->boolean('include_disposed'), fn ($q) => $q->whereNotIn('a.asset_status_id', $disposed))
            ->groupBy($by === 'location' ? 'a.location_id' : 'a.asset_category_id')->selectRaw(($by === 'location' ? 'a.location_id' : 'a.asset_category_id').' gid, SUM(e.depreciation_amount) amt')->pluck('amt', 'gid') : collect();
        $grand = max(1, (float) $agg->sum('cost'));
        $rows = $agg->map(fn ($g) => [
            'group' => $g->grp ?? '(none)', 'count' => (int) $g->n, 'cost' => (float) $g->cost, 'acc' => (float) $g->acc, 'nbv' => (float) $g->nbv,
            'fy_dep' => (float) ($fyDep[$g->gid] ?? 0), 'share' => round((float) $g->cost / $grand * 100, 1),
            'dep_pct' => (float) $g->cost > 0 ? round((float) $g->acc / (float) $g->cost * 100, 1) : 0,
        ])->all();

        $r->merge(['fy' => $r->input('fy', $fy?->id)]);

        return [
            'filters' => $filters, 'applied' => $this->applied($filters, $r), 'rows' => $rows, 'chart' => true,
            'columns' => [self::col('group', $by === 'location' ? 'Location' : 'Category'), self::col('count', 'Assets', 'int', true), self::col('cost', 'Gross block', 'money', true),
                self::col('acc', 'Acc. depreciation', 'money', true), self::col('nbv', 'Net book value', 'money', true), self::col('fy_dep', 'Depreciation '.($fy?->name ?? ''), 'money', true),
                self::col('share', '% of gross', 'percent'), self::col('dep_pct', '% depreciated', 'percent')],
        ];
    }

    private function depreciationDetail(Request $r): array
    {
        $fy = $this->fy($r);
        $runs = $this->t('depreciation_runs')->whereNull('deleted_at')->whereIn('status', ['posted', 'finalized'])->orderByDesc('period_end')->orderByDesc('id')
            ->get(['id', 'run_number', 'period_label'])->mapWithKeys(fn ($x) => [$x->id => $x->run_number.' · '.$x->period_label])->all();
        $filters = [
            ['name' => 'fy', 'label' => 'Financial year', 'type' => 'select', 'options' => $this->fyOptions(), 'default' => $fy?->id],
            ['name' => 'run', 'label' => 'Run', 'type' => 'select', 'options' => $runs],
            ['name' => 'category', 'label' => 'Category', 'type' => 'select', 'options' => $this->lookup('asset_categories')],
            ['name' => 'location', 'label' => 'Location', 'type' => 'select', 'options' => $this->lookup('locations')],
            ['name' => 'cost_center', 'label' => 'Cost center', 'type' => 'select', 'options' => $this->lookup('cost_centers', 'code')],
            ['name' => 'q', 'label' => 'Asset', 'type' => 'text'],
        ];
        $rows = $this->t('depreciation_entries', 'e')->whereNull('e.deleted_at')
            ->join('depreciation_runs as r', 'r.id', '=', 'e.depreciation_run_id')
            ->join('assets as a', 'a.id', '=', 'e.asset_id')
            ->leftJoin('asset_categories as c', 'c.id', '=', 'e.asset_category_id')
            ->leftJoin('cost_centers as cc', 'cc.id', '=', 'e.cost_center_id')
            ->leftJoin('locations as l', 'l.id', '=', 'e.location_id')
            ->whereIn('r.status', ['posted', 'finalized'])
            ->when($r->filled('run'), fn ($q) => $q->where('e.depreciation_run_id', $r->run), fn ($q) => $q->when($fy, fn ($q) => $q->where('e.financial_year_id', $fy->id)))
            ->when($r->filled('category'), fn ($q) => $q->where('e.asset_category_id', $r->category))
            ->when($r->filled('location'), fn ($q) => $q->where('e.location_id', $r->location))
            ->when($r->filled('cost_center'), fn ($q) => $q->where('e.cost_center_id', $r->cost_center))
            ->when($r->filled('q'), fn ($q) => $q->where(fn ($w) => $w->whereLike('a.asset_code', "%{$r->q}%")->orWhereLike('a.name', "%{$r->q}%")))
            ->orderBy('e.period_end')->orderBy('a.asset_code')
            ->get(['r.run_number', 'r.period_label', 'r.run_type', 'e.period_start', 'e.period_end', 'a.asset_code', 'a.name', 'c.name as category', 'cc.code as cc', 'l.name as location',
                'e.method_code', 'e.days', 'e.rate', 'e.opening_gross_block', 'e.opening_accumulated_depreciation', 'e.depreciation_amount', 'e.closing_accumulated_depreciation', 'e.closing_wdv'])
            ->map(fn ($e) => [
                'run' => $e->run_number, 'period' => $e->run_type === 'disposal' ? 'Disposal '.fmt_date($e->period_end) : $e->period_label, 'asset_code' => $e->asset_code, 'name' => $e->name,
                'category' => $e->category, 'cc' => $e->cc, 'location' => $e->location, 'method' => $e->method_code, 'days' => (int) $e->days, 'rate' => $e->rate !== null ? (float) $e->rate : null,
                'gross' => (float) $e->opening_gross_block, 'opening' => (float) $e->opening_accumulated_depreciation, 'amount' => (float) $e->depreciation_amount,
                'closing' => (float) $e->closing_accumulated_depreciation, 'nbv' => (float) $e->closing_wdv,
            ])->all();
        if (! $r->filled('run')) {
            $r->merge(['fy' => $r->input('fy', $fy?->id)]);
        }

        return [
            'filters' => $filters, 'applied' => $this->applied($filters, $r), 'rows' => $rows,
            'title' => 'Depreciation Detail'.($r->filled('run') ? ' – '.($runs[$r->run] ?? '') : ($fy ? ' – '.$fy->name : '')),
            'columns' => [self::col('run', 'Run', 'code'), self::col('period', 'Period'), self::col('asset_code', 'Asset code', 'code'), self::col('name', 'Description'), self::col('category', 'Category'),
                self::col('cc', 'Cost ctr'), self::col('method', 'Method'), self::col('days', 'Days', 'int'), self::col('rate', 'Rate %', 'number'), self::col('gross', 'Gross block', 'money'),
                self::col('opening', 'Opening acc. dep.', 'money'), self::col('amount', 'Depreciation', 'money', true), self::col('closing', 'Closing acc. dep.', 'money'), self::col('nbv', 'Closing NBV', 'money')],
        ];
    }

    /** Schedule II / Ind AS 16 PPE note: gross block and depreciation movement by class. */
    public function depreciationSummary(Request $r): array
    {
        $fy = $this->fy($r);
        $filters = [['name' => 'fy', 'label' => 'Financial year', 'type' => 'select', 'options' => $this->fyOptions(), 'default' => $fy?->id]];
        $r->merge(['fy' => $r->input('fy', $fy?->id)]);
        if (! $fy) {
            return ['filters' => $filters, 'rows' => [], 'columns' => []];
        }
        $S = $fy->start_date->toDateString();
        $E = $fy->end_date->toDateString();

        $disp = $this->t('asset_disposals')->whereNull('deleted_at')->where('status', 'completed')->get(['asset_id', 'disposal_date', 'accumulated_depreciation_at_disposal'])->keyBy('asset_id');
        $assets = $this->t('assets', 'a')->whereNull('a.deleted_at')->where('a.original_cost', '>', 0)->where('a.capitalization_date', '<=', $E)
            ->get(['a.id', 'a.asset_category_id', 'a.original_cost', 'a.capitalization_date', 'a.opening_balance_date', 'a.opening_accumulated_depreciation']);
        $before = $this->t('depreciation_entries')->whereNull('deleted_at')->where('period_end', '<', $S)->groupBy('asset_id')->selectRaw('asset_id, SUM(depreciation_amount) s')->pluck('s', 'asset_id');
        $during = $this->t('depreciation_entries')->whereNull('deleted_at')->whereBetween('period_end', [$S, $E])->groupBy('asset_id')->selectRaw('asset_id, SUM(depreciation_amount) s')->pluck('s', 'asset_id');
        $cats = $this->t('asset_categories', 'c')->leftJoin('depreciation_methods as m', 'm.id', '=', 'c.depreciation_method_id')
            ->get(['c.id', 'c.name', 'c.code', 'c.useful_life_years', 'c.useful_life_months', 'c.schedule_ii_reference', 'm.code as method', 'c.wdv_rate'])->keyBy('id');

        $by = [];
        foreach ($assets as $a) {
            $d = $disp[$a->id] ?? null;
            if ($d && $d->disposal_date < $S) {
                continue;
            }
            $k = $a->asset_category_id;
            $by[$k] ??= ['gross_open' => 0, 'additions' => 0, 'deletions' => 0, 'acc_open' => 0, 'dep' => 0, 'acc_disp' => 0, 'count' => 0];
            $g = &$by[$k];
            $g['count']++;
            $cost = (float) $a->original_cost;
            $a->capitalization_date < $S ? $g['gross_open'] += $cost : $g['additions'] += $cost;
            $opening = $a->opening_balance_date && $a->opening_balance_date <= $E ? (float) $a->opening_accumulated_depreciation : 0;
            $g['acc_open'] += $opening + (float) ($before[$a->id] ?? 0);
            $g['dep'] += (float) ($during[$a->id] ?? 0);
            if ($d && $d->disposal_date <= $E) {
                $g['deletions'] += $cost;
                $g['acc_disp'] += (float) $d->accumulated_depreciation_at_disposal;
            }
            unset($g);
        }
        $rows = [];
        foreach ($by as $cid => $g) {
            $c = $cats[$cid] ?? null;
            $gc = $g['gross_open'] + $g['additions'] - $g['deletions'];
            $ac = $g['acc_open'] + $g['dep'] - $g['acc_disp'];
            $life = $c ? trim(($c->useful_life_years ? $c->useful_life_years.' yrs' : '').($c->useful_life_months ? ' '.$c->useful_life_months.' m' : '')) : '';
            $rows[] = [
                'category' => $c?->name ?? '(none)', 'basis' => trim(($c?->method ?? '').' · '.$life, ' ·'),
                'gross_open' => round($g['gross_open'], 2), 'additions' => round($g['additions'], 2), 'deletions' => round($g['deletions'], 2), 'gross_close' => round($gc, 2),
                'acc_open' => round($g['acc_open'], 2), 'dep' => round($g['dep'], 2), 'acc_disp' => round($g['acc_disp'], 2), 'acc_close' => round($ac, 2),
                'nbv_open' => round($g['gross_open'] - $g['acc_open'], 2), 'nbv_close' => round($gc - $ac, 2),
            ];
        }
        usort($rows, fn ($x, $y) => strcmp($x['category'], $y['category']));

        $company = Tenant::company();
        $notes = [
            'Depreciation is provided on the '.($company?->depreciation_basis === 'income_tax' ? 'Income-tax Act' : 'Companies Act, 2013 Schedule II').' basis over the useful lives shown, '
                .'pro-rata to the number of days the asset is available for use. Residual value is generally 5% of original cost.',
            'Deletions are stated at original cost; "On disposals" is the accumulated depreciation (including pro-rata charge to the disposal date) written back on assets sold or scrapped.',
            'Opening balances for assets acquired before the go-live date are carried at the accumulated depreciation as at '.fmt_date($this->t('assets')->min('opening_balance_date')).'.',
        ];

        return [
            'filters' => $filters, 'applied' => $this->applied($filters, $r), 'rows' => $rows, 'notes' => $notes,
            'title' => 'Property, Plant & Equipment – '.$fy->name.' (Schedule II / Ind AS 16)',
            // On screen the "Method · life" column is shown under the asset class to save width; exports keep it separate.
            'screenMerge' => ['basis' => 'category'],
            'groupHeaders' => [['', 2], ['Gross block (at cost)', 4], ['Accumulated depreciation', 4], ['Net block', 2]],
            'columns' => [self::col('category', 'Asset class'), self::col('basis', 'Method · life'),
                self::col('gross_open', 'As at '.$fy->start_date->format('d M Y'), 'money', true), self::col('additions', 'Additions', 'money', true), self::col('deletions', 'Deletions', 'money', true), self::col('gross_close', 'As at '.$fy->end_date->format('d M Y'), 'money', true),
                self::col('acc_open', 'As at '.$fy->start_date->format('d M Y'), 'money', true), self::col('dep', 'For the year', 'money', true), self::col('acc_disp', 'On disposals', 'money', true), self::col('acc_close', 'As at '.$fy->end_date->format('d M Y'), 'money', true),
                self::col('nbv_open', 'As at '.$fy->start_date->format('d M Y'), 'money', true), self::col('nbv_close', 'As at '.$fy->end_date->format('d M Y'), 'money', true)],
        ];
    }

    private function assetMovement(Request $r): array
    {
        $fy = $this->fy($r);
        $from = $r->input('from', $fy?->start_date->toDateString());
        $to = $r->input('to', now()->toDateString());
        $r->merge(['from' => $from, 'to' => $to]);
        $filters = [
            ['name' => 'from', 'label' => 'From', 'type' => 'date'], ['name' => 'to', 'label' => 'To', 'type' => 'date'],
            ['name' => 'event', 'label' => 'Movement', 'type' => 'select', 'options' => ['addition' => 'Additions', 'transfer' => 'Transfers', 'disposal' => 'Disposals']],
            ['name' => 'location', 'label' => 'Location', 'type' => 'select', 'options' => $this->lookup('locations')],
        ];
        $ev = $r->input('event');
        $loc = $r->input('location');
        $rows = collect();
        if (! $ev || $ev === 'addition') {
            $rows = $rows->concat($this->t('assets', 'a')->whereNull('a.deleted_at')->whereBetween('a.capitalization_date', [$from, $to])
                ->when($loc, fn ($q) => $q->whereRaw('COALESCE((SELECT t.from_location_id FROM asset_transfers t WHERE t.asset_id = a.id AND t.deleted_at IS NULL ORDER BY t.transfer_date, t.id LIMIT 1), a.location_id) = ?', [$loc]))
                ->leftJoin('locations as l', 'l.id', '=', 'a.location_id')->leftJoin('cost_centers as cc', 'cc.id', '=', 'a.cost_center_id')
                ->get(['a.capitalization_date as d', 'a.asset_code', 'a.name', 'a.original_cost', 'a.invoice_number', 'l.name as loc', 'cc.code as cc'])
                ->map(fn ($x) => ['date' => $x->d, 'event' => 'Addition', 'ref' => $x->invoice_number, 'asset_code' => $x->asset_code, 'name' => $x->name, 'from' => '—', 'to' => trim($x->loc.' / '.$x->cc, ' /'), 'amount' => (float) $x->original_cost, 'remarks' => 'Capitalised']));
        }
        if (! $ev || $ev === 'transfer') {
            $rows = $rows->concat($this->t('asset_transfers', 't')->whereNull('t.deleted_at')->whereBetween('t.transfer_date', [$from, $to])
                ->when($loc, fn ($q) => $q->where(fn ($w) => $w->where('t.from_location_id', $loc)->orWhere('t.to_location_id', $loc)))
                ->join('assets as a', 'a.id', '=', 't.asset_id')
                ->leftJoin('locations as fl', 'fl.id', '=', 't.from_location_id')->leftJoin('locations as tl', 'tl.id', '=', 't.to_location_id')
                ->leftJoin('cost_centers as fc', 'fc.id', '=', 't.from_cost_center_id')->leftJoin('cost_centers as tc', 'tc.id', '=', 't.to_cost_center_id')
                ->leftJoin('departments as fd', 'fd.id', '=', 't.from_department_id')->leftJoin('departments as td', 'td.id', '=', 't.to_department_id')
                ->get(['t.transfer_date as d', 't.transfer_number', 'a.asset_code', 'a.name', 'a.original_cost', 't.reason', 'fl.name as fl', 'tl.name as tl', 'fc.code as fc', 'tc.code as tc', 'fd.name as fd', 'td.name as td'])
                ->map(fn ($x) => ['date' => $x->d, 'event' => 'Transfer', 'ref' => $x->transfer_number, 'asset_code' => $x->asset_code, 'name' => $x->name,
                    'from' => implode(' / ', array_filter([$x->fl, $x->fd, $x->fc])), 'to' => implode(' / ', array_filter([$x->tl, $x->td, $x->tc])), 'amount' => (float) $x->original_cost, 'remarks' => $x->reason]));
        }
        if (! $ev || $ev === 'disposal') {
            $rows = $rows->concat($this->t('asset_disposals', 'x')->whereNull('x.deleted_at')->where('x.status', 'completed')->whereBetween('x.disposal_date', [$from, $to])
                ->join('assets as a', 'a.id', '=', 'x.asset_id')->leftJoin('locations as l', 'l.id', '=', 'a.location_id')
                ->when($loc, fn ($q) => $q->where('a.location_id', $loc))
                ->get(['x.disposal_date as d', 'x.disposal_number', 'x.disposal_type', 'x.buyer_name', 'a.asset_code', 'a.name', 'x.gross_block_at_disposal', 'l.name as loc'])
                ->map(fn ($x) => ['date' => $x->d, 'event' => 'Disposal', 'ref' => $x->disposal_number, 'asset_code' => $x->asset_code, 'name' => $x->name, 'from' => $x->loc,
                    'to' => AssetDisposal::TYPES[$x->disposal_type].($x->buyer_name ? ' – '.$x->buyer_name : ''), 'amount' => -(float) $x->gross_block_at_disposal, 'remarks' => 'Removed from register']));
        }
        $rows = $rows->sortBy([['date', 'asc'], ['asset_code', 'asc']])->values()->all();

        return [
            'filters' => $filters, 'applied' => $this->applied($filters, $r), 'rows' => $rows,
            'notes' => ['Amount = original cost; additions increase and disposals reduce the gross block. Transfers show the cost moved between locations / cost centers and do not change the total.'],
            'totals' => ['amount' => round(collect($rows)->where('event', '!=', 'Transfer')->sum('amount'), 2)],
            'columns' => [self::col('date', 'Date', 'date'), self::col('event', 'Movement', 'pill'), self::col('ref', 'Reference', 'code'), self::col('asset_code', 'Asset code', 'code'), self::col('name', 'Description'),
                self::col('from', 'From'), self::col('to', 'To'), self::col('amount', 'Cost', 'pl', true), self::col('remarks', 'Remarks')],
        ];
    }

    private function maintenanceHistory(Request $r): array
    {
        $fy = $this->fy($r);
        $from = $r->input('from', $fy?->start_date->toDateString());
        $to = $r->input('to', now()->toDateString());
        $r->merge(['from' => $from, 'to' => $to]);
        $filters = [
            ['name' => 'from', 'label' => 'From', 'type' => 'date'], ['name' => 'to', 'label' => 'To', 'type' => 'date'],
            ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => WorkOrder::TYPES],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => WorkOrder::STATUSES],
            ['name' => 'location', 'label' => 'Location', 'type' => 'select', 'options' => $this->lookup('locations')],
            ['name' => 'q', 'label' => 'Asset', 'type' => 'text'],
        ];
        $rows = $this->t('work_orders', 'w')->whereNull('w.deleted_at')->whereBetween('w.reported_date', [$from, $to])
            ->join('assets as a', 'a.id', '=', 'w.asset_id')->leftJoin('vendors as v', 'v.id', '=', 'w.vendor_id')
            ->when($r->filled('type'), fn ($q) => $q->where('w.type', $r->type))
            ->when($r->filled('status'), fn ($q) => $q->where('w.status', $r->status))
            ->when($r->filled('location'), fn ($q) => $q->where('a.location_id', $r->location))
            ->when($r->filled('q'), fn ($q) => $q->where(fn ($w) => $w->whereLike('a.asset_code', "%{$r->q}%")->orWhereLike('a.name', "%{$r->q}%")))
            ->orderBy('w.reported_date')->orderBy('w.id')
            ->get(['w.wo_number', 'w.reported_date', 'w.completed_at', 'a.asset_code', 'a.name', 'w.type', 'w.priority', 'w.status', 'w.title', 'w.downtime_hours', 'w.labour_cost', 'w.parts_cost', 'w.external_cost', 'w.total_cost', 'v.name as vendor', 'w.assigned_to'])
            ->map(fn ($w) => ['wo' => $w->wo_number, 'reported' => $w->reported_date, 'completed' => $w->completed_at ? substr($w->completed_at, 0, 10) : null, 'asset_code' => $w->asset_code, 'name' => $w->name,
                'title' => $w->title, 'type' => $w->type, 'status' => $w->status, 'by' => $w->vendor ?? $w->assigned_to, 'downtime' => (float) $w->downtime_hours,
                'labour' => (float) $w->labour_cost, 'parts' => (float) $w->parts_cost, 'external' => (float) $w->external_cost, 'total' => (float) $w->total_cost])->all();

        return [
            'filters' => $filters, 'applied' => $this->applied($filters, $r), 'rows' => $rows,
            'columns' => [self::col('wo', 'WO no.', 'code'), self::col('reported', 'Reported', 'date'), self::col('completed', 'Completed', 'date'), self::col('asset_code', 'Asset', 'code'), self::col('name', 'Asset name'),
                self::col('title', 'Work'), self::col('type', 'Type', 'pill'), self::col('status', 'Status', 'pill'), self::col('by', 'Done by'), self::col('downtime', 'Downtime h', 'number', true),
                self::col('labour', 'Labour', 'money', true), self::col('parts', 'Parts', 'money', true), self::col('external', 'External', 'money', true), self::col('total', 'Total', 'money', true)],
        ];
    }

    private function disposalRegister(Request $r): array
    {
        $filters = [
            ['name' => 'from', 'label' => 'From', 'type' => 'date'], ['name' => 'to', 'label' => 'To', 'type' => 'date'],
            ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => AssetDisposal::TYPES],
            ['name' => 'category', 'label' => 'Category', 'type' => 'select', 'options' => $this->lookup('asset_categories')],
        ];
        $rows = $this->t('asset_disposals', 'x')->whereNull('x.deleted_at')->where('x.status', 'completed')
            ->join('assets as a', 'a.id', '=', 'x.asset_id')->leftJoin('asset_categories as c', 'c.id', '=', 'a.asset_category_id')
            ->when($r->filled('from'), fn ($q) => $q->where('x.disposal_date', '>=', $r->from))
            ->when($r->filled('to'), fn ($q) => $q->where('x.disposal_date', '<=', $r->to))
            ->when($r->filled('type'), fn ($q) => $q->where('x.disposal_type', $r->type))
            ->when($r->filled('category'), fn ($q) => $q->where('a.asset_category_id', $r->category))
            ->orderBy('x.disposal_date')
            ->get(['x.*', 'a.asset_code', 'a.name', 'a.capitalization_date', 'c.name as category'])
            ->map(fn ($x) => ['number' => $x->disposal_number, 'date' => $x->disposal_date, 'asset_code' => $x->asset_code, 'name' => $x->name, 'category' => $x->category, 'cap' => $x->capitalization_date,
                'type' => $x->disposal_type, 'buyer' => $x->buyer_name, 'gross' => (float) $x->gross_block_at_disposal, 'acc' => (float) $x->accumulated_depreciation_at_disposal,
                'prorata' => (float) $x->depreciation_upto_disposal, 'nbv' => (float) $x->nbv_at_disposal, 'sale' => (float) $x->sale_value, 'cost' => (float) $x->disposal_cost, 'pl' => (float) $x->profit_loss])->all();

        return [
            'filters' => $filters, 'applied' => $this->applied($filters, $r), 'rows' => $rows,
            'notes' => ['Profit / (loss) = sale value − cost of disposal − NBV. Accumulated depreciation includes the pro-rata charge up to the disposal date.'],
            'columns' => [self::col('number', 'Disposal no.', 'code'), self::col('date', 'Date', 'date'), self::col('asset_code', 'Asset', 'code'), self::col('name', 'Description'), self::col('category', 'Category'),
                self::col('cap', 'Put to use', 'date'), self::col('type', 'Type', 'pill'), self::col('buyer', 'Buyer'), self::col('gross', 'Gross block', 'money', true), self::col('prorata', 'Pro-rata dep.', 'money', true),
                self::col('acc', 'Acc. dep.', 'money', true), self::col('nbv', 'NBV', 'money', true), self::col('sale', 'Sale value', 'money', true), self::col('cost', 'Disposal cost', 'money', true), self::col('pl', 'Profit / (loss)', 'pl', true)],
        ];
    }

    private function auditTrail(Request $r): array
    {
        $from = $r->input('from', now()->subDays(90)->toDateString());
        $to = $r->input('to', now()->toDateString());
        $r->merge(['from' => $from, 'to' => $to]);
        $types = $this->t('audit_logs')->whereNotNull('auditable_type')->distinct()->pluck('auditable_type')->mapWithKeys(fn ($t) => [$t => Str::headline(class_basename($t))])->sort()->all();
        $filters = [
            ['name' => 'from', 'label' => 'From', 'type' => 'date'], ['name' => 'to', 'label' => 'To', 'type' => 'date'],
            ['name' => 'user', 'label' => 'User', 'type' => 'select', 'options' => DB::table('users')->where('company_id', Tenant::id())->orderBy('name')->pluck('name', 'id')->all()],
            ['name' => 'event', 'label' => 'Event', 'type' => 'select', 'options' => $this->t('audit_logs')->distinct()->orderBy('event')->pluck('event', 'event')->map(fn ($e) => Str::headline($e))->all()],
            ['name' => 'entity', 'label' => 'Record type', 'type' => 'select', 'options' => $types],
            ['name' => 'q', 'label' => 'Record', 'type' => 'text'],
        ];
        $rows = $this->t('audit_logs')
            ->whereBetween('created_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])
            ->when($r->filled('user'), fn ($q) => $q->where('user_id', $r->user))
            ->when($r->filled('event'), fn ($q) => $q->where('event', $r->event))
            ->when($r->filled('entity'), fn ($q) => $q->where('auditable_type', $r->entity))
            ->when($r->filled('q'), fn ($q) => $q->whereLike('auditable_label', "%{$r->q}%"))
            ->orderByDesc('created_at')->orderByDesc('id')->limit(5000)
            ->get(['created_at', 'user_name', 'event', 'auditable_type', 'auditable_label', 'old_values', 'new_values', 'ip_address'])
            ->map(function ($l) {
                $new = json_decode($l->new_values ?? '[]', true) ?: [];
                $old = json_decode($l->old_values ?? '[]', true) ?: [];
                $changes = $l->event === 'updated'
                    ? collect($new)->map(fn ($v, $k) => Str::headline($k).': '.Str::limit($this->scalar($old[$k] ?? null), 30).' → '.Str::limit($this->scalar($v), 30))->implode('; ')
                    : collect($new)->only(['period', 'total', 'reason', 'run_number', 'status', 'name', 'asset_code', 'original_cost', 'disposal_type', 'profit_loss', 'transfer_number'])->map(fn ($v, $k) => Str::headline($k).': '.Str::limit($this->scalar($v), 40))->implode('; ');

                return ['at' => Carbon::parse($l->created_at)->format('d M Y H:i'), 'user' => $l->user_name ?? 'system', 'event' => $l->event,
                    'entity' => $l->auditable_type ? Str::headline(class_basename($l->auditable_type)) : '—', 'label' => $l->auditable_label, 'changes' => Str::limit($changes, 300), 'ip' => $l->ip_address ?? 'console'];
            })->all();

        return [
            'filters' => $filters, 'applied' => $this->applied($filters, $r), 'rows' => $rows,
            'notes' => count($rows) >= 5000 ? ['Showing the latest 5,000 events; narrow the date range for more.'] : [],
            'columns' => [self::col('at', 'When'), self::col('user', 'User'), self::col('event', 'Event', 'pill'), self::col('entity', 'Record type'), self::col('label', 'Record'), self::col('changes', 'Details'), self::col('ip', 'IP')],
        ];
    }

    private function scalar($v): string
    {
        return is_array($v) ? json_encode($v) : (string) ($v ?? '∅');
    }

    // --------------------------------------------------------------- format

    public static function format($v, string $type, bool $plain = false): string
    {
        if ($v === null || $v === '') {
            return $type === 'money' || $type === 'pl' ? '—' : ($plain ? '' : '—');
        }

        return match ($type) {
            'money' => inr($v, 2, false),
            'pl' => (float) $v < 0 ? '('.inr(abs($v), 2, false).')' : inr($v, 2, false),
            'number' => number_format((float) $v, 2),
            'int' => number_format((int) $v),
            'percent' => number_format((float) $v, 1).'%',
            'date' => fmt_date($v),
            'pill' => Str::headline((string) $v),
            default => (string) $v,
        };
    }
}
