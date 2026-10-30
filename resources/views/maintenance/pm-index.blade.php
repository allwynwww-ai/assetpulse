@php $canWrite = auth()->user()->canManageAssets(); @endphp
<x-app-layout title="Preventive maintenance">
    <x-slot name="header">
        <x-page-header title="Preventive Maintenance" subtitle="Schedules by calendar, running hours or mould shots, with next-due tracking." icon="wrench" :crumbs="['Maintenance' => null, 'PM schedules' => null]">
            <a href="{{ route('work-orders.index') }}" class="btn-secondary"><x-icon name="clipboard" class="h-4 w-4"/> Work orders</a>
            @if ($canWrite)<a href="{{ route('pm-schedules.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4"/> New schedule</a>@endif
        </x-page-header>
    </x-slot>

    <div class="mb-5 grid gap-4 sm:grid-cols-3">
        @foreach ([['overdue', 'Overdue', 'rose'], ['due30', 'Due in next 30 days', 'amber'], ['all', 'Active schedules', 'brand']] as [$k, $l, $c])
            <a href="{{ route('pm-schedules.index', ['view' => $k]) }}" class="card flex items-center gap-4 p-5 transition hover:shadow-soft {{ $view === $k ? 'ring-2 ring-'.$c.'-300' : '' }}">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-{{ $c }}-50 text-{{ $c }}-600"><x-icon name="{{ $k === 'all' ? 'wrench' : 'calendar' }}" class="h-5 w-5"/></div>
                <div><div class="stat-label">{{ $l }}</div><div class="stat-value text-{{ $c }}-700">{{ $counts[$k] }}</div></div>
            </a>
        @endforeach
    </div>

    <div class="card">
        <form method="GET" class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 py-3">
            <input type="hidden" name="view" value="{{ $view }}">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Schedule, asset code or name…" class="form-input min-w-[14rem] flex-1 py-1.5 text-sm">
            <button class="btn-primary py-1.5">Search</button>
            <a href="{{ route('pm-schedules.index', ['view' => 'inactive']) }}" class="btn-ghost py-1.5 text-xs">Inactive</a>
        </form>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Asset</th><th>Schedule</th><th>Frequency</th><th>Last done</th><th>Next due</th><th>Meter</th><th class="text-right">Est. cost</th><th></th></tr></thead>
                <tbody>
                @forelse ($schedules as $pm)
                    @php
                        $days = $pm->next_due_date ? (int) now()->startOfDay()->diffInDays($pm->next_due_date, false) : null;
                        $state = $days === null ? null : ($days < 0 ? 'overdue' : ($days <= 30 ? 'due' : 'ok'));
                    @endphp
                    <tr>
                        <td><a href="{{ route('assets.show', $pm->asset_id) }}" class="font-mono text-xs font-semibold text-brand-700">{{ $pm->asset?->asset_code }}</a><div class="line-clamp-2 min-w-[11rem] max-w-[16rem] text-sm text-slate-700" title="{{ $pm->asset?->name }}">{{ $pm->asset?->name }}</div><div class="text-xs text-slate-400">{{ $pm->asset?->location?->name }}</div></td>
                        <td class="min-w-[14rem]"><div class="line-clamp-2 text-sm font-medium text-slate-800" title="{{ $pm->title }}">{{ $pm->title }}</div><div class="text-xs text-slate-400">{{ $pm->code }} {{ $pm->assigned_to ? '· '.$pm->assigned_to : '' }}</div></td>
                        <td class="min-w-[7rem] text-sm">Every {{ number_format($pm->frequency_interval) }} {{ strtolower(\App\Models\PmSchedule::FREQUENCY_UNITS[$pm->frequency_unit] ?? $pm->frequency_unit) }}</td>
                        <td class="whitespace-nowrap text-sm">{{ fmt_date($pm->last_done_date) }}</td>
                        <td class="whitespace-nowrap">
                            <div class="text-sm font-medium">{{ fmt_date($pm->next_due_date) }}</div>
                            @if ($state && $pm->is_active)<x-pill :value="$state">{{ $days < 0 ? abs($days).' d overdue' : ($days === 0 ? 'Due today' : 'in '.$days.' d') }}</x-pill>@endif
                        </td>
                        <td class="text-xs text-slate-500">@if (in_array($pm->frequency_unit, ['running_hours', 'shots'])){{ number_format((float) $pm->meter_current_reading) }}<div class="text-slate-400">{{ number_format(max(0, $pm->frequency_interval - ((float) $pm->meter_current_reading - (float) $pm->meter_last_service_reading))) }} left · {{ number_format((float) $pm->meter_daily_average) }}/day</div>@else — @endif</td>
                        <td class="text-right tabular-nums text-sm">{{ inr($pm->estimated_cost, 0) }}</td>
                        <td class="whitespace-nowrap text-right">
                            @if ($canWrite && $pm->is_active)
                                <a href="{{ route('work-orders.create', ['pm' => $pm->id]) }}" class="btn-secondary btn-sm" title="Raise work order"><x-icon name="clipboard" class="h-3.5 w-3.5"/> WO</a>
                                <a href="{{ route('pm-schedules.edit', $pm) }}" class="btn-ghost btn-sm" title="Edit"><x-icon name="pencil" class="h-4 w-4"/></a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-12 text-center text-sm text-slate-400">No schedules in this view.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 px-5 py-3">{{ $schedules->links() }}</div>
    </div>
</x-app-layout>
