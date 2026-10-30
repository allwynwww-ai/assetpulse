<?php

namespace App\Http\Controllers;

use App\Services\Reports\ReportBuilder;
use App\Services\Reports\ReportExporter;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ReportController extends Controller
{
    public function index()
    {
        $groups = collect(ReportBuilder::REPORTS)->map(fn ($r, $slug) => ['slug' => $slug, 'title' => $r[0], 'desc' => $r[1], 'icon' => $r[2], 'group' => $r[3]])->groupBy('group');

        return view('reports.index', compact('groups'));
    }

    public function show(Request $request, string $report, ReportBuilder $builder, ReportExporter $exporter)
    {
        abort_unless(ReportBuilder::exists($report), 404);
        $request->validate([
            'export' => 'nullable|in:xlsx,pdf',
            'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from',
            'cap_from' => 'nullable|date', 'cap_to' => 'nullable|date',
        ]);
        @set_time_limit(120);
        $data = $builder->build($report, $request);

        return match ($request->input('export')) {
            'xlsx' => $exporter->xlsx($data),
            'pdf' => $exporter->pdf($data),
            default => $this->html($request, $data),
        };
    }

    private function html(Request $request, array $data)
    {
        $per = 100;
        $page = max(1, (int) $request->input('page', 1));
        $rows = collect($data['rows']);
        $paginator = new LengthAwarePaginator($rows->forPage($page, $per)->values(), $rows->count(), $per, $page, ['path' => $request->url(), 'query' => $request->except('page')]);

        return view('reports.show', ['r' => $data, 'rows' => $paginator]);
    }
}
