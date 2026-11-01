@php
    $canWrite = auth()->user()->canManageAssets();
    $sortLink = function ($col, $label, $right = false) use ($sort, $dir) {
        $next = $sort === $col && $dir === 'asc' ? 'desc' : 'asc';
        $on = $sort === $col;
        $arrow = $on ? '<svg class="h-3 w-3 '.($dir === 'desc' ? '' : 'rotate-180').'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>' : '';
        return '<a href="'.e(request()->fullUrlWithQuery(['sort' => $col, 'dir' => $next, 'page' => null])).'" class="inline-flex items-center gap-1 hover:text-ink '.($on ? 'text-ink2' : '').'">'.e($label).$arrow.'</a>';
    };
    $filterDefs = ['category' => ['Category', $lookups['categories']], 'location' => ['Location', $lookups['locations']], 'department' => ['Department', $lookups['departments']], 'cost_center' => ['Cost centre', $lookups['costCenters']], 'status' => ['Status', $lookups['statuses']]];
    $activeChips = [];
    foreach ($filterDefs as $param => [$label, $opts]) {
        if (request()->filled($param)) {
            $activeChips[] = [$label, $opts[request($param)] ?? request($param), [$param]];
        }
    }
    if (request()->filled('from') || request()->filled('to')) {
        $activeChips[] = ['Put to use', trim((request('from') ? fmt_date(request('from')) : '…').' – '.(request('to') ? fmt_date(request('to')) : '…')), ['from', 'to']];
    }
    if (request()->filled('q')) {
        $activeChips[] = ['Search', '“'.request('q').'”', ['q']];
    }
    $without = fn (array $keys) => request()->fullUrlWithQuery(array_fill_keys(array_merge($keys, ['page']), null));
    $closedIds = [\App\Models\AssetStatus::idFor(\App\Models\AssetStatus::DISPOSED), \App\Models\AssetStatus::idFor(\App\Models\AssetStatus::SCRAPPED)];
    $pageIds = $inactive ? [] : $assets->pluck('id')->all();
    $blocked = $assets->filter(fn ($a) => in_array($a->asset_status_id, $closedIds, true))->pluck('id')->values()->all();
    $nbvMap = $assets->mapWithKeys(fn ($a) => [$a->id => (float) $a->current_book_value]);
    $pct = fn ($a) => (float) $a->original_cost > 0 ? max(0, min(100, (float) $a->current_book_value / (float) $a->original_cost * 100)) : 0;
    $barColor = fn ($p) => $p > 50 ? 'bg-emerald-500' : ($p > 15 ? 'bg-amber-400' : 'bg-rose-400');
    $statusPill = fn ($s) => $s ? '<span class="badge bg-'.$s->color.'-500/10 text-'.$s->color.'-700 ring-'.$s->color.'-600/20 dark:text-'.$s->color.'-300"><span class="h-1.5 w-1.5 rounded-full bg-'.$s->color.'-500"></span>'.e($s->name).'</span>' : '';
    $exportQuery = array_filter(['report' => 'asset-register', 'q' => request('q'), 'category' => request('category'), 'location' => request('location'), 'department' => request('department'), 'cost_center' => request('cost_center'), 'status' => request('status'), 'cap_from' => request('from'), 'cap_to' => request('to')]);
    $accPct = (float) $totals->cost > 0 ? (float) $totals->acc / (float) $totals->cost * 100 : 0;
