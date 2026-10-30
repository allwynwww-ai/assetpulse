<x-app-layout title="Run depreciation">
    <x-slot name="header">
        <x-page-header title="Run depreciation" subtitle="Preview the charge for a period, then post it." icon="calculator" :crumbs="['Depreciation' => route('depreciation.index'), 'Run' => null]"/>
    </x-slot>

    <form method="GET" class="card mb-6">
        <input type="hidden" name="preview" value="1">
        <div class="card-body grid gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
            <div>
                <label class="form-label">Run parameters</label>
                <select name="parameter" class="form-input">
                    @foreach ($parameters as $p)<option value="{{ $p->id }}" @selected($param?->id === $p->id)>{{ $p->name }}</option>@endforeach
                </select>
            </div>
            <div><label class="form-label">Period from</label><input type="date" name="from" value="{{ $from }}" class="form-input" required></div>
            <div><label class="form-label">Period to</label><input type="date" name="to" value="{{ $to }}" class="form-input" required></div>
            <div><button class="btn-primary w-full justify-center"><x-icon name="eye" class="h-4 w-4"/> Preview</button></div>
        </div>
        @if ($param)
            <div class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">
                {{ ucfirst($param->frequency) }} · proration by {{ $param->proration_basis }} · low-value threshold {{ inr($param->low_value_threshold, 0) }} · shift factor {{ $param->shift_factor }}× · suggested next period: <b>{{ $label }}</b>
            </div>
        @endif
    </form>

    @if ($preview)
        @if ($error)
            <div class="mb-5 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"><x-icon name="info" class="mt-0.5 h-4 w-4 shrink-0"/><div><b>This period cannot be posted:</b> {{ $error }} The figures below are for information only.</div></div>
        @endif
        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <div class="card p-5"><div class="stat-label">Assets to depreciate</div><div class="stat-value">{{ $preview['rows']->count() }}</div></div>
            <div class="card p-5 ring-1 ring-brand-200"><div class="stat-label">Total depreciation</div><div class="stat-value text-brand-700">{{ inr($preview['total']) }}</div></div>
            <div class="card p-5"><div class="stat-label">Skipped</div><div class="stat-value">{{ $preview['skipped']->count() }}</div><div class="mt-1 text-xs text-slate-400">Fully depreciated / not yet in use</div></div>
        </div>

        <div class="card mb-6">
            <div class="card-header"><div class="card-title">Preview · {{ fmt_date($from) }} – {{ fmt_date($to) }}</div>
                @if (! $error && $preview['rows']->count())
                    <form method="POST" action="{{ route('depreciation.store') }}" class="flex flex-wrap items-center gap-2" onsubmit="return confirm('Post depreciation of {{ inr($preview['total']) }} for {{ $preview['rows']->count() }} assets?')">
                        @csrf
                        <input type="hidden" name="from" value="{{ $from }}"><input type="hidden" name="to" value="{{ $to }}"><input type="hidden" name="parameter" value="{{ $param?->id }}">
                        <input name="remarks" class="form-input py-1.5 text-sm w-56" placeholder="Remarks (optional)">
                        <button class="btn-success"><x-icon name="check" class="h-4 w-4"/> Post run</button>
                    </form>
                @endif
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Asset</th><th>Method</th><th class="text-right">Rate %</th><th class="text-right">Days</th><th class="text-right">Opening WDV</th><th class="text-right">Depreciation</th><th class="text-right">Closing WDV</th><th>Note</th></tr></thead>
                    <tbody>
                    @foreach ($preview['rows'] as $row)
                        @php $r = $row['result']; $a = $row['asset']; @endphp
                        <tr>
                            <td><a href="{{ route('assets.show', $a) }}" class="font-mono text-xs font-semibold text-brand-700">{{ $a->asset_code }}</a><div class="text-sm text-slate-700">{{ $a->name }}</div></td>
                            <td class="text-xs">{{ $r->method }}</td>
                            <td class="text-right tabular-nums text-xs">{{ $r->rate !== null ? number_format($r->rate, 2) : '—' }}</td>
                            <td class="text-right tabular-nums text-xs">{{ $r->days }}</td>
                            <td class="text-right tabular-nums">{{ inr($r->openingWdv) }}</td>
                            <td class="text-right tabular-nums font-semibold text-amber-700">{{ inr($r->amount) }}</td>
                            <td class="text-right tabular-nums">{{ inr($r->closingWdv) }}</td>
                            <td class="text-xs text-slate-500">{{ match ($r->details['rule'] ?? null) { 'low_value' => 'Low-value write-off', 'end_of_life' => 'End of life – to residual', 'end_of_life_true_up' => 'Life over – true-up', default => (isset($r->details['segments']) && count($r->details['segments']) > 1 ? 'Spans FYs' : '') } }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot class="bg-slate-50 text-sm font-semibold"><tr><td colspan="5" class="px-4 py-3">Total</td><td class="px-4 py-3 text-right tabular-nums">{{ inr($preview['total']) }}</td><td colspan="2"></td></tr></tfoot>
                </table>
            </div>
        </div>

        @if ($preview['skipped']->count())
            <details class="card">
                <summary class="card-header cursor-pointer"><div class="card-title">Skipped assets ({{ $preview['skipped']->count() }})</div></summary>
                <ul class="divide-y divide-slate-100 text-sm">
                    @foreach ($preview['skipped'] as $s)
                        <li class="flex gap-3 px-5 py-2"><span class="font-mono text-xs text-slate-500">{{ $s['asset']->asset_code }}</span><span class="flex-1 text-slate-700">{{ $s['asset']->name }}</span><span class="text-xs text-slate-400">{{ $s['reason'] }}</span></li>
                    @endforeach
                </ul>
            </details>
        @endif
    @endif
</x-app-layout>
