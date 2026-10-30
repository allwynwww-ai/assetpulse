<x-app-layout title="Disposals">
    <x-slot name="header">
        <x-page-header title="Disposals" subtitle="Sales, scrapping and write-offs with profit / loss on disposal." icon="trash" :crumbs="['Operations' => null, 'Disposals' => null]">
            <a href="{{ route('reports.show', 'disposal-register') }}" class="btn-secondary"><x-icon name="chart" class="h-4 w-4"/> Disposal register</a>
            @can('manage-assets')<a href="{{ route('disposals.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4"/> Dispose an asset</a>@endcan
        </x-page-header>
    </x-slot>
    <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card p-5"><div class="stat-label">Disposals</div><div class="stat-value">{{ $totals->n }}</div></div>
        <div class="card p-5"><div class="stat-label">Gross block removed</div><div class="stat-value">{{ inr_short($totals->gross) }}</div><div class="text-xs text-slate-400">NBV {{ inr_short($totals->nbv) }}</div></div>
        <div class="card p-5"><div class="stat-label">Sale proceeds</div><div class="stat-value">{{ inr_short($totals->proceeds) }}</div></div>
        <div class="card p-5"><div class="stat-label">Net profit / (loss)</div><div class="stat-value {{ $totals->pl < 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $totals->pl < 0 ? '(' . inr_short(abs($totals->pl)) . ')' : inr_short($totals->pl) }}</div></div>
    </div>
    <div class="card">
        <form method="GET" class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 py-3">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Disposal no., asset, buyer…" class="form-input min-w-[14rem] flex-1 py-1.5 text-sm">
            <select name="type" class="form-input w-44 py-1.5 text-sm"><option value="">All types</option>@foreach (\App\Models\AssetDisposal::TYPES as $k => $l)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $l }}</option>@endforeach</select>
            <input type="date" name="from" value="{{ request('from') }}" class="form-input w-40 py-1.5 text-sm"><input type="date" name="to" value="{{ request('to') }}" class="form-input w-40 py-1.5 text-sm">
            <button class="btn-primary py-1.5">Filter</button>
        </form>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Disposal</th><th>Date</th><th>Asset</th><th>Type</th><th class="text-right">Gross</th><th class="text-right">Acc. dep.</th><th class="text-right">NBV</th><th class="text-right">Proceeds</th><th class="text-right">Profit / (loss)</th></tr></thead>
                <tbody>
                @forelse ($disposals as $d)
                    <tr>
                        <td><a href="{{ route('disposals.show', $d) }}" class="font-mono text-xs font-semibold text-brand-700">{{ $d->disposal_number }}</a></td>
                        <td class="whitespace-nowrap text-sm">{{ fmt_date($d->disposal_date) }}</td>
                        <td><a href="{{ route('assets.show', $d->asset_id) }}" class="font-mono text-xs text-brand-700">{{ $d->asset?->asset_code }}</a><div class="line-clamp-2 min-w-[12rem] max-w-[18rem] text-sm" title="{{ $d->asset?->name }}">{{ $d->asset?->name }}</div></td>
                        <td><x-pill :value="$d->disposal_type"/></td>
                        <td class="text-right tabular-nums text-sm">{{ inr($d->gross_block_at_disposal, 0) }}</td>
                        <td class="text-right tabular-nums text-sm">{{ inr($d->accumulated_depreciation_at_disposal, 0) }}</td>
                        <td class="text-right tabular-nums text-sm">{{ inr($d->nbv_at_disposal, 0) }}</td>
                        <td class="text-right tabular-nums text-sm">{{ inr($d->sale_value, 0) }}</td>
                        <td class="text-right tabular-nums text-sm font-semibold {{ $d->profit_loss < 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $d->profit_loss < 0 ? '('.inr(abs($d->profit_loss), 0).')' : inr($d->profit_loss, 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="py-12 text-center text-sm text-slate-400">No disposals recorded.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 px-5 py-3">{{ $disposals->links() }}</div>
    </div>
</x-app-layout>