@endphp
<x-app-layout title="Asset register">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <nav class="hidden items-center gap-1.5 text-[12.5px] text-ink3 lg:flex">Assets <x-icon name="chev-right" class="h-3.5 w-3.5"/> <span class="text-ink2">Register</span></nav>
            <h1 class="font-display text-[23px] font-bold tracking-tight text-ink lg:mt-1 lg:text-[26px]">Asset register</h1>
            <p class="mt-0.5 hidden text-[13.5px] text-ink2 lg:block">Every capitalised asset with cost, accumulated depreciation and net book value.</p>
            <p class="text-[12.5px] text-ink3 lg:hidden">{{ number_format($totals->n) }} in view · NBV <b class="font-semibold text-ink">{{ inr_short($totals->nbv) }}</b> of {{ inr_short($totals->cost) }}</p>
        </div>
        <div class="flex items-center gap-2">
            <div x-data="{ open: false }" class="relative hidden sm:block">
                <button type="button" @click="open = !open" class="btn btn-secondary h-10 rounded-xl"><x-icon name="download" class="h-4 w-4"/> Export <x-icon name="chev-down" class="h-3.5 w-3.5 text-ink3"/></button>
                <div x-cloak x-show="open" @click.outside="open = false" x-transition.origin.top.right class="absolute right-0 z-30 mt-2 w-60 rounded-xl border border-line bg-card p-1.5 shadow-pop">
                    <a href="{{ route('reports.show', $exportQuery + ['export' => 'xlsx']) }}" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] text-ink2 hover:bg-soft"><x-icon name="file" class="h-4 w-4 text-emerald-600"/> Excel (.xlsx)</a>
                    <a href="{{ route('reports.show', $exportQuery + ['export' => 'pdf']) }}" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] text-ink2 hover:bg-soft"><x-icon name="file" class="h-4 w-4 text-rose-600"/> PDF</a>
                    <a href="{{ route('reports.show', $exportQuery) }}" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] text-ink2 hover:bg-soft"><x-icon name="chart" class="h-4 w-4 text-ink3"/> Open as report</a>
                    <div class="px-2.5 pb-1 pt-1.5 text-[11px] text-ink3">Uses the current search and filters.</div>
                </div>
            </div>
            @if ($canWrite)
                <a href="{{ route('assets.create') }}" class="btn btn-primary h-10 rounded-xl px-4"><x-icon name="plus" class="h-4 w-4" stroke="2.25"/> <span class="sm:hidden">Add</span><span class="hidden sm:inline">Add asset</span></a>
            @endif
        </div>
    </div>

    {{-- Summary strip --}}
    <div class="mt-5 hidden grid-cols-2 divide-line rounded-2xl border border-line bg-card shadow-card sm:grid xl:grid-cols-4 xl:divide-x">
        @foreach ([
            ['Assets', number_format($totals->n), $inactive ? 'inactive' : ($tab === 'all' ? 'on the register' : 'in this view'), 'package', 'brand'],
            ['Gross block', inr_short($totals->cost), 'original cost', 'layers', 'sky'],
            ['Accumulated dep.', inr_short($totals->acc), number_format($accPct, 1).'% of cost', 'calculator', 'violet'],
            ['Net book value', inr_short($totals->nbv), (float) $totals->cost > 0 ? number_format(100 - $accPct, 1).'% of cost' : '—', 'check-circle', 'emerald'],
        ] as [$l, $v, $s, $ic, $t])
            <div class="flex items-center gap-3.5 px-5 py-4">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-{{ $t }}-500/10 text-{{ $t }}-600 dark:text-{{ $t }}-300"><x-icon :name="$ic" class="h-5 w-5"/></span>
                <div class="min-w-0"><div class="text-[12px] font-medium text-ink3">{{ $l }}</div><div class="font-display text-[20px] font-bold leading-tight text-ink tnum">{{ $v }}</div></div>
                <div class="ml-auto text-right text-[11.5px] text-ink3">{{ $s }}</div>
            </div>
        @endforeach
    </div>

    <div class="mt-5 lg:rounded-2xl lg:border lg:border-line lg:bg-card lg:shadow-card"
         x-data="bulkSelect(@js($pageIds), { filters: {{ $filtered && ! count($activeChips) ? 'true' : 'false' }}, blocked: @js($blocked), nbv: @js($nbvMap) })">

        {{-- Status tabs (desktop: underline tabs; mobile: pills) --}}
        @unless ($inactive)
            <div class="no-sb -mx-4 flex gap-2 overflow-x-auto px-4 lg:mx-0 lg:gap-6 lg:border-b lg:border-line lg:px-5 lg:pt-4">
                @foreach (\App\Http\Controllers\AssetController::TABS as $key => $label)
                    @php $on = $tab === $key; @endphp
                    <a href="{{ request()->fullUrlWithQuery(['tab' => $key === 'all' ? null : $key, 'page' => null]) }}"
                       class="relative flex h-8 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full px-3 text-[12.5px] font-semibold lg:h-auto lg:rounded-none lg:px-1 lg:pb-3 lg:text-[13.5px] {{ $on ? 'bg-ink text-card lg:bg-transparent lg:text-brand-700 lg:dark:text-brand-200' : 'border border-line bg-card text-ink2 lg:border-0 lg:bg-transparent lg:font-medium lg:text-ink3 lg:hover:text-ink' }}">
                        {{ $label }}
                        <span class="rounded-full px-1.5 text-[11px] font-semibold tnum {{ $on ? 'text-card/70 lg:bg-brand-500/10 lg:text-brand-700 lg:dark:text-brand-200' : 'text-ink3 lg:bg-soft' }}">{{ number_format($tabCounts[$key]) }}</span>
                        @if ($on)<span class="absolute inset-x-0 -bottom-px hidden h-[2px] rounded-full bg-brand-600 lg:block"></span>@endif
                    </a>
                @endforeach
            </div>
        @endunless

        {{-- Search + filter chips --}}
        <form method="GET" class="mt-3 lg:mt-0 lg:px-5 lg:py-3" x-ref="filterForm">
            @foreach (['tab', 'show', 'sort', 'dir'] as $keep)
                @if (request()->filled($keep))<input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}">@endif
            @endforeach
            <div class="flex flex-wrap items-center gap-2">
                <label class="relative flex h-11 min-w-0 flex-1 items-center lg:h-9 lg:w-[260px] lg:flex-none">
                    <span class="pointer-events-none absolute left-3 text-ink3"><x-icon name="search" class="h-4 w-4"/></span>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Code, name, serial, invoice…" class="form-input h-full rounded-xl border-line bg-card pl-9 text-[14px] shadow-card lg:rounded-lg lg:bg-soft/60 lg:text-[13px] lg:shadow-none">
                </label>
                <button type="button" @click="filters = !filters" class="relative grid h-11 w-11 place-items-center rounded-xl border border-line bg-card text-ink2 shadow-card lg:hidden" aria-label="Filters">
                    <x-icon name="filter" class="h-5 w-5"/>
                    @if (count($activeChips))<span class="absolute -right-1 -top-1 grid h-[18px] w-[18px] place-items-center rounded-full bg-brand-600 text-[10px] font-bold text-white">{{ count($activeChips) }}</span>@endif
                </button>
                <div class="no-sb -mx-4 flex w-[calc(100%+2rem)] items-center gap-2 overflow-x-auto px-4 lg:mx-0 lg:w-auto lg:flex-1 lg:flex-wrap lg:overflow-visible lg:px-0">
                    @foreach ($activeChips as [$label, $val, $keys])
                        <a href="{{ $without($keys) }}" class="flex h-8 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg border border-brand-300 bg-brand-500/[.07] pl-2.5 pr-1.5 text-[12.5px] text-brand-700 dark:border-brand-500/40 dark:text-brand-200" title="Remove filter">
                            <span class="text-ink3">{{ $label }}</span><span class="max-w-[160px] truncate font-semibold">{{ $val }}</span>
                            <span class="grid h-5 w-5 place-items-center rounded-md hover:bg-brand-500/10"><x-icon name="x" class="h-3.5 w-3.5" stroke="2"/></span>
                        </a>
                    @endforeach
                    @foreach ($filterDefs as $param => [$label, $opts])
                        @unless (request()->filled($param))
                            <button type="button" @click="filters = true; $nextTick(() => $refs['f_{{ $param }}']?.focus())" class="hidden h-8 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg border border-dashed border-line bg-card px-2.5 text-[12.5px] font-medium text-ink2 hover:border-brand-300 lg:flex">
                                <x-icon name="plus" class="h-3.5 w-3.5"/>{{ $label }}
                            </button>
                        @endunless
                    @endforeach
                    @unless (request()->filled('from') || request()->filled('to'))
                        <button type="button" @click="filters = true; $nextTick(() => $refs.f_from?.focus())" class="hidden h-8 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg border border-dashed border-line bg-card px-2.5 text-[12.5px] font-medium text-ink2 hover:border-brand-300 lg:flex"><x-icon name="plus" class="h-3.5 w-3.5"/>Date</button>
                    @endunless
                    @if (count($activeChips))
                        <a href="{{ route('assets.index', array_filter(['tab' => request('tab'), 'show' => request('show')])) }}" class="ml-1 shrink-0 whitespace-nowrap text-[12.5px] font-semibold text-ink3 hover:text-ink">Clear</a>
                    @endif
                </div>
                <div class="ml-auto hidden items-center gap-1 lg:flex">
                    <div class="flex rounded-lg border border-line p-0.5 text-[12px] font-medium">
                        <a href="{{ request()->fullUrlWithQuery(['show' => null, 'page' => null]) }}" class="rounded-md px-2.5 py-1 {{ ! $inactive ? 'bg-soft text-ink' : 'text-ink3 hover:text-ink' }}">Active</a>
                        <a href="{{ request()->fullUrlWithQuery(['show' => 'inactive', 'tab' => null, 'page' => null]) }}" class="rounded-md px-2.5 py-1 {{ $inactive ? 'bg-soft text-ink' : 'text-ink3 hover:text-ink' }}">Deleted</a>
                    </div>
                </div>
            </div>
            <div x-show="filters" x-cloak x-transition class="mt-3 rounded-2xl border border-line bg-card p-4 shadow-card lg:shadow-none">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                    @foreach ($filterDefs as $param => [$label, $opts])
                        <div>
                            <label class="form-label text-xs">{{ $label }}</label>
                            <select name="{{ $param }}" x-ref="f_{{ $param }}" class="form-input py-1.5 text-sm">
                                <option value="">All</option>
                                @foreach ($opts as $id => $name)<option value="{{ $id }}" @selected((string) request($param) === (string) $id)>{{ $name }}</option>@endforeach
                            </select>
                        </div>
                    @endforeach
                    <div><label class="form-label text-xs">Put to use from</label><input type="date" name="from" x-ref="f_from" value="{{ request('from') }}" class="form-input py-1.5 text-sm"></div>
                    <div><label class="form-label text-xs">to</label><input type="date" name="to" value="{{ request('to') }}" class="form-input py-1.5 text-sm"></div>
                </div>
                <div class="mt-3 flex items-center justify-between gap-2">
                    <a href="{{ request()->fullUrlWithQuery(['show' => $inactive ? null : 'inactive', 'tab' => null, 'page' => null]) }}" class="text-[12.5px] font-medium text-ink3 hover:text-ink lg:hidden">{{ $inactive ? 'Show active assets' : 'Show deleted assets' }}</a>
                    <div class="ml-auto flex gap-2">
                        <button type="button" class="btn btn-ghost btn-sm" @click="filters = false">Close</button>
                        <button class="btn btn-primary btn-sm">Apply filters</button>
                    </div>
                </div>
            </div>
        </form>

        @if ($inactive)
            <div class="mx-0 mt-3 flex items-center gap-2 rounded-xl bg-amber-500/10 px-4 py-2 text-[12.5px] text-amber-700 dark:text-amber-300 lg:mx-5 lg:mt-0 lg:mb-3"><x-icon name="info" class="h-4 w-4"/> Showing deleted assets. Restore one to bring it back to the register.</div>
        @endif

        {{-- Bulk action bar --}}
        <div x-show="count > 0" x-cloak x-transition.opacity
             class="sticky top-[60px] z-20 mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl border border-brand-200 bg-brand-50 px-4 py-2 text-[12.5px] shadow-card dark:border-brand-500/30 dark:bg-[#1b1d3a] lg:top-16 lg:mt-0 lg:rounded-none lg:border-x-0 lg:shadow-none">
            <span class="font-semibold text-brand-800 dark:text-brand-100"><span x-text="count"></span> selected</span>
            <span class="hidden text-brand-700/80 dark:text-brand-200/80 sm:inline">· NBV <span class="tnum" x-text="inr(selNbv)"></span></span>
            <span class="mx-1 hidden h-4 w-px bg-brand-200 dark:bg-brand-500/30 sm:block"></span>
            @if ($canWrite)
                <template x-if="!hasBlocked">
                    <a :href="url('{{ route('transfers.create') }}', 'assets[]')" class="flex h-7 items-center gap-1.5 rounded-md px-2 font-semibold text-brand-700 hover:bg-white/70 dark:text-brand-200 dark:hover:bg-white/5"><x-icon name="transfer" class="h-3.5 w-3.5"/>Transfer</a>
                </template>
                <template x-if="hasBlocked">
                    <span class="flex h-7 cursor-not-allowed items-center gap-1.5 rounded-md px-2 font-semibold text-brand-700/40 dark:text-brand-200/40" title="Disposed or scrapped assets cannot be transferred"><x-icon name="transfer" class="h-3.5 w-3.5"/>Transfer</span>
                </template>
            @endif
            <a :href="url('{{ route('assets.labels') }}')" target="_blank" rel="noopener" class="flex h-7 items-center gap-1.5 rounded-md px-2 font-semibold text-brand-700 hover:bg-white/70 dark:text-brand-200 dark:hover:bg-white/5"><x-icon name="printer" class="h-3.5 w-3.5"/>Print QR labels</a>
            <a :href="exportUrl('{{ route('reports.show', ['report' => 'asset-register', 'export' => 'xlsx']) }}')" class="flex h-7 items-center gap-1.5 rounded-md px-2 font-semibold text-brand-700 hover:bg-white/70 dark:text-brand-200 dark:hover:bg-white/5"><x-icon name="download" class="h-3.5 w-3.5"/>Export</a>
            <button type="button" @click="clear()" class="ml-auto font-semibold text-brand-700 dark:text-brand-200">Deselect</button>
        </div>

        {{-- Desktop table --}}
        <table class="hidden w-full table-fixed text-[13px] lg:table">
            <colgroup><col class="w-[44px]"><col><col class="w-[18%]"><col class="w-[136px]"><col class="w-[120px]"><col class="w-[140px]"><col class="w-[150px]"><col class="w-[96px]"></colgroup>
            <thead>
            <tr class="text-left">
                @php $th = 'sticky top-16 z-10 border-y border-line bg-soft/95 py-2.5 text-[11.5px] font-semibold uppercase tracking-[.05em] text-ink3 backdrop-blur'; @endphp
                <th class="{{ $th }} pl-5 pr-2">
                    @unless ($inactive)<input type="checkbox" class="form-check" :checked="allOnPage" @change="toggleAll()" aria-label="Select all on this page">@endunless
                </th>
                <th class="{{ $th }} pr-4">{!! $sortLink('name', 'Asset') !!} <span class="text-ink3/60">/</span> {!! $sortLink('asset_code', 'Code') !!}</th>
                <th class="{{ $th }} pr-4">Location / Dept.</th>
                <th class="{{ $th }} pr-4 text-right">{!! $sortLink('original_cost', 'Cost') !!}</th>
                <th class="{{ $th }} pr-4 text-right">{!! $sortLink('accumulated_depreciation', 'Acc. dep.') !!}</th>
                <th class="{{ $th }} pr-4 text-right">{!! $sortLink('current_book_value', 'Net book value') !!}</th>
                <th class="{{ $th }} pr-2">Status</th>
                <th class="{{ $th }} pr-4"></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($assets as $a)
                @php $p = $pct($a); @endphp
                <tr class="group border-b border-line last:border-0 hover:bg-brand-500/[.04] {{ $a->trashed() ? 'opacity-60' : '' }}" :class="ids.includes({{ $a->id }}) && 'bg-brand-500/[.05]'">
                    <td class="py-3 pl-5 pr-2">@unless ($a->trashed())<input type="checkbox" class="form-check" value="{{ $a->id }}" x-model.number="ids" aria-label="Select {{ $a->asset_code }}">@endunless</td>
                    <td class="py-3 pr-4">
                        <a href="{{ route('assets.show', $a) }}" class="flex min-w-0 items-center gap-3">
                            <x-cat-icon :category="$a->category"/>
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-medium text-ink group-hover:text-brand-700 dark:group-hover:text-brand-200">{{ $a->name }}</div>
                                <div class="mt-0.5 flex items-center gap-1.5 text-[12px] text-ink3">
                                    <span class="shrink-0 whitespace-nowrap font-mono text-[11.5px] font-medium text-brand-600 dark:text-brand-300">{{ $a->asset_code }}</span>
                                    @if ($sub = trim(($a->manufacturer ?? '').' '.($a->model ?? '')) ?: $a->category?->name)<span>·</span><span class="truncate">{{ $sub }}</span>@endif
                                </div>
                            </div>
                        </a>
                    </td>
                    <td class="py-3 pr-4"><div class="truncate text-ink">{{ $a->location?->name ?? '—' }}</div><div class="truncate text-[12px] text-ink3">{{ $a->department?->name }}</div></td>
                    <td class="whitespace-nowrap py-3 pr-4 text-right"><div class="text-ink tnum">{{ inr($a->original_cost) }}</div><div class="mt-0.5 text-[11.5px] text-ink3 tnum">PTU {{ fmt_date($a->capitalization_date) }}</div></td>
                    <td class="whitespace-nowrap py-3 pr-4 text-right text-ink2 tnum">{{ inr($a->accumulated_depreciation) }}</td>
                    <td class="whitespace-nowrap py-3 pr-4 text-right">
                        <div class="font-semibold text-ink tnum">{{ inr($a->current_book_value) }}</div>
                        <div class="mt-1 flex items-center justify-end gap-1.5" title="Net book value as % of cost"><span class="text-[10.5px] text-ink3 tnum">{{ number_format($p) }}%</span><div class="h-1 w-14 overflow-hidden rounded-full bg-soft"><div class="h-full rounded-full {{ $barColor($p) }}" style="width: {{ round($p) }}%"></div></div></div>
                    </td>
                    <td class="py-3 pr-2">{!! $statusPill($a->status) !!}</td>
                    <td class="py-3 pr-4">
                        <div class="flex items-center justify-end gap-0.5 opacity-60 transition group-hover:opacity-100">
                            @if ($a->trashed())
                                @if ($canWrite)
                                    <form method="POST" action="{{ route('assets.restore', $a->id) }}" class="inline">@csrf @method('PATCH')<button class="btn btn-secondary btn-sm"><x-icon name="restore" class="h-3.5 w-3.5"/> Restore</button></form>
                                @endif
                            @else
                                <a href="{{ route('assets.show', $a) }}" class="grid h-7 w-7 place-items-center rounded-lg text-ink2 hover:bg-card hover:shadow-card" title="View"><x-icon name="eye" class="h-4 w-4"/></a>
                                <a href="{{ route('assets.label', $a) }}" target="_blank" rel="noopener" class="grid h-7 w-7 place-items-center rounded-lg text-ink2 hover:bg-card hover:shadow-card" title="QR label"><x-icon name="qr" class="h-4 w-4"/></a>
                                @if ($canWrite)<a href="{{ route('assets.edit', $a) }}" class="grid h-7 w-7 place-items-center rounded-lg text-ink2 hover:bg-card hover:shadow-card" title="Edit"><x-icon name="pencil" class="h-4 w-4"/></a>@endif
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="py-14 text-center">
                    <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-soft text-ink3"><x-icon name="cube"/></div>
                    <div class="mt-3 text-sm font-medium text-ink">No assets found</div>
                    <div class="text-xs text-ink3">{{ $filtered || $tab !== 'all' ? 'Adjust the tab, filters or search.' : 'Add your first asset to start the register.' }}</div>
                </td></tr>
            @endforelse
            </tbody>
            @if ($assets->count())
                <tfoot>
                <tr class="border-t-2 border-line bg-soft/60 text-[12.5px]">
                    <td></td>
                    <td class="py-3 pr-4 font-semibold text-ink">Total · {{ number_format($totals->n) }} {{ \Illuminate\Support\Str::plural('asset', $totals->n) }} in view</td>
                    <td></td>
                    <td class="py-3 pr-4 text-right font-semibold text-ink tnum">{{ inr($totals->cost) }}</td>
                    <td class="py-3 pr-4 text-right font-semibold text-ink2 tnum">{{ inr($totals->acc) }}</td>
                    <td class="py-3 pr-4 text-right font-bold text-ink tnum">{{ inr($totals->nbv) }}</td>
                    <td colspan="2"></td>
                </tr>
                </tfoot>
            @endif
        </table>

        {{-- Mobile cards --}}
        <div class="mt-3 space-y-3 lg:hidden">
            @forelse ($assets as $a)
                @php $p = $pct($a); @endphp
                <div class="relative rounded-2xl border border-line bg-card p-3.5 shadow-card {{ $a->trashed() ? 'opacity-60' : '' }}" :class="ids.includes({{ $a->id }}) && 'ring-2 ring-brand-500/40'">
                    <a href="{{ route('assets.show', $a) }}" class="block active:scale-[.99]">
                        <div class="flex items-start gap-3">
                            <x-cat-icon :category="$a->category" size="h-11 w-11" icon-size="h-5 w-5"/>
                            <div class="min-w-0 flex-1 pr-6">
                                <div class="line-clamp-2 text-[14px] font-semibold leading-snug text-ink">{{ $a->name }}</div>
                                <div class="mt-1 flex items-center gap-1.5 text-[12px] text-ink3"><span class="font-mono text-[11.5px] font-medium text-brand-600 dark:text-brand-300">{{ $a->asset_code }}</span>@if ($a->location)<span>·</span><span class="truncate">{{ $a->location->name }}</span>@endif</div>
                            </div>
                        </div>
                        <div class="mt-3 flex items-end justify-between gap-3 border-t border-dashed border-line pt-3">
                            <div><div class="text-[11px] text-ink3">Net book value</div><div class="font-display text-[16px] font-bold text-ink tnum">{{ inr($a->current_book_value) }}</div></div>
                            <div class="max-w-[110px] flex-1 pb-1"><div class="mb-1 text-[10.5px] text-ink3">of {{ inr_short($a->original_cost) }}</div><div class="h-1.5 overflow-hidden rounded-full bg-soft"><div class="h-full rounded-full {{ $barColor($p) }}" style="width: {{ round($p) }}%"></div></div></div>
                            <div class="pb-0.5">{!! $statusPill($a->status) !!}</div>
                        </div>
                    </a>
                    @unless ($a->trashed())
                        <label class="absolute right-2.5 top-2.5 grid h-8 w-8 place-items-center"><input type="checkbox" class="form-check" value="{{ $a->id }}" x-model.number="ids" aria-label="Select {{ $a->asset_code }}"></label>
                    @else
                        @if ($canWrite)
                            <form method="POST" action="{{ route('assets.restore', $a->id) }}" class="mt-3">@csrf @method('PATCH')<button class="btn btn-secondary btn-sm w-full"><x-icon name="restore" class="h-3.5 w-3.5"/> Restore</button></form>
                        @endif
                    @endunless
                </div>
            @empty
                <div class="rounded-2xl border border-line bg-card px-4 py-12 text-center shadow-card">
                    <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-soft text-ink3"><x-icon name="cube"/></div>
                    <div class="mt-3 text-sm font-medium text-ink">No assets found</div>
                </div>
            @endforelse
            @if ($assets->count())
                <div class="rounded-2xl border border-line bg-soft/60 px-4 py-3 text-[12.5px]">
                    <div class="font-semibold text-ink">Total · {{ number_format($totals->n) }} in view</div>
                    <div class="mt-1 grid grid-cols-3 gap-2 text-ink3"><div>Cost<div class="font-semibold text-ink tnum">{{ inr_short($totals->cost) }}</div></div><div>Acc. dep.<div class="font-semibold text-ink tnum">{{ inr_short($totals->acc) }}</div></div><div>NBV<div class="font-bold text-ink tnum">{{ inr_short($totals->nbv) }}</div></div></div>
                </div>
            @endif
        </div>

        <div class="mt-4 lg:mt-0 lg:border-t lg:border-line lg:px-5 lg:py-3">{{ $assets->links() }}</div>
    </div>
</x-app-layout>
