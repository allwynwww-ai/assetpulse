@php
    $canRun = auth()->user()->canManageAssets();
    $isAdmin = auth()->user()->isCompanyAdmin() || auth()->user()->isSuperAdmin();
@endphp
<x-app-layout :title="$run->run_number">
    <x-slot name="header">
        <x-page-header :title="'Depreciation · '.$run->period_label" icon="calculator" :crumbs="['Depreciation' => route('depreciation.index'), $run->run_number => null]">
            <a href="{{ route('reports.show', ['depreciation-detail', 'run' => $run->id, 'export' => 'xlsx']) }}" class="btn-secondary"><x-icon name="download" class="h-4 w-4"/> Excel</a>
            @if ($canRun && $canReverse)
                <div x-data="{ open: false }">
                    <button type="button" @click="open = true" class="btn-secondary text-rose-600"><x-icon name="restore" class="h-4 w-4"/> Reverse</button>
                    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" @keydown.escape.window="open = false">
                        <form method="POST" action="{{ route('depreciation.reverse', $run) }}" class="card w-full max-w-md p-6" @click.outside="open = false">
                            @csrf
                            <div class="text-base font-semibold text-slate-900">Reverse {{ $run->run_number }}?</div>
                            <p class="mt-1 text-sm text-slate-500">All {{ $run->total_assets }} entries are cancelled and asset balances restored. You can then re-run {{ $run->period_label }}.</p>
                            <label class="form-label mt-4">Reason</label>
                            <textarea name="reason" required minlength="5" rows="2" class="form-input"></textarea>
                            <div class="mt-4 flex justify-end gap-2"><button type="button" class="btn-secondary" @click="open = false">Cancel</button><button class="btn-danger">Reverse run</button></div>
                        </form>
                    </div>
                </div>
            @endif
            @if ($isAdmin && $canFinalize)
                <form method="POST" action="{{ route('depreciation.finalize', $run) }}" onsubmit="return confirm('Finalise {{ $run->period_label }}? The period will be locked.')">@csrf
                    <button class="btn-success"><x-icon name="shield" class="h-4 w-4"/> Finalise & lock</button></form>
            @endif
        </x-page-header>
    </x-slot>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="card p-5"><div class="stat-label">Status</div><div class="mt-1"><x-pill :value="$run->status" class="text-sm"/> <x-pill :value="$run->run_type"/></div><div class="mt-2 text-xs text-slate-400">{{ $run->run_number }}</div></div>
        <div class="card p-5"><div class="stat-label">Period</div><div class="text-base font-semibold text-slate-800">{{ fmt_date($run->period_start) }} – {{ fmt_date($run->period_end) }}</div><div class="mt-1 text-xs text-slate-400">{{ $run->financialYear?->name }}</div></div>
        <div class="card p-5"><div class="stat-label">Assets</div><div class="stat-value">{{ $run->total_assets }}</div></div>
        <div class="card p-5 ring-1 ring-brand-200"><div class="stat-label">Depreciation</div><div class="stat-value text-brand-700">{{ inr($run->total_depreciation) }}</div></div>
        <div class="card p-5"><div class="stat-label">Audit</div><div class="text-xs text-slate-600">Posted by {{ $run->runBy?->name ?? 'system' }}<br>{{ $run->run_at?->format('d M Y, h:i A') }}</div>
            @if ($run->finalized_at)<div class="mt-1 text-xs text-emerald-700">Finalised by {{ $run->finalizedBy?->name ?? 'system' }} · {{ $run->finalized_at->format('d M Y') }}</div>@endif
            @if ($run->reversed_at)<div class="mt-1 text-xs text-rose-600">Reversed {{ $run->reversed_at->format('d M Y') }}</div>@endif
        </div>
    </div>
    @if ($run->remarks)<div class="mb-6 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 whitespace-pre-line">{{ $run->remarks }}</div>@endif

    <div class="grid gap-6 xl:grid-cols-4">
        <div class="card xl:col-span-3">
            <form method="GET" class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 py-3">
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Asset code or name" class="form-input w-56 py-1.5 text-sm">
                <select name="category" class="form-input w-48 py-1.5 text-sm"><option value="">All categories</option>@foreach ($categories as $id => $n)<option value="{{ $id }}" @selected(request('category') == $id)>{{ $n }}</option>@endforeach</select>
                <select name="cost_center" class="form-input w-48 py-1.5 text-sm"><option value="">All cost centers</option>@foreach ($costCenters as $id => $n)<option value="{{ $id }}" @selected(request('cost_center') == $id)>{{ $n }}</option>@endforeach</select>
                <button class="btn-primary py-1.5">Filter</button>
            </form>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Asset</th><th>Cost center</th><th>Method</th><th class="text-right">Rate %</th><th class="text-right">Days</th><th class="text-right">Opening acc.</th><th class="text-right">Depreciation</th><th class="text-right">Closing WDV</th></tr></thead>
                    <tbody>
                    @foreach ($entries as $e)
                        <tr class="{{ $e->trashed() ? 'line-through opacity-50' : '' }}">
                            <td><a href="{{ route('assets.show', $e->asset_id) }}" class="font-mono text-xs font-semibold text-brand-700">{{ $e->asset?->asset_code }}</a><div class="text-sm text-slate-700">{{ $e->asset?->name }}</div></td>
                            <td class="text-xs text-slate-500">{{ $e->costCenter?->code ?? '—' }}@if (! empty($e->calculation_details['cost_center_split']))<div class="text-amber-600">split</div>@endif</td>
                            <td class="text-xs">{{ $e->method_code }}</td>
                            <td class="text-right text-xs tabular-nums">{{ $e->rate !== null ? number_format((float) $e->rate, 2) : '—' }}</td>
                            <td class="text-right text-xs tabular-nums">{{ $e->days }}</td>
                            <td class="text-right tabular-nums">{{ inr($e->opening_accumulated_depreciation) }}</td>
                            <td class="text-right font-semibold tabular-nums text-amber-700">{{ inr($e->depreciation_amount) }}</td>
                            <td class="text-right tabular-nums">{{ inr($e->closing_wdv) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot class="bg-slate-50 text-sm font-semibold"><tr><td colspan="6" class="px-4 py-3">{{ $totals->n }} entries</td><td class="px-4 py-3 text-right tabular-nums">{{ inr($totals->dep) }}</td><td class="px-4 py-3 text-right tabular-nums">{{ inr($totals->wdv) }}</td></tr></tfoot>
                </table>
            </div>
            <div class="border-t border-slate-100 px-5 py-3">{{ $entries->links() }}</div>
        </div>
        <div class="card self-start">
            <div class="card-header"><div class="card-title">By category</div></div>
            <ul class="divide-y divide-slate-100">
                @foreach ($byCategory as $c)
                    <li class="flex items-center justify-between px-5 py-2.5 text-sm"><span class="text-slate-700">{{ $c->name }} <span class="text-xs text-slate-400">({{ $c->n }})</span></span><span class="tabular-nums font-medium">{{ inr_short($c->dep) }}</span></li>
                @endforeach
            </ul>
        </div>
    </div>
</x-app-layout>
