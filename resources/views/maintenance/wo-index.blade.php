@php $canWrite = auth()->user()->canManageAssets(); @endphp
<x-app-layout title="Work orders">
    <x-slot name="header">
        <x-page-header title="Work Orders" subtitle="Planned and breakdown maintenance with cost and downtime." icon="clipboard" :crumbs="['Maintenance' => null, 'Work orders' => null]">
            <a href="{{ route('pm-schedules.index') }}" class="btn-secondary"><x-icon name="calendar" class="h-4 w-4"/> PM schedules</a>
            @if ($canWrite)
                <a href="{{ route('work-orders.create', ['type' => 'breakdown']) }}" class="btn-danger"><x-icon name="flag" class="h-4 w-4"/> Report breakdown</a>
                <a href="{{ route('work-orders.create', ['type' => 'preventive']) }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4"/> New work order</a>
            @endif
        </x-page-header>
    </x-slot>
    <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card p-5"><div class="stat-label">Open work orders</div><div class="stat-value">{{ $openCount }}</div></div>
        <div class="card p-5 {{ $openBreakdowns ? 'ring-1 ring-rose-200' : '' }}"><div class="stat-label">Open breakdowns</div><div class="stat-value {{ $openBreakdowns ? 'text-rose-600' : '' }}">{{ $openBreakdowns }}</div></div>
        <div class="card p-5"><div class="stat-label">Cost (filtered)</div><div class="stat-value">{{ inr_short($totals->cost) }}</div></div>
        <div class="card p-5"><div class="stat-label">Downtime (filtered)</div><div class="stat-value">{{ number_format($totals->downtime, 1) }} h</div></div>
    </div>
    <div class="card">
        <form method="GET" class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 py-3">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="WO no., title, asset…" class="form-input min-w-[14rem] flex-1 py-1.5 text-sm">
            <select name="type" class="form-input w-40 py-1.5 text-sm"><option value="">All types</option>@foreach (\App\Models\WorkOrder::TYPES as $k => $l)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $l }}</option>@endforeach</select>
            <select name="status" class="form-input w-40 py-1.5 text-sm"><option value="">All statuses</option><option value="open" @selected(request('status') === 'open')>Not closed</option>@foreach (\App\Models\WorkOrder::STATUSES as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select>
            <input type="date" name="from" value="{{ request('from') }}" class="form-input w-40 py-1.5 text-sm"><input type="date" name="to" value="{{ request('to') }}" class="form-input w-40 py-1.5 text-sm">
            <button class="btn-primary py-1.5">Filter</button>
        </form>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>WO</th><th>Asset</th><th>Title</th><th>Type</th><th>Priority</th><th>Reported</th><th>Status</th><th class="text-right">Downtime</th><th class="text-right">Cost</th></tr></thead>
                <tbody>
                @forelse ($workOrders as $wo)
                    <tr>
                        <td><a href="{{ route('work-orders.show', $wo) }}" class="font-mono text-xs font-semibold text-brand-700">{{ $wo->wo_number }}</a></td>
                        <td><a href="{{ route('assets.show', $wo->asset_id) }}" class="font-mono text-xs text-brand-700">{{ $wo->asset?->asset_code }}</a><div class="max-w-[12rem] truncate text-xs text-slate-500">{{ $wo->asset?->name }}</div></td>
                        <td class="max-w-xs"><a href="{{ route('work-orders.show', $wo) }}" class="text-sm font-medium text-slate-800 hover:text-brand-700">{{ $wo->title }}</a></td>
                        <td><x-pill :value="$wo->type"/></td>
                        <td><x-pill :value="$wo->priority"/></td>
                        <td class="whitespace-nowrap text-sm">{{ fmt_date($wo->reported_date) }}</td>
                        <td><x-pill :value="$wo->status"/></td>
                        <td class="text-right tabular-nums text-sm">{{ (float) $wo->downtime_hours ? number_format($wo->downtime_hours, 1).' h' : '—' }}</td>
                        <td class="text-right tabular-nums text-sm">{{ (float) $wo->total_cost ? inr($wo->total_cost, 0) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="py-12 text-center text-sm text-slate-400">No work orders found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 px-5 py-3">{{ $workOrders->links() }}</div>
    </div>
</x-app-layout>
