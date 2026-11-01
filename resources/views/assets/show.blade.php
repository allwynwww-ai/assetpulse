@php
    $canWrite = auth()->user()->canManageAssets();
    $disposed = $asset->isDisposed();
    $disposal = $asset->disposals->firstWhere('status', 'completed');
    $live = $canWrite && ! $asset->trashed() && ! $disposed;
    $s = $asset->status;
    $dl = function (array $rows, string $cols = 'sm:grid-cols-2 xl:grid-cols-4') {
        $h = '<dl class="grid grid-cols-2 gap-x-6 gap-y-3.5 '.$cols.'">';
        foreach ($rows as $r) {
            [$k, $v] = $r; $mono = $r[2] ?? false;
            $h .= '<div class="min-w-0"><dt class="text-[11.5px] font-medium text-ink3">'.e($k).'</dt><dd class="mt-0.5 break-words text-[13.5px] font-medium text-ink '.($mono ? 'font-mono text-[12.5px]' : '').'">'.($v === null || $v === '' ? '<span class="text-ink3/60">—</span>' : e($v)).'</dd></div>';
        }
        return $h.'</dl>';
    };
    $section = fn ($title, $icon, $body, $extra = '') => '<div class="py-5 first:pt-0 last:pb-0"><div class="mb-3.5 flex items-center gap-2 text-[12px] font-semibold uppercase tracking-[.06em] text-ink3">'.view('components.icon', ['name' => $icon, 'class' => 'h-4 w-4', 'stroke' => '1.75'])->render().e($title).$extra.'</div>'.$body.'</div>';
    $make = trim(($asset->manufacturer ?? '').' '.($asset->model ?? ''));
    $nbvPct = (float) $asset->original_cost > 0 ? max(0, min(100, (float) $asset->current_book_value / (float) $asset->original_cost * 100)) : 0;
    $remaining = $asset->remaining_life_months;
    $lifeLeftPct = max(0, min(100, 100 - (float) $asset->life_consumed_percent));
    $asOf = $asset->last_depreciation_date ? 'as of '.fmt_date($asset->last_depreciation_date).' · posted' : ($asset->opening_balance_date ? 'opening balance · '.fmt_date($asset->opening_balance_date) : 'no depreciation posted yet');
    $nextPm = $asset->pmSchedules->filter(fn ($p) => $p->is_active && $p->next_due_date)->sortBy('next_due_date')->first();
    $openWos = $asset->workOrders->whereIn('status', ['open', 'in_progress', 'on_hold']);
    $btn2 = 'inline-flex h-9 items-center gap-1.5 whitespace-nowrap rounded-lg border border-line bg-card px-3 text-[13px] font-semibold text-ink2 shadow-card hover:bg-soft hover:text-ink';
    $tabs = ['overview' => ['Overview', null], 'schedule' => ['Depreciation schedule', $entries->count() ?: null], 'maintenance' => ['Maintenance', $asset->workOrders->count() + $asset->pmSchedules->count() ?: null], 'components' => ['Components', $asset->components->count() ?: null], 'transfers' => ['Transfers', $asset->transfers->count() ?: null], 'documents' => ['Documents', $asset->attachments->count() ?: null], 'audit' => ['Audit trail', null]];
    $chartNote = $asset->depreciationMethod?->code ? $asset->depreciationMethod->code.($lifeChart['lastCharge'] ? ' · last charge '.inr($lifeChart['lastCharge']['amount']).' ('.$lifeChart['lastCharge']['period'].')' : ' · no depreciation posted yet') : '';
