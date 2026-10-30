<x-app-layout title="Transfers">
    <x-slot name="header">
        <x-page-header title="Asset Transfers" subtitle="Movements between plants, departments and cost centers." icon="arrows" :crumbs="['Operations' => null, 'Transfers' => null]">
            <a href="{{ route('reports.show', 'asset-movement') }}" class="btn-secondary"><x-icon name="chart" class="h-4 w-4"/> Movement report</a>
            @can('manage-assets')<a href="{{ route('transfers.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4"/> New transfer</a>@endcan
        </x-page-header>
    </x-slot>
    <div class="card">
        <form method="GET" class="flex flex-wrap items-end gap-2 border-b border-slate-100 px-5 py-3">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Transfer no., asset, reason…" class="form-input min-w-[14rem] flex-1 py-1.5 text-sm">
            <select name="location" class="form-input w-48 py-1.5 text-sm"><option value="">Any location</option>@foreach ($locations as $id => $n)<option value="{{ $id }}" @selected(request('location') == $id)>{{ $n }}</option>@endforeach</select>
            <input type="date" name="from" value="{{ request('from') }}" class="form-input w-40 py-1.5 text-sm" title="From date">
            <input type="date" name="to" value="{{ request('to') }}" class="form-input w-40 py-1.5 text-sm" title="To date">
            <button class="btn-primary py-1.5">Filter</button>
        </form>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Transfer</th><th>Date</th><th>Asset</th><th>From</th><th></th><th>To</th><th>Reason</th><th>By</th></tr></thead>
                <tbody>
                @forelse ($transfers as $t)
                    <tr>
                        <td><a href="{{ route('transfers.show', $t) }}" class="font-mono text-xs font-semibold text-brand-700">{{ $t->transfer_number }}</a>@if ($t->affects_depreciation)<div class="text-[10px] text-amber-600">CC split</div>@endif</td>
                        <td class="whitespace-nowrap text-sm">{{ fmt_date($t->transfer_date) }}</td>
                        <td><a href="{{ route('assets.show', $t->asset_id) }}" class="font-mono text-xs text-brand-700">{{ $t->asset?->asset_code }}</a><div class="max-w-[14rem] truncate text-sm text-slate-700">{{ $t->asset?->name }}</div></td>
                        <td class="text-sm"><div>{{ $t->fromLocation?->name ?? '—' }}</div><div class="text-xs text-slate-400">{{ $t->fromDepartment?->name }} · {{ $t->fromCostCenter?->code }}</div></td>
                        <td class="text-slate-300"><x-icon name="chevron-right" class="h-4 w-4"/></td>
                        <td class="text-sm"><div class="font-medium">{{ $t->toLocation?->name ?? '—' }}</div><div class="text-xs text-slate-400">{{ $t->toDepartment?->name }} · {{ $t->toCostCenter?->code }}</div></td>
                        <td class="max-w-xs truncate text-sm text-slate-600" title="{{ $t->reason }}">{{ $t->reason }}</td>
                        <td class="text-xs text-slate-500">{{ $t->requestedBy?->name ?? 'system' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-12 text-center text-sm text-slate-400">No transfers recorded.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 px-5 py-3">{{ $transfers->links() }}</div>
    </div>
</x-app-layout>
