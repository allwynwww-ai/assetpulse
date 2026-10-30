@php $canWrite = auth()->user()->canManageAssets(); @endphp
<x-app-layout title="Dashboard">
    <x-slot name="header">
        <x-page-header title="{{ greeting() }}, {{ \Illuminate\Support\Str::before(auth()->user()->name.' ', ' ') }}"
                       :subtitle="($currentCompany?->name ?? '').($currentFy ? ' · '.$currentFy->name : '').($lockedThrough ? ' · books locked through '.fmt_date($lockedThrough) : '')" icon="home">
            <a href="{{ route('reports.index') }}" class="btn-secondary"><x-icon name="chart" class="h-4 w-4"/> Reports</a>
            @if ($canWrite)<a href="{{ route('assets.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4"/> Add asset</a>@endif
        </x-page-header>
    </x-slot>

    {{-- Attention strip --}}
    @if ($pmOverdue || $openBreakdowns->count() || $pendingRuns->count())
        <div class="mb-6 grid gap-3 md:grid-cols-3">
            @if ($pmOverdue)
                <a href="{{ route('pm-schedules.index', ['view' => 'overdue']) }}" class="flex items-center gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 hover:bg-rose-100">
                    <x-icon name="calendar" class="h-5 w-5 shrink-0"/><span><b>{{ $pmOverdue }}</b> preventive maintenance task{{ $pmOverdue > 1 ? 's' : '' }} overdue</span><x-icon name="chevron-right" class="ml-auto h-4 w-4"/></a>
            @endif
            @if ($openBreakdowns->count())
                <a href="{{ route('work-orders.index', ['type' => 'breakdown', 'status' => 'open']) }}" class="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 hover:bg-amber-100">
                    <x-icon name="wrench" class="h-5 w-5 shrink-0"/><span><b>{{ $openBreakdowns->count() }}</b> open breakdown{{ $openBreakdowns->count() > 1 ? 's' : '' }} · {{ $openBreakdowns->first()->asset?->asset_code }}</span><x-icon name="chevron-right" class="ml-auto h-4 w-4"/></a>
            @endif
            @if ($pendingRuns->count())
                <a href="{{ route('depreciation.show', $pendingRuns->first()) }}" class="flex items-center gap-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800 hover:bg-sky-100">
                    <x-icon name="calculator" class="h-5 w-5 shrink-0"/><span>Depreciation for <b>{{ $pendingRuns->pluck('period_label')->implode(', ') }}</b> posted, awaiting finalisation</span><x-icon name="chevron-right" class="ml-auto h-4 w-4"/></a>
            @endif
        </div>
    @endif

    {{-- KPIs --}}
    <div class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-3 2xl:grid-cols-6">
        <a href="{{ route('assets.index') }}" class="card p-5 hover:shadow-soft"><div class="stat-label">Assets in use</div><div class="stat-value">{{ number_format($totals->n) }}</div>
            <div class="mt-1 text-xs text-slate-400">{{ $underMaintenance ? $underMaintenance.' under maintenance' : 'all operational' }}</div></a>
        <div class="card p-5"><div class="stat-label">Gross block</div><div class="stat-value">{{ inr_short($totals->cost) }}</div><div class="mt-1 text-xs text-slate-400">{{ inr($totals->cost, 0) }}</div></div>
        <div class="card p-5 ring-1 ring-mint-200"><div class="stat-label">Net block (NBV)</div><div class="stat-value text-mint-700">{{ inr_short($totals->nbv) }}</div>
            <div class="mt-1 text-xs text-slate-400">{{ $totals->cost > 0 ? round($totals->nbv / $totals->cost * 100) : 0 }}% of cost · acc. dep. {{ inr_short($totals->acc) }}</div></div>
        <a href="{{ route('reports.show', 'depreciation-summary') }}" class="card p-5 hover:shadow-soft"><div class="stat-label">Depreciation {{ $currentFy ? 'FY '.str_replace('FY ', '', $currentFy->name) : 'this FY' }}</div><div class="stat-value text-amber-700">{{ inr_short($fyDep) }}</div>
            <div class="mt-1 text-xs text-slate-400">{{ inr($fyDep, 0) }} year to date</div></a>
        <a href="{{ $lastRun ? route('depreciation.show', $lastRun) : route('depreciation.index') }}" class="card p-5 hover:shadow-soft"><div class="stat-label">Depreciation {{ $lastRun ? $lastRun->period_end->format('M Y') : 'last month' }}</div><div class="stat-value">{{ inr_short($monthDep) }}</div>
            <div class="mt-1 text-xs text-slate-400">{{ $lastRun ? $lastRun->run_number.' · '.$lastRun->status : 'no run yet' }}</div></a>
        <a href="{{ route('reports.show', 'maintenance-history') }}" class="card p-5 hover:shadow-soft"><div class="stat-label">Maintenance cost YTD</div><div class="stat-value">{{ inr_short($maintYtd) }}</div>
            <div class="mt-1 text-xs text-slate-400">{{ $openWo }} open work order{{ $openWo === 1 ? '' : 's' }}</div></a>
    </div>

    {{-- Charts --}}
    <div class="mb-6 grid gap-6 lg:grid-cols-2 xl:grid-cols-5">
        <div class="card xl:col-span-2">
            <div class="card-header"><div class="card-title">Gross block by category</div><a href="{{ route('reports.show', 'assets-by-category') }}" class="text-xs font-medium text-brand-600 hover:underline">Report →</a></div>
            @php $cat = $charts['category']; $catTotal = max((float) collect($cat['values'])->sum(), 1); @endphp
            <div class="card-body flex flex-col items-center gap-5 sm:flex-row sm:items-start lg:flex-col lg:items-center 2xl:flex-row 2xl:items-start">
                <div class="relative h-48 w-48 shrink-0 sm:mt-1 lg:mt-0 2xl:mt-1"><canvas id="chartCategory"></canvas></div>
                <ul class="w-full min-w-0 flex-1 space-y-1.5 text-xs">
                    @foreach ($cat['labels'] as $i => $label)
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 h-2.5 w-2.5 shrink-0 rounded-sm" style="background: {{ $cat['colors'][$i] ?? '#94a3b8' }}"></span>
                            <span class="min-w-0 flex-1 leading-4 text-slate-600">{{ $label }} <span class="text-slate-400">({{ $cat['counts'][$i] ?? 0 }})</span></span>
                            <span class="shrink-0 whitespace-nowrap text-right font-medium tabular-nums text-slate-800">{{ inr_short($cat['values'][$i]) }}</span>
                            <span class="w-9 shrink-0 text-right tabular-nums text-slate-400">{{ number_format($cat['values'][$i] * 100 / $catTotal, 0) }}%</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
        <div class="card xl:col-span-3">
            <div class="card-header"><div class="card-title">Cost vs NBV by location</div><a href="{{ route('reports.show', 'assets-by-location') }}" class="text-xs font-medium text-brand-600 hover:underline">Report →</a></div>
            <div class="card-body"><div class="relative h-64"><canvas id="chartLocation"></canvas></div></div>
        </div>
        <div class="card lg:col-span-2 xl:col-span-5">
            <div class="card-header"><div class="card-title">Monthly depreciation – last 12 months</div><a href="{{ route('depreciation.index') }}" class="text-xs font-medium text-brand-600 hover:underline">Runs →</a></div>
            <div class="card-body"><div class="relative h-60"><canvas id="chartTrend"></canvas></div></div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        {{-- Upcoming PM --}}
        <div class="card">
            <div class="card-header"><div class="card-title">Maintenance due <span class="ml-1 text-xs font-normal text-slate-400">{{ $pmOverdue }} overdue · {{ $pmDue30 }} in 30 days</span></div><a href="{{ route('pm-schedules.index', ['view' => 'due30']) }}" class="text-xs font-medium text-brand-600 hover:underline">All →</a></div>
            <ul class="divide-y divide-slate-100">
                @forelse ($upcomingPm as $pm)
                    @php $over = $pm->next_due_date->lt(today()); @endphp
                    <li class="flex items-center gap-3 px-5 py-2.5 text-sm">
                        <div class="w-14 shrink-0 text-center"><div class="text-[10px] font-semibold uppercase {{ $over ? 'text-rose-600' : 'text-amber-600' }}">{{ $pm->next_due_date->format('M') }}</div><div class="text-lg font-bold leading-5 {{ $over ? 'text-rose-600' : 'text-slate-800' }}">{{ $pm->next_due_date->format('d') }}</div></div>
                        <div class="min-w-0 flex-1">
                            <div class="line-clamp-2 font-medium leading-5 text-slate-800" title="{{ $pm->title }}">{{ $pm->title }}</div>
                            <div class="line-clamp-2 text-xs text-slate-500" title="{{ $pm->asset?->asset_code }} {{ $pm->asset?->name }}"><a href="{{ route('assets.show', $pm->asset_id) }}" class="font-mono text-brand-700">{{ $pm->asset?->asset_code }}</a> {{ $pm->asset?->name }}</div>
                        </div>
                        @if ($over)<span class="badge bg-rose-50 text-rose-700 ring-rose-200">{{ (int) $pm->next_due_date->diffInDays(today()) }}d late</span>
                        @elseif ($canWrite)<a href="{{ route('work-orders.create', ['pm' => $pm->id]) }}" class="btn-ghost btn-sm" title="Raise work order"><x-icon name="wrench" class="h-3.5 w-3.5"/></a>@endif
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-sm text-slate-400">Nothing due in the next 30 days. 🎉</li>
                @endforelse
            </ul>
        </div>

        {{-- Ending life --}}
        <div class="card">
            <div class="card-header"><div class="card-title">Reaching end of useful life</div><span class="text-xs text-slate-400">next 12 months</span></div>
            <ul class="divide-y divide-slate-100">
                @forelse ($endingLife as $a)
                    <li class="flex items-center gap-3 px-5 py-2.5 text-sm">
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('assets.show', $a) }}" class="line-clamp-2 font-medium text-slate-800 hover:text-brand-700" title="{{ $a->name }}">{{ $a->name }}</a>
                            <div class="text-xs text-slate-500"><span class="font-mono">{{ $a->asset_code }}</span> · NBV {{ inr($a->current_book_value, 0) }}</div>
                        </div>
                        <div class="text-right text-xs"><div class="font-semibold text-slate-700">{{ fmt_date($a->eol) }}</div><div class="text-slate-400">{{ max(0, (int) now()->diffInMonths(\Illuminate\Support\Carbon::parse($a->eol))) }} mo left</div></div>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-sm text-slate-400">No assets reach end of life in the next 12 months.</li>
                @endforelse
            </ul>
        </div>

        {{-- Quick actions + recent --}}
        <div class="space-y-6">
            @if ($canWrite)
                <div class="card">
                    <div class="card-header"><div class="card-title">Quick actions</div></div>
                    <div class="grid grid-cols-2 gap-2 p-4">
                        @foreach ([['assets.create', 'plus', 'Add asset'], ['depreciation.create', 'calculator', 'Run depreciation'], ['work-orders.create', 'wrench', 'Log breakdown'], ['transfers.create', 'arrows', 'Transfer asset'], ['pm-schedules.create', 'calendar', 'New PM schedule'], ['disposals.create', 'trash', 'Dispose asset']] as [$r, $ic, $l])
                            <a href="{{ route($r) }}" class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700"><x-icon :name="$ic" class="h-4 w-4 text-slate-400"/>{{ $l }}</a>
                        @endforeach
                    </div>
                </div>
            @endif
            <div class="card">
                <div class="card-header"><div class="card-title">Recently added</div><a href="{{ route('assets.index') }}" class="text-xs font-medium text-brand-600 hover:underline">Register →</a></div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($recent as $a)
                        <li class="flex items-center gap-3 px-5 py-2.5 text-sm">
                            <div class="min-w-0 flex-1"><a href="{{ route('assets.show', $a) }}" class="line-clamp-2 font-medium text-slate-800 hover:text-brand-700" title="{{ $a->name }}">{{ $a->name }}</a>
                                <div class="truncate text-xs text-slate-500"><span class="font-mono">{{ $a->asset_code }}</span> · {{ $a->location?->name }}</div></div>
                            <div class="text-right text-xs font-semibold text-slate-700">{{ inr_short($a->original_cost) }}</div>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-sm text-slate-400">No assets yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    @push('scripts')
        <script type="module">
            const d = @json($charts);
            const money = (ctx) => ' ' + window.inr(ctx.parsed.y ?? ctx.parsed);
            new Chart(document.getElementById('chartCategory'), {
                type: 'doughnut',
                data: { labels: d.category.labels, datasets: [{ data: d.category.values, backgroundColor: d.category.colors, borderWidth: 2, borderColor: '#fff', hoverOffset: 6 }] },
                options: { cutout: '62%', maintainAspectRatio: false, plugins: { legend: { display: false },
                    tooltip: { callbacks: { label: (c) => ` ${c.label}: ${window.inr(c.parsed, true)} (${d.category.counts[c.dataIndex]} assets)` } } } },
            });
            new Chart(document.getElementById('chartLocation'), {
                type: 'bar',
                data: { labels: d.location.labels, datasets: [
                    { label: 'Gross cost', data: d.location.cost, backgroundColor: '#bfdbfe', borderRadius: 6 },
                    { label: 'Net book value', data: d.location.nbv, backgroundColor: '#10b981', borderRadius: 6 },
                ] },
                options: { plugins: { legend: { position: 'top', align: 'end' }, tooltip: { callbacks: { label: (c) => ` ${c.dataset.label}: ${window.inr(c.parsed.y)}` } } },
                    scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { callback: (v) => window.inr(v, true) } } } },
            });
            new Chart(document.getElementById('chartTrend'), {
                type: 'line',
                data: { labels: d.trend.labels, datasets: [{ label: 'Depreciation', data: d.trend.values, borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.12)', fill: true, tension: 0.3, pointRadius: 3, pointBackgroundColor: '#f59e0b' }] },
                options: { plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => ' ' + window.inr(c.parsed.y) } } },
                    scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { callback: (v) => window.inr(v, true) } } } },
            });
        </script>
    @endpush
</x-app-layout>
