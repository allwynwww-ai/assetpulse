@php
    use App\Services\Reports\ReportBuilder as RB;
    $merge = $r['screenMerge'] ?? [];           // [childKey => parentKey]: child rendered as a sub-line of parent
    $mergedInto = array_flip($merge);            // [parentKey => childKey]
    $cols = array_values(array_filter($r['columns'], fn ($c) => ! isset($merge[$c['key']])));
    if ($merge && ! empty($r['groupHeaders'])) { $r['groupHeaders'][0][1] -= count($merge); }
    $num = fn ($c) => in_array($c['type'], ['money', 'pl', 'number', 'int', 'percent']);
    $q = request()->except(['page', 'export']);
@endphp
<x-app-layout :title="$r['title']">
    <x-slot name="header">
        <x-page-header :title="$r['title']" :subtitle="$r['subtitle']" icon="chart" :crumbs="['Reports' => route('reports.index'), \Illuminate\Support\Str::limit($r['title'], 40) => null]">
            <a href="{{ route('reports.show', [$r['slug']] + $q + ['export' => 'xlsx']) }}" class="btn-secondary"><x-icon name="download" class="h-4 w-4"/> Excel</a>
            <a href="{{ route('reports.show', [$r['slug']] + $q + ['export' => 'pdf']) }}" class="btn-secondary"><x-icon name="printer" class="h-4 w-4"/> PDF</a>
        </x-page-header>
    </x-slot>

    @if (! empty($r['filters']))
        <form method="GET" class="card mb-5 flex flex-wrap items-end gap-3 px-5 py-4">
            @foreach ($r['filters'] as $f)
                <div class="min-w-[9rem] {{ $f['type'] === 'text' ? 'flex-1' : '' }}">
                    <label class="form-label text-xs">{{ $f['label'] }}</label>
                    @if ($f['type'] === 'select')
                        <select name="{{ $f['name'] }}" class="form-input py-1.5 text-sm">
                            @unless (isset($f['default']))<option value="">All</option>@endunless
                            @foreach ($f['options'] as $k => $v)<option value="{{ $k }}" @selected((string) request($f['name'], $f['default'] ?? '') === (string) $k)>{{ $v }}</option>@endforeach
                        </select>
                    @else
                        <input type="{{ $f['type'] === 'date' ? 'date' : 'search' }}" name="{{ $f['name'] }}" value="{{ request($f['name']) }}" class="form-input py-1.5 text-sm">
                    @endif
                </div>
            @endforeach
            <div class="flex gap-2"><button class="btn-primary py-1.5">Apply</button><a href="{{ route('reports.show', $r['slug']) }}" class="btn-ghost py-1.5">Reset</a></div>
        </form>
    @endif

    @if (! empty($r['totals']))
        <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="card p-4"><div class="stat-label">Rows</div><div class="stat-value">{{ number_format($rows->total()) }}</div></div>
            @foreach (collect($cols)->filter(fn ($c) => array_key_exists($c['key'], $r['totals']) && in_array($c['type'], ['money', 'pl']))->take(3) as $c)
                <div class="card p-4"><div class="stat-label">{{ $c['label'] }}</div><div class="stat-value text-xl {{ $c['type'] === 'pl' && $r['totals'][$c['key']] < 0 ? 'text-rose-600' : '' }}">₹ {{ RB::format($r['totals'][$c['key']], $c['type']) }}</div></div>
            @endforeach
        </div>
    @endif

    @php $wide = count($cols) > 8; @endphp
    <div class="card relative overflow-hidden"
         x-data="{ l: false, r: false, u() { const e = this.$refs.s; this.l = e.scrollLeft > 0; this.r = e.scrollLeft + e.clientWidth < e.scrollWidth - 1; } }"
         x-init="u(); new ResizeObserver(() => u()).observe($refs.s)" :class="l && 'is-scrolled'">
        <div class="overflow-x-auto" x-ref="s" @scroll.passive="u()">
            <table class="table table-sticky {{ $wide ? 'table-compact' : 'text-sm' }}">
                <thead>
                    @if (! empty($r['groupHeaders']))
                        <tr>@foreach ($r['groupHeaders'] as [$l, $span])<th colspan="{{ $span }}" class="border-b border-slate-200 text-center {{ $l ? 'bg-slate-100' : '' }}">{{ $l }}</th>@endforeach</tr>
                    @endif
                    <tr>@foreach ($cols as $c)<th class="{{ $num($c) ? 'text-right' : '' }} {{ $wide ? '' : 'whitespace-nowrap' }}">{{ $c['label'] }}</th>@endforeach</tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        @foreach ($cols as $c)
                            @php $v = $row[$c['key']] ?? null; @endphp
                            <td class="{{ $num($c) ? 'text-right tabular-nums whitespace-nowrap' : '' }} {{ $c['type'] === 'code' ? 'font-mono text-xs whitespace-nowrap' : '' }} {{ $c['type'] === 'date' ? 'whitespace-nowrap' : '' }} {{ $c['type'] === 'pl' && $v < 0 ? 'text-rose-600' : '' }} {{ in_array($c['key'], ['name', 'changes', 'remarks', 'title']) ? 'max-w-xs' : '' }}">
                                @if ($c['type'] === 'pill' && $v)<x-pill :value="$v"/>@else{{ RB::format($v, $c['type']) }}@endif
                                @isset($mergedInto[$c['key']])<div class="mt-0.5 text-[11px] text-slate-400">{{ $row[$mergedInto[$c['key']]] ?? '' }}</div>@endisset
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($cols) ?: 1 }}" class="py-12 text-center text-slate-400">No data for the selected filters.</td></tr>
                @endforelse
                </tbody>
                @if (! empty($r['totals']) && $rows->count())
                    <tfoot>
                        <tr class="border-t-2 border-slate-300 bg-slate-50 font-semibold">
                            @foreach ($cols as $i => $c)
                                <td class="{{ $num($c) ? 'text-right tabular-nums whitespace-nowrap' : '' }}">{{ $i === 0 ? 'Total' : (array_key_exists($c['key'], $r['totals']) ? RB::format($r['totals'][$c['key']], $c['type']) : '') }}</td>
                            @endforeach
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        <div class="pointer-events-none absolute inset-y-0 right-0 w-8 bg-gradient-to-l from-slate-900/10 to-transparent transition-opacity duration-200" :class="r ? 'opacity-100' : 'opacity-0'" aria-hidden="true"></div>
        @if ($rows->hasPages())<div class="border-t border-slate-100 px-5 py-3">{{ $rows->links() }}</div>@endif
        @if (! empty($r['notes']))
            <div class="space-y-1 border-t border-slate-100 px-5 py-4 text-xs text-slate-500">@foreach ($r['notes'] as $n)<p>Note: {{ $n }}</p>@endforeach</div>
        @endif
    </div>
</x-app-layout>
