@php
    $canWrite = auth()->user()->canManageAssets();
    $first = \Illuminate\Support\Str::before(auth()->user()->name.' ', ' ');
    $cost = (float) $totals->cost; $nbv = (float) $totals->nbv; $acc = (float) $totals->acc;
    $nbvPct = $cost > 0 ? $nbv / $cost * 100 : 0;
    $asOf = $lastRun?->period_end;
    $fyShort = $currentFy ? str_replace('FY ', '', $currentFy->name) : null;

    // Attention items – all derived from live records
    $alerts = [];
    if ($pmOverdue) {
        $over = $upcomingPm->filter(fn ($p) => $p->next_due_date->lt(today()));
        $alerts[] = ['rose', 'calendar-check', $pmOverdue.' PM task'.($pmOverdue > 1 ? 's' : '').' overdue',
            $over->take(2)->map(fn ($p) => ($p->asset?->asset_code ? $p->asset->asset_code.' · ' : '').$p->title)->implode(', '), 'View', route('pm-schedules.index', ['view' => 'overdue'])];
    }
    if ($openBreakdowns->count()) {
        $b = $openBreakdowns->first(); $nb = $openBreakdowns->count();
        $alerts[] = ['amber', 'alert', $nb.' open breakdown'.($nb > 1 ? 's' : ''), trim(($b->asset?->asset_code ?? '').' · '.$b->title, ' ·'), $nb === 1 ? 'View WO' : 'View all',
            $nb === 1 ? route('work-orders.show', $b) : route('work-orders.index', ['type' => 'breakdown', 'status' => 'open'])];
    }
    foreach ($pendingRuns->take(2) as $r) {
        $alerts[] = ['brand', 'calculator', $r->period_label.' depreciation posted', inr($r->total_depreciation, 0).' · '.$r->run_number.' awaiting finalisation', 'Review & finalise', route('depreciation.show', $r)];
    }
    if ($depDue && $canWrite) {
        $alerts[] = ['violet', 'calendar', 'Depreciation for '.$depDue->format('M Y').' not run yet', $lastRun ? 'Last run '.$lastRun->run_number.' · '.$lastRun->period_end->format('M Y') : 'No depreciation run has been posted yet', 'Run now', route('depreciation.create')];
    }
    $tone = ['rose' => 'bg-rose-500/10 text-rose-600 dark:text-rose-300', 'amber' => 'bg-amber-500/15 text-amber-700 dark:text-amber-300', 'brand' => 'bg-brand-500/10 text-brand-600 dark:text-brand-300', 'violet' => 'bg-violet-500/10 text-violet-600 dark:text-violet-300'];
    $ctaTone = ['rose' => 'text-rose-600 dark:text-rose-300', 'amber' => 'text-amber-700 dark:text-amber-300', 'brand' => 'text-brand-600 dark:text-brand-300', 'violet' => 'text-violet-600 dark:text-violet-300'];

    $fyPast = $fyMonths->reject(fn ($m) => $m['future']);
    $fyPosted = $fyPast->filter(fn ($m) => $m['value'] > 0);
    $fySub = $fyPosted->count() ? $fyPosted->first()['label'].' – '.$fyPosted->last()['label'].' · '.$fyPosted->count().' month'.($fyPosted->count() > 1 ? 's' : '').' posted' : 'nothing posted yet this year';
    $health = $totals->n > 0 ? $inUse / $totals->n : 0;
    $idle = max(0, $totals->n - $inUse - $underMaintenance);

    $kpis = [
        ['Depreciation '.($fyShort ? 'FY '.$fyShort : 'this FY'), inr_short($fyDep), $fySub, 'calculator', 'bg-violet-500/10 text-violet-600 dark:text-violet-300', route('reports.show', 'depreciation-summary'), 'bars', $fyPast->pluck('value'), '#7C5CFA'],
        ['Depreciation '.($lastRun ? $lastRun->period_end->format('M Y') : 'last run'), inr_short($monthDep), $lastRun ? $lastRun->run_number.' · '.$lastRun->status : 'no run yet', 'calendar', 'bg-brand-500/10 text-brand-600 dark:text-brand-300', $lastRun ? route('depreciation.show', $lastRun) : route('depreciation.index'), 'spark', $charts['trend']['values'], '#4840D6'],
        ['Maintenance cost YTD', inr_short($maintYtd), $openWo.' open work order'.($openWo === 1 ? '' : 's'), 'wrench', 'bg-amber-500/10 text-amber-600 dark:text-amber-300', route('reports.show', 'maintenance-history'), 'spark', $maintTrend, '#F59E0B'],
        ['Asset health', number_format($inUse).' / '.number_format($totals->n), $underMaintenance.' under maintenance'.($idle ? ' · '.$idle.' other' : ''), 'check-circle', 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-300', route('assets.index'), 'ring', $health, '#10B981'],
    ];
    $qa = $canWrite ? [
        ['plus', 'Add asset', 'bg-brand-500/10 text-brand-600 dark:text-brand-300', route('assets.create')],
        ['wrench', 'New work order', 'bg-amber-500/10 text-amber-600 dark:text-amber-300', route('work-orders.create')],
        ['transfer', 'Transfer asset', 'bg-sky-500/10 text-sky-600 dark:text-sky-300', route('transfers.create')],
        ['calculator', 'Run depreciation', 'bg-violet-500/10 text-violet-600 dark:text-violet-300', route('depreciation.create')],
        ['archive', 'Record disposal', 'bg-rose-500/10 text-rose-600 dark:text-rose-300', route('disposals.create')],
        ['chart', 'Reports', 'bg-teal-500/10 text-teal-600 dark:text-teal-300', route('reports.index')],
    ] : [];
    $cat = $charts['category']; $catTotal = max((float) collect($cat['values'])->sum(), 1);
    $catShown = collect($cat['labels'])->take(6);
@endphp
<x-app-layout title="Dashboard">
    {{-- Greeting --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between lg:gap-6">
        <div>
            <div class="text-[12.5px] font-medium text-ink3 lg:text-[13px]"><span class="lg:hidden">{{ now()->format('D, d M') }}</span><span class="hidden lg:inline">{{ now()->format('l, d F Y') }}</span>{{ $currentFy ? ' · '.$currentFy->name : '' }}</div>
            <h1 class="mt-0.5 font-display text-[23px] font-bold tracking-tight text-ink lg:mt-1 lg:text-[28px]">{{ greeting() }}, {{ $first }}</h1>
            <div class="mt-1.5 hidden flex-wrap items-center gap-2 text-[13px] text-ink2 lg:flex">
                @if ($lockedThrough)<span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2 py-0.5 text-[12px] font-medium text-emerald-700 dark:text-emerald-300"><x-icon name="lock" class="h-3.5 w-3.5"/> Books locked through {{ fmt_date($lockedThrough) }}</span><span class="text-ink3">·</span>@endif
                <span>{{ count($alerts) ? count($alerts).' item'.(count($alerts) > 1 ? 's' : '').' need'.(count($alerts) > 1 ? '' : 's').' your attention' : 'Nothing needs your attention right now' }}</span>
            </div>
        </div>
        <div class="hidden items-center gap-2 lg:flex">
            <a href="{{ route('reports.index') }}" class="flex h-10 items-center gap-2 rounded-xl border border-line bg-card px-3.5 text-[13px] font-semibold text-ink shadow-card hover:bg-soft"><x-icon name="chart" class="h-4 w-4"/> Reports</a>
            @if ($canWrite)<a href="{{ route('assets.create') }}" class="flex h-10 items-center gap-2 rounded-xl bg-brand-600 px-3.5 text-[13px] font-semibold text-white shadow-sm hover:bg-brand-700"><x-icon name="plus" class="h-4 w-4"/> Add asset</a>@endif
        </div>
    </div>

    {{-- Mobile search --}}
    <button type="button" @click="$dispatch('ap:palette')" class="mt-4 flex h-11 w-full items-center gap-2.5 rounded-xl border border-line bg-card px-3.5 text-[14px] text-ink3 shadow-card lg:hidden">
        <x-icon name="search" class="h-[18px] w-[18px]"/> Search asset code, name, serial…
        <span class="ml-auto text-brand-600 dark:text-brand-300" @click.stop="$dispatch('ap:scan')" role="button" aria-label="Scan QR"><x-icon name="scan" class="h-5 w-5"/></span>
    </button>

    {{-- Attention --}}
    @if (count($alerts))
        <section class="mt-5 lg:mt-6">
            <h2 class="mb-2 flex items-center text-[13px] font-semibold text-ink lg:hidden">Needs attention <span class="ml-1.5 rounded-full bg-rose-500/10 px-1.5 text-[11px] font-bold text-rose-600 dark:text-rose-300">{{ count($alerts) }}</span></h2>
            <div class="no-sb -mx-4 flex gap-3 overflow-x-auto px-4 lg:mx-0 lg:grid lg:grid-cols-3 lg:gap-4 lg:overflow-visible lg:px-0">
                @foreach ($alerts as [$t, $ic, $title, $sub, $cta, $url])
                    <a href="{{ $url }}" class="group flex min-w-[290px] items-center gap-3 rounded-2xl border border-line bg-card p-3.5 shadow-card transition hover:shadow-lift lg:min-w-0">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl {{ $tone[$t] }}"><x-icon :name="$ic" class="h-5 w-5"/></span>
                        <div class="min-w-0 flex-1"><div class="truncate text-[13.5px] font-semibold text-ink">{{ $title }}</div><div class="truncate text-[12px] text-ink3">{{ $sub }}</div></div>
                        <span class="hidden shrink-0 items-center gap-1 text-[12px] font-semibold 2xl:flex {{ $ctaTone[$t] }}">{{ $cta }}<x-icon name="chev-right" class="h-3.5 w-3.5"/></span>
                        <x-icon name="chev-right" class="h-4 w-4 shrink-0 text-ink3 2xl:hidden"/>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Hero + KPIs --}}
    <div class="mt-5 grid gap-4 lg:mt-6 xl:grid-cols-12">
        <div class="xl:col-span-5">
            <div class="relative h-full overflow-hidden rounded-2xl bg-gradient-to-br from-brand-700 via-brand-800 to-brand-950 p-5 text-white shadow-lift lg:p-6">
                <svg class="pointer-events-none absolute -right-10 -top-16 h-72 w-72 text-white/[.06]" viewBox="0 0 200 200" aria-hidden="true"><circle cx="100" cy="100" r="98" fill="none" stroke="currentColor" stroke-width="28"/></svg>
                <div class="pointer-events-none absolute -bottom-24 left-1/3 h-48 w-48 rounded-full bg-teal-400/20 blur-3xl"></div>
                <div class="relative">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2 text-[12.5px] font-medium text-brand-100"><x-icon name="layers" class="h-4 w-4"/> Net block value</div>
                        <span class="rounded-full bg-white/10 px-2.5 py-1 text-[11px] font-medium text-brand-100 ring-1 ring-white/15">{{ $asOf ? 'as of '.fmt_date($asOf) : 'no depreciation posted yet' }}</span>
                    </div>
                    <div class="mt-3 flex flex-col gap-1.5 sm:flex-row sm:items-baseline sm:gap-3">
                        <div class="font-display text-[34px] font-extrabold leading-none tracking-tight tnum lg:text-[40px]">{{ inr_short($nbv) }}</div>
                        <div class="text-[13px] text-brand-100"><b class="font-semibold text-white">{{ number_format($nbvPct, 1) }}%</b> of gross block {{ inr_short($cost) }}</div>
                    </div>
                    <div class="mt-5">
                        <div class="flex h-2.5 overflow-hidden rounded-full bg-white/10"><div class="rounded-l-full bg-gradient-to-r from-teal-300 to-emerald-400" style="width: {{ round($nbvPct, 1) }}%"></div><div class="bg-white/25" style="width: {{ round(100 - $nbvPct, 1) }}%"></div></div>
                        <div class="mt-2 flex flex-wrap justify-between gap-x-3 gap-y-1 text-[11.5px] text-brand-100">
                            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-emerald-400"></span>Net block {{ inr_short($nbv) }}</span>
                            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-white/40"></span>Acc. depreciation {{ inr_short($acc) }}</span>
                        </div>
                    </div>
                    <div class="mt-5 grid grid-cols-3 divide-x divide-white/10 rounded-xl bg-white/[.07] ring-1 ring-white/10">
                        <div class="min-w-0 px-3 py-3 lg:px-3.5"><div class="truncate text-[11px] text-brand-100">Gross block</div><div class="mt-0.5 font-display text-[15px] font-bold tnum lg:text-[17px]">{{ inr_short($cost) }}</div></div>
                        <a href="{{ $currentFy ? route('reports.show', ['asset-movement', 'from' => $currentFy->start_date->toDateString(), 'to' => $currentFy->end_date->toDateString()]) : route('reports.index') }}" class="min-w-0 px-3 py-3 hover:bg-white/[.05] lg:px-3.5"><div class="truncate text-[11px] text-brand-100"><span class="sm:hidden">Added FY</span><span class="hidden sm:inline">Additions {{ $fyShort ? 'FY '.$fyShort : 'this FY' }}</span></div><div class="mt-0.5 font-display text-[15px] font-bold tnum lg:text-[17px]">{{ inr_short($additionsFy->cost ?? 0) }}</div></a>
                        <a href="{{ route('assets.index') }}" class="min-w-0 px-3 py-3 hover:bg-white/[.05] lg:px-3.5"><div class="truncate text-[11px] text-brand-100">Assets in use</div><div class="mt-0.5 font-display text-[15px] font-bold tnum lg:text-[17px]">{{ number_format($totals->n) }}</div></a>
                    </div>
                </div>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:col-span-7">
            @foreach ($kpis as [$label, $value, $sub, $ic, $tint, $url, $kind, $series, $color])
                <a href="{{ $url }}" class="rounded-2xl border border-line bg-card p-3.5 shadow-card transition hover:shadow-lift sm:p-4">
                    <div class="flex items-center gap-2 sm:gap-2.5"><span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg sm:h-8 sm:w-8 {{ $tint }}"><x-icon :name="$ic" class="h-4 w-4 sm:h-[17px] sm:w-[17px]"/></span><span class="truncate text-[11.5px] font-medium text-ink2 sm:text-[12.5px]">{{ $label }}</span></div>
                    <div class="mt-2.5 flex items-end justify-between gap-3 sm:mt-3">
                        <div class="min-w-0"><div class="font-display text-[21px] font-bold leading-none tracking-tight text-ink tnum sm:text-[26px]">{{ $value }}</div><div class="mt-1.5 truncate text-[11.5px] text-ink3 sm:mt-2 sm:text-[12px]">{{ $sub }}</div></div>
                        <div class="hidden shrink-0 pb-0.5 sm:block">
                            @if ($kind === 'bars')<x-bars :values="$series" :color="$color"/>
                            @elseif ($kind === 'spark')<x-spark :values="$series" :color="$color"/>
                            @else
                                <svg width="38" height="38" viewBox="0 0 36 36" class="-rotate-90" aria-hidden="true"><circle cx="18" cy="18" r="14" fill="none" style="stroke: rgb(var(--line))" stroke-width="5"/><circle cx="18" cy="18" r="14" fill="none" stroke="{{ $color }}" stroke-width="5" stroke-linecap="round" stroke-dasharray="{{ sprintf('%.1f', 87.96 * $series) }} 87.96"/></svg>
                            @endif
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </div>

    {{-- Quick actions --}}
    @if ($qa)
        <section class="mt-5 rounded-2xl border border-line bg-card p-4 shadow-card lg:mt-6 lg:border-0 lg:bg-transparent lg:p-0 lg:shadow-none">
            <h2 class="mb-3 text-[13px] font-semibold text-ink lg:mb-2.5 lg:uppercase lg:tracking-[.06em] lg:text-ink3">Quick actions</h2>
            <div class="grid grid-cols-4 gap-2 lg:hidden">
                @foreach (array_slice($qa, 0, 4) as [$ic, $l, $t, $url])
                    <a href="{{ $url }}" class="flex flex-col items-center gap-1.5"><span class="grid h-12 w-12 place-items-center rounded-2xl {{ $t }}"><x-icon :name="$ic" class="h-[22px] w-[22px]"/></span><span class="text-center text-[11px] font-medium leading-tight text-ink2">{{ $l }}</span></a>
                @endforeach
            </div>
            <div class="hidden gap-3 lg:grid lg:grid-cols-3 2xl:grid-cols-6">
                @foreach ($qa as [$ic, $l, $t, $url])
                    <a href="{{ $url }}" class="flex items-center gap-2.5 rounded-xl border border-line bg-card px-3 py-2.5 text-[13px] font-semibold text-ink shadow-card transition hover:border-brand-300 hover:shadow-lift"><span class="grid h-8 w-8 place-items-center rounded-lg {{ $t }}"><x-icon :name="$ic" class="h-4 w-4"/></span>{{ $l }}</a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Category + location --}}
    <div class="mt-5 grid gap-4 lg:mt-6 xl:grid-cols-12">
        <div class="card hidden sm:block xl:col-span-6">
            <div class="flex items-center justify-between px-5 pt-4">
                <div><h3 class="font-display text-[15px] font-bold text-ink">Gross block by category</h3><p class="text-[12px] text-ink3">Original cost of assets in use</p></div>
                <a href="{{ route('reports.show', 'assets-by-category') }}" class="flex items-center gap-1 text-[12.5px] font-semibold text-brand-600 dark:text-brand-300">Report <x-icon name="arrow-ur" class="h-3.5 w-3.5"/></a>
            </div>
            @if (count($cat['labels']))
                <div class="flex items-center gap-5 p-5 pt-3">
                    <div class="relative h-[190px] w-[190px] shrink-0">
                        <canvas id="chartCategory" aria-label="Gross block by category"></canvas>
                        <div class="pointer-events-none absolute inset-0 grid place-items-center text-center"><div><div class="text-[11px] text-ink3">Gross block</div><div class="font-display text-[19px] font-bold text-ink tnum">{{ inr_short($catTotal) }}</div><div class="text-[11px] text-ink3">{{ count($cat['labels']) }} {{ \Illuminate\Support\Str::plural('category', count($cat['labels'])) }}</div></div></div>
                    </div>
                    <ul class="min-w-0 flex-1">
                        @foreach ($cat['labels'] as $i => $label)
                            @if ($i < 6)
                                <li class="flex items-center gap-2.5 py-[5px]"><span class="h-2.5 w-2.5 shrink-0 rounded-[3px]" style="background: {{ $cat['colors'][$i] ?? '#94a3b8' }}"></span><span class="min-w-0 flex-1 truncate text-[12.5px] text-ink2" title="{{ $label }} ({{ $cat['counts'][$i] }})">{{ $label }}</span><span class="text-[12.5px] font-semibold text-ink tnum">{{ inr_short($cat['values'][$i]) }}</span><span class="w-10 text-right text-[11.5px] text-ink3 tnum">{{ number_format($cat['values'][$i] * 100 / $catTotal, 0) }}%</span></li>
                            @endif
                        @endforeach
                        @if (count($cat['labels']) > 6)
                            @php $rest = collect($cat['values'])->slice(6)->sum(); @endphp
                            <li class="flex items-center gap-2.5 py-[5px]"><span class="h-2.5 w-2.5 shrink-0 rounded-[3px] bg-slate-300"></span><span class="min-w-0 flex-1 truncate text-[12.5px] text-ink2">{{ count($cat['labels']) - 6 }} more</span><span class="text-[12.5px] font-semibold text-ink tnum">{{ inr_short($rest) }}</span><span class="w-10 text-right text-[11.5px] text-ink3 tnum">{{ number_format($rest * 100 / $catTotal, 0) }}%</span></li>
                        @endif
                    </ul>
                </div>
            @else
                <p class="px-5 pb-6 pt-4 text-sm text-ink3">No assets in use yet.</p>
            @endif
        </div>
        <div class="card hidden sm:block xl:col-span-6">
            <div class="flex flex-wrap items-center justify-between gap-2 px-5 pt-4">
                <div><h3 class="font-display text-[15px] font-bold text-ink">Cost vs net book value by location</h3><p class="text-[12px] text-ink3">Assets in use{{ $asOf ? ' · as of '.fmt_date($asOf) : '' }}</p></div>
                <div class="flex items-center gap-3 text-[11.5px] text-ink2"><span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-[3px] bg-brand-200 dark:bg-brand-400/40"></span>Gross cost</span><span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-[3px] bg-teal-500"></span>Net book value</span></div>
            </div>
            <div class="px-4 pb-4 pt-2" style="height: {{ max(218, 40 + count($charts['location']['labels']) * 34) }}px"><canvas id="chartLocation" aria-label="Cost vs NBV by location"></canvas></div>
        </div>
    </div>

    {{-- Depreciation trend + upcoming maintenance --}}
    <div class="mt-4 grid gap-4 xl:grid-cols-12">
        <div class="card xl:col-span-7">
            <div class="flex items-center justify-between px-4 pt-4 sm:px-5">
                <div><h3 class="font-display text-[15px] font-bold text-ink">Monthly depreciation</h3><p class="hidden text-[12px] text-ink3 sm:block">Last 12 months{{ $fyShort ? ' · FY '.$fyShort.' YTD' : '' }} <b class="font-semibold text-ink">{{ inr_short($fyDep) }}</b></p></div>
                <a href="{{ route('depreciation.index') }}" class="flex items-center gap-1 text-[12.5px] font-semibold text-brand-600 dark:text-brand-300">All runs <x-icon name="arrow-ur" class="h-3.5 w-3.5"/></a>
            </div>
            <div class="h-[170px] px-3 pb-3 pt-2 sm:h-[236px] sm:px-4 sm:pb-4 sm:pt-3"><canvas id="chartTrend" aria-label="Monthly depreciation"></canvas></div>
        </div>
        <div class="card xl:col-span-5">
            <div class="flex items-center justify-between px-4 pb-2 pt-4 sm:px-5">
                <div><h3 class="font-display text-[15px] font-bold text-ink">Upcoming maintenance</h3><p class="text-[12px] text-ink3">{{ $pmOverdue }} overdue · {{ $pmDue30 }} in the next 30 days</p></div>
                <a href="{{ route('pm-schedules.index', ['view' => 'due30']) }}" class="flex items-center gap-1 text-[12.5px] font-semibold text-brand-600 dark:text-brand-300">All <x-icon name="arrow-ur" class="h-3.5 w-3.5"/></a>
            </div>
            <ul class="divide-y divide-line">
                @forelse ($upcomingPm->take(5) as $pm)
                    @php $late = $pm->next_due_date->lt(today()); $days = (int) today()->diffInDays($pm->next_due_date, false); @endphp
                    <li class="flex items-start gap-3 px-4 py-3 hover:bg-soft/60 sm:px-5">
                        <x-cat-icon :category="$pm->asset?->category" size="h-8 w-8" icon-size="h-4 w-4" rounded="rounded-full"/>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-[13px] font-semibold text-ink" title="{{ $pm->title }}">{{ $pm->title }}</div>
                            <a href="{{ route('assets.show', $pm->asset_id) }}" class="block truncate text-[12px] text-ink3 hover:text-brand-600">{{ $pm->asset?->asset_code }} · {{ $pm->asset?->name }}</a>
                        </div>
                        <span class="shrink-0 whitespace-nowrap rounded-md px-2 py-0.5 text-[11px] font-semibold {{ $late ? 'bg-rose-500/10 text-rose-600 dark:text-rose-300' : 'bg-soft text-ink2' }}">{{ $late ? 'Overdue '.abs($days).' d' : ($days === 0 ? 'Due today' : 'Due '.$pm->next_due_date->format('d M')) }}</span>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-sm text-ink3">Nothing due in the next 30 days.</li>
                @endforelse
            </ul>
        </div>
    </div>

    {{-- End of life + recent --}}
    <div class="mt-4 grid gap-4 xl:grid-cols-2">
        <div class="card">
            <div class="flex items-center justify-between px-4 pb-2 pt-4 sm:px-5"><div><h3 class="font-display text-[15px] font-bold text-ink">Reaching end of useful life</h3><p class="text-[12px] text-ink3">Next 12 months</p></div></div>
            <ul class="divide-y divide-line">
                @forelse ($endingLife->take(5) as $a)
                    <li class="flex items-center gap-3 px-4 py-2.5 sm:px-5">
                        <x-cat-icon :category="$a->category" size="h-8 w-8" icon-size="h-4 w-4" rounded="rounded-lg"/>
                        <div class="min-w-0 flex-1"><a href="{{ route('assets.show', $a) }}" class="block truncate text-[13px] font-semibold text-ink hover:text-brand-600" title="{{ $a->name }}">{{ $a->name }}</a><div class="truncate text-[12px] text-ink3"><span class="font-mono">{{ $a->asset_code }}</span> · NBV {{ inr($a->current_book_value, 0) }}</div></div>
                        <div class="shrink-0 text-right text-[12px]"><div class="font-semibold text-ink2">{{ fmt_date($a->eol) }}</div><div class="text-ink3">{{ max(0, (int) now()->diffInMonths(\Illuminate\Support\Carbon::parse($a->eol))) }} mo left</div></div>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-sm text-ink3">No assets reach end of life in the next 12 months.</li>
                @endforelse
            </ul>
        </div>
        <div class="card">
            <div class="flex items-center justify-between px-4 pb-2 pt-4 sm:px-5"><div><h3 class="font-display text-[15px] font-bold text-ink">Recently added</h3><p class="text-[12px] text-ink3">Latest entries in the register</p></div><a href="{{ route('assets.index') }}" class="flex items-center gap-1 text-[12.5px] font-semibold text-brand-600 dark:text-brand-300">Register <x-icon name="arrow-ur" class="h-3.5 w-3.5"/></a></div>
            <ul class="divide-y divide-line">
                @forelse ($recent->take(5) as $a)
                    <li class="flex items-center gap-3 px-4 py-2.5 sm:px-5">
                        <x-cat-icon :category="$a->category" size="h-8 w-8" icon-size="h-4 w-4" rounded="rounded-lg"/>
                        <div class="min-w-0 flex-1"><a href="{{ route('assets.show', $a) }}" class="block truncate text-[13px] font-semibold text-ink hover:text-brand-600" title="{{ $a->name }}">{{ $a->name }}</a><div class="truncate text-[12px] text-ink3"><span class="font-mono">{{ $a->asset_code }}</span>{{ $a->location ? ' · '.$a->location->name : '' }}</div></div>
                        <div class="shrink-0 text-right text-[12.5px] font-semibold text-ink tnum">{{ inr_short($a->original_cost) }}</div>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-sm text-ink3">No assets yet.</li>
                @endforelse
            </ul>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (!window.apChart) return;
                const d = @json($charts);
                const fyKeys = @json($fyMonths->map(fn ($m) => $m['label'])->values());
                const fyStart = @json($currentFy?->start_date?->format('Y-m'));
                const trendYm = @json(collect(range(0, 11))->map(fn ($i) => \Illuminate\Support\Carbon::parse($lastRun?->period_end ?? now())->endOfMonth()->subMonthsNoOverflow(11 - $i)->format('Y-m')));
                const T = () => window.apTheme;
                const cat = document.getElementById('chartCategory');
                if (cat) window.apChart(() => new Chart(cat, {
                    type: 'doughnut',
                    data: { labels: d.category.labels, datasets: [{ data: d.category.values, backgroundColor: d.category.colors, borderWidth: 3, borderColor: T().css('--card'), borderRadius: 4, spacing: 1, hoverOffset: 4 }] },
                    options: { cutout: '72%', plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => ` ${c.label}: ${window.inr(c.parsed, true)} (${d.category.counts[c.dataIndex]} assets)` } } } },
                }));
                const loc = document.getElementById('chartLocation');
                if (loc) window.apChart(() => new Chart(loc, {
                    type: 'bar',
                    data: { labels: d.location.labels, datasets: [
                        { label: 'Gross cost', data: d.location.cost, backgroundColor: T().isDark() ? 'rgba(123,125,244,.35)' : '#C6CAFE', borderRadius: 5, barThickness: 11 },
                        { label: 'Net book value', data: d.location.nbv, backgroundColor: '#14B8A6', borderRadius: 5, barThickness: 11 },
                    ] },
                    options: { indexAxis: 'y', plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => ` ${c.dataset.label}: ${window.inr(c.parsed.x)}` } } },
                        scales: { x: { beginAtZero: true, grid: { color: T().css('--line') }, border: { display: false }, ticks: { maxTicksLimit: 6, callback: (v) => window.inr(v, true) } },
                                  y: { grid: { display: false }, border: { display: false }, ticks: { color: T().css('--ink2'), font: { size: 12 }, callback: function (v) { const l = this.getLabelForValue(v); return l.length > 26 ? l.slice(0, 25) + '…' : l; } } } } },
                }));
                const dep = document.getElementById('chartTrend');
                if (dep) window.apChart(() => {
                    const dark = T().isDark();
                    const g = dep.getContext('2d').createLinearGradient(0, 0, 0, 240); g.addColorStop(0, '#4840D6'); g.addColorStop(1, '#7B7DF4');
                    const n = d.trend.values.length;
                    const small = dep.clientWidth < 520;
                    return new Chart(dep, {
                        type: 'bar',
                        data: { labels: d.trend.labels, datasets: [{ label: 'Depreciation', data: d.trend.values, borderRadius: 6, barPercentage: .68,
                            backgroundColor: (c) => c.dataIndex === n - 1 ? g : (fyStart && trendYm[c.dataIndex] >= fyStart ? (dark ? 'rgba(123,125,244,.45)' : '#C6CAFE') : (dark ? 'rgba(255,255,255,.08)' : '#E6E8F0')) }] },
                        options: { plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => ' ' + window.inr(c.parsed.y) } } },
                            scales: { y: { beginAtZero: true, grid: { color: T().css('--line') }, border: { display: false }, ticks: { maxTicksLimit: 5, callback: (v) => window.inr(v, true) } },
                                      x: { grid: { display: false }, border: { display: false }, ticks: { maxRotation: 0, autoSkip: small, maxTicksLimit: small ? 6 : 12 } } } },
                    });
                });
            });
        </script>
    @endpush
</x-app-layout>
