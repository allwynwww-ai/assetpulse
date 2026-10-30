@php
    $colors = ['created' => 'emerald', 'updated' => 'sky', 'deactivated' => 'rose', 'deleted' => 'rose', 'restored' => 'amber', 'login' => 'violet', 'logout' => 'slate', 'login_failed' => 'orange'];
@endphp
<x-app-layout title="Audit Trail">
    <x-slot name="header">
        <x-page-header title="Audit Trail" icon="shield" :crumbs="['Admin' => null, 'Audit Trail' => null]"
                       :subtitle="'Immutable log of every create, change, deactivation and sign-in'.($company ? ' in '.$company->name : ' across the platform').'.'"/>
    </x-slot>

    <div class="card">
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-100 px-5 py-3.5">
            <div>
                <label class="form-label text-xs">Search</label>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Record, user or IP" class="form-input w-56 py-1.5 text-sm">
            </div>
            <div>
                <label class="form-label text-xs">Event</label>
                <select name="event" class="form-input w-36 py-1.5 text-sm">
                    <option value="">All</option>
                    @foreach ($events as $e)<option value="{{ $e }}" @selected(request('event') === $e)>{{ \Illuminate\Support\Str::headline($e) }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="form-label text-xs">Record type</label>
                <select name="model" class="form-input w-44 py-1.5 text-sm">
                    <option value="">All</option>
                    @foreach ($models as $k => $v)<option value="{{ $k }}" @selected(request('model') === $k)>{{ $v }}</option>@endforeach
                </select>
            </div>
            <div><label class="form-label text-xs">From</label><input type="date" name="from" value="{{ request('from') }}" class="form-input py-1.5 text-sm"></div>
            <div><label class="form-label text-xs">To</label><input type="date" name="to" value="{{ request('to') }}" class="form-input py-1.5 text-sm"></div>
            <button class="btn-secondary py-1.5">Filter</button>
            @if (request()->hasAny(['q', 'event', 'model', 'from', 'to']))<a href="{{ route('admin.audit-logs.index') }}" class="btn-ghost py-1.5 text-xs">Clear</a>@endif
        </form>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>When (IST)</th><th>Event</th><th>Record</th><th>User</th>@unless ($company)<th>Company</th>@endunless<th>IP address</th><th></th></tr></thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="whitespace-nowrap text-xs text-slate-500">{{ $log->created_at?->timezone(config('app.timezone'))->format('d M Y, h:i:s A') }}</td>
                        <td><span class="badge bg-{{ $colors[$log->event] ?? 'slate' }}-50 text-{{ $colors[$log->event] ?? 'slate' }}-700 ring-{{ $colors[$log->event] ?? 'slate' }}-200">{{ \Illuminate\Support\Str::headline($log->event) }}</span></td>
                        <td><div class="font-medium text-slate-800">{{ $log->auditable_label ?? '—' }}</div><div class="text-xs text-slate-400">{{ $log->model_name }}</div></td>
                        <td class="text-sm">{{ $log->user_name ?? 'system' }}</td>
                        @unless ($company)<td class="text-xs text-slate-500">{{ $log->company?->name ?? 'Platform' }}</td>@endunless
                        <td class="font-mono text-xs text-slate-500">{{ $log->ip_address ?? 'console' }}</td>
                        <td class="text-right"><a href="{{ route('admin.audit-logs.show', $log->id) }}" class="btn-ghost btn-sm"><x-icon name="eye" class="h-4 w-4"/></a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-12 text-center text-sm text-slate-400">No audit entries match.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 px-5 py-3">{{ $logs->links() }}</div>
    </div>
</x-app-layout>