@endphp
<x-app-layout :title="$asset->asset_code">
    {{-- Header --}}
    <nav class="hidden items-center gap-1.5 text-[12.5px] text-ink3 lg:flex">
        <a href="{{ route('assets.index') }}" class="hover:text-ink">Assets</a> <x-icon name="chev-right" class="h-3.5 w-3.5"/>
        <a href="{{ route('assets.index') }}" class="hover:text-ink">Register</a> <x-icon name="chev-right" class="h-3.5 w-3.5"/>
        <span class="font-mono text-ink2">{{ $asset->asset_code }}</span>
    </nav>
    <a href="{{ route('assets.index') }}" class="mb-3 inline-flex items-center gap-1 text-[13px] font-medium text-ink3 lg:hidden"><x-icon name="chev-left" class="h-4 w-4"/> Assets</a>
    <div class="flex flex-col gap-4 lg:mt-3 lg:flex-row lg:items-start lg:justify-between lg:gap-6">
        <div class="flex min-w-0 items-start gap-3 lg:items-center lg:gap-4">
            <x-cat-icon :category="$asset->category" size="h-12 w-12 lg:h-14 lg:w-14" icon-size="h-6 w-6 lg:h-7 lg:w-7" rounded="rounded-2xl"/>
            <div class="min-w-0">
                <h1 class="font-display text-[19px] font-bold leading-snug tracking-tight text-ink lg:text-[26px] lg:leading-tight">{{ $asset->name }}</h1>
                <div class="mt-1.5 flex flex-wrap items-center gap-2 text-[12.5px] text-ink3">
                    <span class="rounded-md bg-brand-500/10 px-1.5 py-0.5 font-mono text-[12px] font-semibold text-brand-700 dark:text-brand-200">{{ $asset->asset_code }}</span>
                    @if ($s)<span class="badge bg-{{ $s->color }}-500/10 text-{{ $s->color }}-700 ring-{{ $s->color }}-600/20 dark:text-{{ $s->color }}-300"><span class="h-1.5 w-1.5 rounded-full bg-{{ $s->color }}-500"></span>{{ $s->name }}</span>@endif
                    @if ($asset->trashed())<span class="badge bg-slate-500/10 text-slate-600 ring-slate-600/20">Deleted</span>@endif
                    @if ($asset->location)<span class="flex items-center gap-1"><x-icon name="pin" class="h-3.5 w-3.5"/> {{ $asset->location->name }}</span>@endif
                    @if ($asset->custodian)<span class="hidden text-line sm:inline">|</span><span class="hidden items-center gap-1 sm:flex"><x-icon name="user" class="h-3.5 w-3.5"/> {{ $asset->custodian }}</span>@endif
                    @if ($make)<span class="hidden text-line sm:inline">|</span><span class="hidden sm:inline">{{ $make }}</span>@endif
                </div>
            </div>
        </div>
        <div class="hidden shrink-0 items-center gap-2 lg:flex">
            <a href="{{ route('assets.label', $asset) }}" target="_blank" rel="noopener" class="{{ $btn2 }}"><x-icon name="printer" class="h-4 w-4"/> Label</a>
            @if ($live)
                <a href="{{ route('work-orders.create', ['asset' => $asset->id]) }}" class="{{ $btn2 }}"><x-icon name="wrench" class="h-4 w-4"/> Work order</a>
                <a href="{{ route('transfers.create', ['asset' => $asset->id]) }}" class="{{ $btn2 }}"><x-icon name="transfer" class="h-4 w-4"/> Transfer</a>
                <a href="{{ route('assets.edit', $asset) }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-brand-600 px-3.5 text-[13px] font-semibold text-white shadow-sm hover:bg-brand-700"><x-icon name="pencil" class="h-4 w-4"/> Edit asset</a>
            @endif
            @include('assets.partials.more-menu')
        </div>
    </div>

    @if ($disposed && $disposal)
        <div class="mt-5 flex flex-wrap items-center gap-3 rounded-2xl border border-line bg-soft/70 px-4 py-3 text-[13px] text-ink2">
            <x-icon name="info" class="h-4 w-4 text-ink3"/>
            <span>{{ \Illuminate\Support\Str::headline($disposal->disposal_type) }} on <b>{{ fmt_date($disposal->disposal_date) }}</b> ({{ $disposal->disposal_number }}) — NBV {{ inr($disposal->nbv_at_disposal) }}, proceeds {{ inr($disposal->sale_value) }},
                <b class="{{ $disposal->profit_loss < 0 ? 'text-rose-600 dark:text-rose-300' : 'text-emerald-700 dark:text-emerald-300' }}">{{ $disposal->profit_loss < 0 ? 'loss' : 'profit' }} {{ inr(abs($disposal->profit_loss)) }}</b>. This record is read-only.</span>
            <a href="{{ route('disposals.show', $disposal) }}" class="ml-auto font-semibold text-brand-600 hover:underline dark:text-brand-300">View disposal →</a>
        </div>
    @endif

    {{-- Mobile: NBV hero + quick actions --}}
    <div class="mt-4 overflow-hidden rounded-2xl bg-gradient-to-br from-brand-600 via-brand-700 to-brand-900 p-4 text-white shadow-lift lg:hidden">
        <div class="flex items-center justify-between text-[12px] text-white/70"><span>Net book value</span><span>{{ $asset->last_depreciation_date ? 'as of '.fmt_date($asset->last_depreciation_date) : '' }}</span></div>
        <div class="mt-1 font-display text-[30px] font-bold tracking-tight tnum">{{ inr($asset->current_book_value) }}</div>
        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-white/15"><div class="h-full rounded-full bg-teal-300" style="width: {{ round($nbvPct, 1) }}%"></div></div>
        <div class="mt-1.5 flex justify-between text-[11.5px] text-white/70"><span>{{ number_format($nbvPct, 1) }}% of cost remaining</span><span>{{ $remaining !== null ? $remaining.' months left' : '' }}</span></div>
        <div class="mt-3 grid grid-cols-2 gap-3 border-t border-white/15 pt-3 text-[12px]">
            <div><div class="text-white/60">Original cost</div><div class="mt-0.5 font-semibold tnum">{{ inr($asset->original_cost) }}</div></div>
            <div><div class="text-white/60">Accumulated dep.</div><div class="mt-0.5 font-semibold tnum">{{ inr($asset->accumulated_depreciation) }}</div></div>
        </div>
    </div>
    @php
        $mact = [];
        if ($live) {
            $mact[] = ['wrench', 'Work order', route('work-orders.create', ['asset' => $asset->id]), true];
            $mact[] = ['transfer', 'Transfer', route('transfers.create', ['asset' => $asset->id]), false];
        }
        $mact[] = ['printer', 'Label', route('assets.label', $asset), ! $live];
        if ($live) { $mact[] = ['pencil', 'Edit', route('assets.edit', $asset), false]; }
    @endphp
    <div class="mt-5 grid lg:hidden {{ [1 => 'grid-cols-2', 2 => 'grid-cols-3', 3 => 'grid-cols-4'][count($mact)] ?? 'grid-cols-5' }}">
        @foreach ($mact as [$ic, $t, $url, $primary])
            <a href="{{ $url }}" class="flex flex-col items-center gap-1.5" @if ($ic === 'printer') target="_blank" rel="noopener" @endif>
                <span class="grid h-12 w-12 place-items-center rounded-2xl {{ $primary ? 'bg-brand-600 text-white shadow-[0_8px_18px_-6px_rgba(72,64,214,.6)]' : 'border border-line bg-card text-ink2 shadow-card' }}"><x-icon :name="$ic" class="h-5 w-5"/></span>
                <span class="text-[11.5px] font-medium text-ink2">{{ $t }}</span>
            </a>
        @endforeach
        <div class="flex flex-col items-center gap-1.5">@include('assets.partials.more-menu', ['mobile' => true])<span class="text-[11.5px] font-medium text-ink2">More</span></div>
    </div>

    {{-- Desktop: 4 key-figure tiles --}}
    <div class="mt-6 hidden gap-4 lg:grid lg:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-line bg-card p-4 shadow-card">
            <div class="flex items-center gap-2 text-[12.5px] font-medium text-ink3"><x-icon name="tag" class="h-4 w-4"/> Original cost</div>
            <div class="mt-2 font-display text-[24px] font-bold tracking-tight text-ink tnum">{{ inr($asset->original_cost) }}</div>
            <div class="mt-1 text-[12px] text-ink3">Residual value <b class="font-semibold text-ink2">{{ inr($asset->residual_value) }}</b>@if ((float) $asset->original_cost > 0) ({{ rtrim(rtrim(number_format((float) $asset->residual_value / (float) $asset->original_cost * 100, 1), '0'), '.') }}%)@endif</div>
        </div>
        <div class="rounded-2xl border border-line bg-card p-4 shadow-card">
            <div class="flex items-center gap-2 text-[12.5px] font-medium text-ink3"><x-icon name="down" class="h-4 w-4"/> Accumulated depreciation</div>
            <div class="mt-2 font-display text-[24px] font-bold tracking-tight text-ink tnum">{{ inr($asset->accumulated_depreciation) }}</div>
            <div class="mt-2 flex items-center gap-2" title="% of depreciable amount"><div class="h-1.5 flex-1 overflow-hidden rounded-full bg-soft"><div class="h-full rounded-full bg-violet-500" style="width: {{ $asset->depreciated_percent }}%"></div></div><span class="text-[12px] font-semibold text-violet-600 tnum dark:text-violet-300">{{ $asset->depreciated_percent }}%</span></div>
        </div>
        <div class="relative overflow-hidden rounded-2xl border border-emerald-500/25 bg-gradient-to-br from-emerald-500/[.08] to-teal-500/[.03] p-4 shadow-card">
            <div class="flex items-center gap-2 text-[12.5px] font-medium text-emerald-700 dark:text-emerald-300"><x-icon name="shield" class="h-4 w-4"/> Net book value</div>
            <div class="mt-2 font-display text-[24px] font-bold tracking-tight text-ink tnum">{{ inr($asset->current_book_value) }}</div>
            <div class="mt-1 text-[12px] text-ink3">{{ $asOf }}</div>
        </div>
        <div class="rounded-2xl border border-line bg-card p-4 shadow-card">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 text-[12.5px] font-medium text-ink3"><x-icon name="clock" class="h-4 w-4"/> Remaining life</div>
                    <div class="mt-2 font-display text-[24px] font-bold tracking-tight text-ink tnum">{{ $remaining ?? '—' }} @if ($remaining !== null)<span class="text-[15px] font-semibold text-ink3">{{ \Illuminate\Support\Str::plural('month', $remaining) }}</span>@endif</div>
                    <div class="mt-1 truncate text-[12px] text-ink3">of {{ $asset->useful_life_label }}{{ $asset->end_of_life_date ? ' · ends '.fmt_date($asset->end_of_life_date) : '' }}</div>
                </div>
                <svg viewBox="0 0 36 36" class="h-14 w-14 shrink-0 -rotate-90" aria-label="{{ round($lifeLeftPct) }}% of life remaining"><circle cx="18" cy="18" r="15" fill="none" style="stroke: rgb(var(--soft))" stroke-width="4"/><circle cx="18" cy="18" r="15" fill="none" stroke="#4840D6" stroke-width="4" stroke-linecap="round" stroke-dasharray="{{ round(94.25 * $lifeLeftPct / 100, 1) }} 94.25"/></svg>
            </div>
        </div>
    </div>

    <div x-data="{ tab: (location.hash || '#overview').slice(1), go(t) { this.tab = t; history.replaceState(null, '', '#' + t); if (t === 'overview') this.$nextTick(() => window.dispatchEvent(new Event('resize'))) } }"
         x-init="if (!@js(array_keys($tabs)).includes(tab)) tab = 'overview'">
        <div class="no-sb -mx-4 mt-5 flex gap-5 overflow-x-auto border-b border-line px-4 lg:mx-0 lg:mt-6 lg:gap-6 lg:px-0" role="tablist">
            @foreach ($tabs as $key => [$label, $n])
                <button type="button" role="tab" @click="go('{{ $key }}')" :aria-selected="tab === '{{ $key }}'"
                        class="relative flex shrink-0 items-center gap-2 whitespace-nowrap px-1 pb-3 text-[13.5px]"
                        :class="tab === '{{ $key }}' ? 'font-semibold text-brand-700 dark:text-brand-200' : 'font-medium text-ink3 hover:text-ink'">
                    {{ $label }}
                    @if ($n)<span class="rounded-full bg-soft px-1.5 text-[11px] font-semibold text-ink3 tnum">{{ $n }}</span>@endif
                    <span x-show="tab === '{{ $key }}'" class="absolute inset-x-0 -bottom-px h-[2px] rounded-full bg-brand-600"></span>
                </button>
            @endforeach
        </div>

        <div class="mt-5 grid gap-6 lg:mt-6 xl:grid-cols-3">
            <div class="min-w-0 space-y-6 xl:col-span-2">
                {{-- OVERVIEW --}}
                <div x-show="tab === 'overview'" class="space-y-6">
                    <div class="card">
                        <div class="flex flex-wrap items-start justify-between gap-3 px-5 pt-4">
                            <div><h3 class="card-title">Book value over life</h3><p class="text-[12.5px] text-ink3">{{ $chartNote }}</p></div>
                            <div class="flex items-center gap-4 text-[12px] text-ink3">
                                <span class="flex items-center gap-1.5"><span class="h-2 w-4 rounded-full bg-brand-600"></span>Actual</span>
                                @if (count($lifeChart['projected']))<span class="flex items-center gap-1.5"><span class="h-0 w-4 border-t-2 border-dashed border-brand-300"></span>Projected</span>@endif
                                <span class="flex items-center gap-1.5"><span class="h-0 w-4 border-t border-dashed border-ink3"></span>Residual</span>
                            </div>
                        </div>
                        @if (count($lifeChart['actual']) + count($lifeChart['projected']) >= 2)
                            <div class="h-[180px] px-3 pb-3 pt-2 lg:h-[230px]"><canvas id="bv-chart" aria-label="Book value over the asset's life"></canvas></div>
                        @else
                            <p class="px-5 pb-5 pt-3 text-[13px] text-ink3">Not enough information yet to chart book value — add the put-to-use date and useful life.</p>
                        @endif
                    </div>

                    <div class="card">
                        <div class="flex items-center justify-between border-b border-line px-5 py-4">
                            <h3 class="card-title">Asset details</h3>
                            @if ($live)<a href="{{ route('assets.edit', $asset) }}" class="flex items-center gap-1.5 text-[12.5px] font-semibold text-brand-600 dark:text-brand-300"><x-icon name="pencil" class="h-3.5 w-3.5"/> Edit details</a>@endif
                        </div>
                        <div class="divide-y divide-line px-5 py-5">
                            {!! $section('Identification', 'tag', $dl([['Make / model', $make ?: null], ['Serial number', $asset->serial_number, true], ['Asset tag', $asset->asset_tag, true], ['Category', $asset->category?->name], ['Quantity', $asset->quantity.' '.$asset->unit_of_measure], ['Parent asset', $asset->parentAsset ? $asset->parentAsset->asset_code.' – '.$asset->parentAsset->name : null]])) !!}
                            {!! $section('Location & ownership', 'pin', $dl([['Location', $asset->location?->name], ['Department', $asset->department?->name], ['Cost centre', $asset->costCenter ? $asset->costCenter->code.' · '.$asset->costCenter->name : null], ['Custodian', $asset->custodian]])) !!}
                            {!! $section('Purchase', 'file', $dl([['Vendor', $asset->vendor?->name], ['Purchase order', $asset->purchase_order_number, true], ['Invoice', trim($asset->invoice_number.($asset->invoice_date ? ' · '.fmt_date($asset->invoice_date) : ''), ' ·')], ['Purchase date', fmt_date($asset->purchase_date)], ['Put to use', fmt_date($asset->capitalization_date)]])) !!}
                            @php
                                $depRows = [['Method', $asset->depreciationMethod?->label], ['Useful life', $asset->useful_life_label], ['Depreciable amount', inr($asset->depreciable_amount)], ['Last posted', fmt_date($asset->last_depreciation_date)]];
                                if ($asset->wdv_rate !== null && $asset->depreciationMethod?->code === 'WDV') { $depRows[] = ['WDV rate', number_format((float) $asset->wdv_rate, 2).'% p.a.']; }
                                if ($asset->total_units_capacity) { $depRows[] = ['Units', number_format((float) $asset->units_consumed).' / '.number_format((float) $asset->total_units_capacity)]; }
                                if ((float) $asset->opening_accumulated_depreciation > 0) { $depRows[] = ['Opening acc. dep.', inr($asset->opening_accumulated_depreciation).' · '.fmt_date($asset->opening_balance_date)]; }
                            @endphp
                            {!! $section('Depreciation', 'calculator', $dl($depRows)) !!}
                            {!! $section('Warranty & insurance', 'shield', $dl([['Warranty expiry', fmt_date($asset->warranty_expiry_date)], ['Insurance policy', $asset->insurance_policy_number, true], ['Insurance expiry', fmt_date($asset->insurance_expiry_date)]])) !!}
                            @if ($asset->description || $asset->notes)
                                <div class="space-y-3 py-5 last:pb-0">
                                    @if ($asset->description)<div><div class="text-[11.5px] font-medium text-ink3">Description</div><div class="mt-0.5 whitespace-pre-line text-[13.5px] text-ink">{{ $asset->description }}</div></div>@endif
                                    @if ($asset->notes)<div><div class="text-[11.5px] font-medium text-ink3">Notes</div><div class="mt-0.5 whitespace-pre-line text-[13.5px] text-ink">{{ $asset->notes }}</div></div>@endif
                                </div>
                            @endif
                        </div>
                    </div>

                    @if ($asset->components->count())
                        @include('assets.partials.components')
                    @endif
                </div>

                {{-- SCHEDULE --}}
                <div x-show="tab === 'schedule'" x-cloak class="card" x-data="{ all: false }">
                    <div class="card-header">
                        <div class="card-title">Depreciation schedule <span class="ml-1 text-[13px] font-semibold text-ink3">{{ $entries->count() }}</span></div>
                        <div class="flex items-center gap-3 text-xs">
                            <span class="text-ink3">Charged to date {{ inr($entries->sum('depreciation_amount')) }}</span>
                            <a href="{{ route('reports.show', ['depreciation-detail', 'q' => $asset->asset_code, 'run' => '']) }}" class="font-semibold text-brand-600 hover:underline dark:text-brand-300">Full report →</a>
                        </div>
                    </div>
                    @if ($entries->isEmpty())
                        <p class="card-body text-sm text-ink3">No depreciation has been posted for this asset yet.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="table text-sm">
                                <thead><tr><th>Period</th><th>Run</th><th class="text-right">Days</th><th class="text-right">Opening WDV</th><th class="text-right">Charge</th><th class="text-right">Acc. dep.</th><th class="text-right">Closing WDV</th></tr></thead>
                                <tbody>
                                @foreach ($entries->reverse()->values() as $i => $e)
                                    <tr @if ($i >= 12) x-show="all" x-cloak @endif>
                                        <td class="whitespace-nowrap">{{ $e->period_start->format('d M') }} – {{ $e->period_end->format('d M Y') }}</td>
                                        <td class="whitespace-nowrap">@if ($e->run)<a href="{{ route('depreciation.show', $e->run) }}" class="font-mono text-xs text-brand-600 dark:text-brand-300">{{ $e->run->run_number }}</a> <x-pill :value="$e->run->run_type === 'disposal' ? 'disposal' : $e->run->status"/>@endif</td>
                                        <td class="text-right tnum">{{ $e->days }}</td>
                                        <td class="text-right tnum">{{ inr($e->opening_wdv, 2, false) }}</td>
                                        <td class="text-right font-medium tnum">{{ inr($e->depreciation_amount, 2, false) }}</td>
                                        <td class="text-right tnum">{{ inr($e->closing_accumulated_depreciation, 2, false) }}</td>
                                        <td class="text-right tnum">{{ inr($e->closing_wdv, 2, false) }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if ($entries->count() > 12)
                            <div class="border-t border-line px-5 py-2 text-center"><button type="button" @click="all = !all" class="text-xs font-semibold text-brand-600 dark:text-brand-300" x-text="all ? 'Show latest 12 only' : 'Show all {{ $entries->count() }} periods'"></button></div>
                        @endif
                    @endif
                </div>

                {{-- MAINTENANCE --}}
                <div x-show="tab === 'maintenance'" x-cloak class="card">
                    <div class="card-header"><div class="card-title">Maintenance</div>
                        <div class="flex items-center gap-2 text-xs"><span class="text-ink3">Spend to date {{ inr($maintenanceCost) }}</span>
                            @if ($live)<a href="{{ route('pm-schedules.create', ['asset' => $asset->id]) }}" class="btn-ghost btn-sm"><x-icon name="calendar" class="h-3.5 w-3.5"/> PM</a><a href="{{ route('work-orders.create', ['asset' => $asset->id]) }}" class="btn-ghost btn-sm"><x-icon name="wrench" class="h-3.5 w-3.5"/> Work order</a>@endif</div></div>
                    @if ($asset->pmSchedules->isNotEmpty())
                        <div class="border-b border-line px-5 py-3">
                            <div class="mb-2 text-xs font-semibold uppercase tracking-wider text-ink3">PM schedules</div>
                            <div class="space-y-2">
                                @foreach ($asset->pmSchedules as $pm)
                                    @php $over = $pm->is_active && $pm->next_due_date && $pm->next_due_date->isPast() && ! $pm->next_due_date->isToday(); $soon = $pm->is_active && $pm->next_due_date && ! $over && $pm->next_due_date->lte(now()->addDays(30)); @endphp
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                                        <a href="{{ $canWrite ? route('pm-schedules.edit', $pm) : route('pm-schedules.index') }}" class="font-medium text-ink hover:text-brand-600">{{ $pm->title }}</a>
                                        <span class="text-xs text-ink3">every {{ $pm->frequency_interval }} {{ str_replace('_', ' ', $pm->frequency_unit) }}</span>
                                        <span class="ml-auto text-xs {{ $over ? 'font-semibold text-rose-600 dark:text-rose-300' : ($soon ? 'font-semibold text-amber-600 dark:text-amber-300' : 'text-ink3') }}">
                                            {{ ! $pm->is_active ? 'Inactive' : ($pm->next_due_date ? ($over ? 'Overdue since ' : 'Due ').fmt_date($pm->next_due_date) : 'Not scheduled') }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if ($asset->workOrders->isEmpty())
                        <p class="card-body text-sm text-ink3">No work orders recorded.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="table text-sm">
                                <thead><tr><th>WO</th><th>Reported</th><th>Type</th><th>Title</th><th>Status</th><th class="text-right">Downtime</th><th class="text-right">Cost</th></tr></thead>
                                <tbody>
                                @foreach ($asset->workOrders as $wo)
                                    <tr>
                                        <td class="whitespace-nowrap"><a href="{{ route('work-orders.show', $wo) }}" class="font-mono text-xs text-brand-600 dark:text-brand-300">{{ $wo->wo_number }}</a></td>
                                        <td class="whitespace-nowrap">{{ fmt_date($wo->reported_date) }}</td>
                                        <td><x-pill :value="$wo->type"/></td>
                                        <td class="max-w-xs truncate" title="{{ $wo->title }}">{{ $wo->title }}</td>
                                        <td><x-pill :value="$wo->status"/></td>
                                        <td class="text-right tnum">{{ $wo->downtime_hours > 0 ? rtrim(rtrim(number_format($wo->downtime_hours, 1), '0'), '.').' h' : '—' }}</td>
                                        <td class="text-right tnum">{{ inr($wo->total_cost, 0) }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- COMPONENTS --}}
                <div x-show="tab === 'components'" x-cloak>
                    @if ($asset->components->count())
                        @include('assets.partials.components')
                    @else
                        <div class="card card-body text-sm text-ink3">No components are linked to this asset. Link one by choosing this asset as the <b>parent asset</b> when adding or editing a component.</div>
                    @endif
                </div>

                {{-- TRANSFERS --}}
                <div x-show="tab === 'transfers'" x-cloak class="card">
                    <div class="card-header"><div class="card-title">Transfer history <span class="ml-1 text-[13px] font-semibold text-ink3">{{ $asset->transfers->count() }}</span></div>
                        @if ($live)<a href="{{ route('transfers.create', ['asset' => $asset->id]) }}" class="btn-ghost btn-sm"><x-icon name="transfer" class="h-3.5 w-3.5"/> Transfer</a>@endif</div>
                    @if ($asset->transfers->isEmpty())
                        <p class="card-body text-sm text-ink3">This asset has not been moved since it was put to use.</p>
                    @else
                        <ol class="divide-y divide-line">
                            @foreach ($asset->transfers as $t)
                                <li class="flex flex-wrap items-start gap-x-4 gap-y-1 px-5 py-3 text-sm">
                                    <div class="w-24 shrink-0 text-xs text-ink3">{{ fmt_date($t->transfer_date) }}<br><a href="{{ route('transfers.show', $t) }}" class="font-mono text-brand-600 dark:text-brand-300">{{ $t->transfer_number }}</a></div>
                                    <div class="min-w-0 flex-1">
                                        @foreach ([['Location', $t->fromLocation?->name, $t->toLocation?->name], ['Department', $t->fromDepartment?->name, $t->toDepartment?->name], ['Cost center', $t->fromCostCenter?->code, $t->toCostCenter?->code], ['Custodian', $t->from_custodian, $t->to_custodian]] as [$lbl, $a, $b])
                                            @if ($a !== $b)<div><span class="text-ink3">{{ $lbl }}:</span> {{ $a ?: '—' }} <span class="text-ink3">→</span> <span class="font-medium text-ink">{{ $b ?: '—' }}</span></div>@endif
                                        @endforeach
                                        <div class="text-xs text-ink3">{{ $t->reason }}@if ($t->affects_depreciation) · <span class="text-sky-700 dark:text-sky-300">depreciation split by cost center</span>@endif</div>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>

                {{-- DOCUMENTS --}}
                <div x-show="tab === 'documents'" x-cloak class="card">
                    <div class="card-header"><div class="card-title">Documents <span class="ml-1 text-[13px] font-semibold text-ink3">{{ $asset->attachments->count() }}</span></div><x-icon name="paperclip" class="h-4 w-4 text-ink3"/></div>
                    <div class="card-body">
                        @if ($asset->attachments->count())
                            <ul class="divide-y divide-line">
                                @foreach ($asset->attachments as $file)
                                    <li class="flex items-center gap-3 py-2.5">
                                        @if ($file->is_image)
                                            <img src="{{ $file->url }}" alt="" class="h-10 w-10 rounded-lg object-cover ring-1 ring-line">
                                        @else
                                            <span class="grid h-10 w-10 place-items-center rounded-lg bg-soft text-ink3"><x-icon name="doc" class="h-5 w-5"/></span>
                                        @endif
                                        <div class="min-w-0 flex-1">
                                            <a href="{{ $file->url }}" target="_blank" class="block truncate text-sm font-medium text-ink hover:text-brand-600">{{ $file->title ?: $file->original_name }}</a>
                                            <div class="text-xs text-ink3">{{ \App\Models\AssetAttachment::TYPES[$file->type] ?? $file->type }} · {{ $file->size_label }} · {{ $file->uploader?->name }} · {{ $file->created_at?->format('d M Y') }}</div>
                                        </div>
                                        <a href="{{ $file->url }}" download="{{ $file->original_name }}" class="btn-ghost btn-sm" title="Download"><x-icon name="download" class="h-4 w-4"/></a>
                                        @if ($canWrite)
                                            <form method="POST" action="{{ route('assets.attachments.destroy', [$asset, $file]) }}" onsubmit="return confirm('Remove this attachment?')">@csrf @method('DELETE')
                                                <button class="btn-ghost btn-sm text-rose-500 hover:bg-rose-500/10" title="Remove"><x-icon name="x" class="h-4 w-4"/></button>
                                            </form>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-sm text-ink3">No documents attached yet.</p>
                        @endif
                        @if ($canWrite && ! $asset->trashed())
                            <form method="POST" action="{{ route('assets.attachments.store', $asset) }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-end gap-3 rounded-xl border border-dashed border-line bg-soft/60 p-4">
                                @csrf
                                <div><label class="form-label text-xs">Type</label>
                                    <select name="type" class="form-input py-1.5 text-sm">@foreach (\App\Models\AssetAttachment::TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                                <div class="min-w-[10rem] flex-1"><label class="form-label text-xs">Title (optional)</label><input name="title" class="form-input py-1.5 text-sm"></div>
                                <div><label class="form-label text-xs">Files</label><input type="file" name="files[]" multiple required class="text-sm text-ink3 file:mr-2 file:rounded-lg file:border-0 file:bg-brand-500/10 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-brand-700"></div>
                                <button class="btn-primary py-1.5"><x-icon name="paperclip" class="h-4 w-4"/> Upload</button>
                                @error('files')<p class="form-error w-full">{{ $message }}</p>@enderror
                                @error('files.*')<p class="form-error w-full">{{ $message }}</p>@enderror
                            </form>
                        @endif
                    </div>
                </div>

                {{-- AUDIT --}}
                <div x-show="tab === 'audit'" x-cloak class="card">
                    <div class="card-header"><div class="card-title">Audit trail</div><x-icon name="shield" class="h-4 w-4 text-ink3"/></div>
                    <div class="card-body">
                        @include('partials.audit-timeline', ['logs' => $history])
                        <div class="mt-4 border-t border-line pt-3 text-xs text-ink3">
                            Created {{ $asset->created_at?->format('d M Y, h:i A') }}{{ $asset->creator ? ' by '.$asset->creator->name : '' }}<br>
                            Updated {{ $asset->updated_at?->format('d M Y, h:i A') }}{{ $asset->updater ? ' by '.$asset->updater->name : '' }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right rail --}}
            <div class="min-w-0 space-y-6">
                <div class="card p-5">
                    <div class="flex items-center justify-between"><h3 class="card-title">Asset label</h3>@if ($asset->asset_tag)<span class="font-mono text-[11.5px] text-ink3">{{ $asset->asset_tag }}</span>@endif</div>
                    <div class="mt-4 flex items-center gap-4">
                        <div class="w-[118px] shrink-0 rounded-xl border border-line bg-white p-2.5 [&>svg]:h-auto [&>svg]:w-full">{!! $qrSvg !!}</div>
                        <div class="min-w-0 text-[12.5px] text-ink3"><div class="font-mono text-[14px] font-semibold text-ink">{{ $asset->asset_code }}</div><div class="mt-1">Scan to open this asset (sign-in required).</div></div>
                    </div>
                    <div class="mt-4 grid grid-cols-3 gap-2">
                        <a href="{{ route('assets.label', $asset) }}" target="_blank" rel="noopener" class="{{ $btn2 }} justify-center"><x-icon name="printer" class="h-4 w-4"/> Print</a>
                        <a href="{{ route('assets.qr', [$asset, 'format' => 'png']) }}" class="{{ $btn2 }} justify-center"><x-icon name="download" class="h-4 w-4"/> PNG</a>
                        <a href="{{ route('assets.qr', $asset) }}" target="_blank" rel="noopener" class="{{ $btn2 }} justify-center">SVG</a>
                    </div>
                </div>

                <div class="card p-5">
                    <div class="flex items-center justify-between"><h3 class="card-title">Maintenance</h3><button type="button" @click="go('maintenance')" class="text-[12.5px] font-semibold text-brand-600 dark:text-brand-300">View all</button></div>
                    @if ($nextPm)
                        @php $d = (int) now()->startOfDay()->diffInDays($nextPm->next_due_date->copy()->startOfDay(), false); $tone = $d < 0 ? 'rose' : ($d <= 30 ? 'amber' : 'emerald'); @endphp
                        <div class="mt-4 rounded-xl border border-{{ $tone }}-500/25 bg-{{ $tone }}-500/[.06] p-3.5">
                            <div class="flex items-center gap-2 text-[12px] font-semibold text-{{ $tone }}-700 dark:text-{{ $tone }}-300"><x-icon name="calendar" class="h-4 w-4"/>
                                {{ $d < 0 ? 'PM overdue by '.abs($d).' '.\Illuminate\Support\Str::plural('day', abs($d)) : ($d === 0 ? 'PM due today' : 'Next PM due in '.$d.' '.\Illuminate\Support\Str::plural('day', $d)) }} · {{ fmt_date($nextPm->next_due_date) }}</div>
                            <div class="mt-1.5 text-[13px] font-medium text-ink">{{ $nextPm->title }}</div>
                            <div class="mt-0.5 text-[12px] text-ink3">every {{ $nextPm->frequency_interval }} {{ str_replace('_', ' ', $nextPm->frequency_unit) }}</div>
                        </div>
                    @else
                        <p class="mt-3 text-[13px] text-ink3">No active PM schedule.</p>
                    @endif
                    @foreach ($openWos->take(2) as $wo)
                        <a href="{{ route('work-orders.show', $wo) }}" class="mt-3 block rounded-xl border border-{{ $wo->type === 'breakdown' ? 'rose' : 'sky' }}-500/20 bg-{{ $wo->type === 'breakdown' ? 'rose' : 'sky' }}-500/[.05] p-3.5">
                            <div class="flex items-center gap-2 text-[12px] font-semibold text-{{ $wo->type === 'breakdown' ? 'rose' : 'sky' }}-600 dark:text-{{ $wo->type === 'breakdown' ? 'rose' : 'sky' }}-300"><x-icon name="{{ $wo->type === 'breakdown' ? 'alert' : 'wrench' }}" class="h-4 w-4"/> {{ \Illuminate\Support\Str::headline($wo->status) }} {{ str_replace('_', ' ', $wo->type) }}</div>
                            <div class="mt-1.5 text-[13px] font-medium text-ink">{{ $wo->wo_number }} · {{ $wo->title }}</div>
                            <div class="mt-0.5 text-[12px] text-ink3">Reported {{ fmt_date($wo->reported_date) }}</div>
                        </a>
                    @endforeach
                    <div class="mt-3 text-[12px] text-ink3">Spend to date <b class="font-semibold text-ink2 tnum">{{ inr($maintenanceCost) }}</b></div>
                    @if ($live)
                        <a href="{{ route('work-orders.create', ['asset' => $asset->id]) }}" class="mt-3 flex h-9 w-full items-center justify-center gap-1.5 rounded-lg bg-brand-600 text-[13px] font-semibold text-white hover:bg-brand-700"><x-icon name="wrench" class="h-4 w-4"/> Raise work order</a>
                    @endif
                </div>

                <div class="card p-5">
                    <h3 class="card-title">Coverage</h3>
                    <div class="mt-4 space-y-3">
                        @php $insOk = $asset->insurance_expiry_date && ! $asset->insurance_expiry_date->isPast(); $warOk = $asset->warranty_expiry_date && ! $asset->warranty_expiry_date->isPast(); @endphp
                        <div class="flex items-center gap-3">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl {{ $insOk ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-300' : 'bg-soft text-ink3' }}"><x-icon name="shield" class="h-[18px] w-[18px]"/></span>
                            <div class="min-w-0 flex-1"><div class="text-[13px] font-medium text-ink">Insurance</div><div class="truncate font-mono text-[11px] text-ink3">{{ $asset->insurance_policy_number ?: 'No policy recorded' }}</div></div>
                            @if ($asset->insurance_expiry_date)<div class="text-right"><div class="text-[12px] font-semibold {{ $insOk ? 'text-emerald-600 dark:text-emerald-300' : 'text-ink3' }}">{{ $insOk ? 'Active' : 'Expired' }}</div><div class="text-[11px] text-ink3">{{ $insOk ? 'to ' : '' }}{{ fmt_date($asset->insurance_expiry_date) }}</div></div>@endif
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl {{ $warOk ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-300' : 'bg-soft text-ink3' }}"><x-icon name="file" class="h-[18px] w-[18px]"/></span>
                            <div class="min-w-0 flex-1"><div class="text-[13px] font-medium text-ink">Warranty</div><div class="truncate text-[11px] text-ink3">{{ $asset->vendor?->name ?? ($asset->manufacturer ?: '—') }}</div></div>
                            @if ($asset->warranty_expiry_date)<div class="text-right"><div class="text-[12px] font-semibold {{ $warOk ? 'text-emerald-600 dark:text-emerald-300' : 'text-ink3' }}">{{ $warOk ? 'In warranty' : 'Expired' }}</div><div class="text-[11px] text-ink3">{{ fmt_date($asset->warranty_expiry_date) }}</div></div>@else<div class="text-[12px] text-ink3">Not recorded</div>@endif
                        </div>
                    </div>
                </div>

                <div class="card p-5">
                    <div class="flex items-center justify-between"><h3 class="card-title">Activity</h3><button type="button" @click="go('audit')" class="text-[12.5px] font-semibold text-brand-600 dark:text-brand-300">Audit trail</button></div>
                    <div class="mt-4">@include('partials.audit-timeline', ['logs' => $history->take(5)])</div>
                </div>
            </div>
        </div>
    </div>

    @if (count($lifeChart['actual']) + count($lifeChart['projected']) >= 2)
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const D = @json($lifeChart);
                    const el = document.getElementById('bv-chart');
                    if (!el || !window.apChart) return;
                    const fmtD = (ms) => new Date(ms).toLocaleDateString('en-IN', { month: 'short', year: '2-digit' });
                    const all = D.actual.concat(D.projected);
                    const xs = all.map(p => p.x);
                    const today = D.actual.length ? D.actual[D.actual.length - 1] : null;
                    window.apChart(() => {
                        const T = window.apTheme;
                        const ctx = el.getContext('2d');
                        const g = ctx.createLinearGradient(0, 0, 0, el.clientHeight || 230);
                        g.addColorStop(0, T.isDark() ? 'rgba(129,140,248,.28)' : 'rgba(72,64,214,.22)'); g.addColorStop(1, 'rgba(72,64,214,0)');
                        const brand = T.isDark() ? '#8B8DF8' : '#4840D6';
                        return new Chart(el, {
                            type: 'line',
                            data: { datasets: [
                                { label: 'Actual', data: D.actual, borderColor: brand, backgroundColor: g, fill: true, tension: 0, borderWidth: 2.25,
                                  pointRadius: D.actual.map((p, i) => i === D.actual.length - 1 ? 5 : 0), pointBackgroundColor: T.css('--card'), pointBorderColor: brand, pointBorderWidth: 2.5 },
                                { label: 'Projected', data: D.projected, borderColor: '#A2A6FB', borderDash: [5, 4], borderWidth: 2, pointRadius: 0, fill: false },
                                { label: 'Residual', data: [{ x: Math.min(...xs), y: D.residual }, { x: Math.max(...xs), y: D.residual }], borderColor: T.css('--ink3'), borderDash: [2, 3], borderWidth: 1, pointRadius: 0, fill: false },
                            ] },
                            options: {
                                maintainAspectRatio: false, parsing: false, interaction: { mode: 'nearest', intersect: false },
                                scales: {
                                    x: { type: 'linear', min: Math.min(...xs), max: Math.max(...xs), grid: { display: false }, ticks: { maxRotation: 0, maxTicksLimit: el.clientWidth < 500 ? 4 : 8, callback: v => fmtD(v) } },
                                    y: { beginAtZero: true, suggestedMax: D.cost, grid: { color: T.css('--line') }, border: { display: false }, ticks: { maxTicksLimit: 5, callback: v => window.inr(v, true) } },
                                },
                                plugins: { legend: { display: false }, tooltip: { callbacks: { title: (it) => new Date(it[0].parsed.x).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }), label: (c) => ' ' + c.dataset.label + ': ' + window.inr(c.parsed.y) } } },
                            },
                        });
                    });
                });
            </script>
        @endpush
    @endif
</x-app-layout>
