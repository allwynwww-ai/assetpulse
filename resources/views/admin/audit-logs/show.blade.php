<x-app-layout title="Audit entry">
    <x-slot name="header">
        <x-page-header :title="\Illuminate\Support\Str::headline($log->event).' · '.($log->auditable_label ?? $log->model_name)" icon="shield"
                       :crumbs="['Admin' => null, 'Audit Trail' => route('admin.audit-logs.index'), '#'.$log->id => null]">
            <a href="{{ route('admin.audit-logs.index') }}" class="btn-secondary">Back</a>
        </x-page-header>
    </x-slot>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-1 self-start">
            <div class="card-header"><div class="card-title">Context</div></div>
            <dl class="card-body space-y-3">
                <div><dt class="dl-label">When</dt><dd class="dl-value">{{ $log->created_at?->timezone(config('app.timezone'))->format('d M Y, h:i:s A') }} IST</dd></div>
                <div><dt class="dl-label">User</dt><dd class="dl-value">{{ $log->user_name ?? 'system' }}</dd></div>
                <div><dt class="dl-label">Record</dt><dd class="dl-value">{{ $log->model_name }} #{{ $log->auditable_id ?? '—' }}</dd></div>
                <div><dt class="dl-label">IP address</dt><dd class="dl-value font-mono">{{ $log->ip_address ?? 'console' }}</dd></div>
                <div><dt class="dl-label">URL</dt><dd class="dl-value break-all text-xs">{{ $log->url }}</dd></div>
                <div><dt class="dl-label">User agent</dt><dd class="dl-value break-all text-xs text-slate-500">{{ $log->user_agent ?? '—' }}</dd></div>
            </dl>
        </div>
        <div class="card lg:col-span-2">
            <div class="card-header"><div class="card-title">Values</div></div>
            @php $keys = collect(array_keys($log->old_values ?? []))->merge(array_keys($log->new_values ?? []))->unique(); @endphp
            <table class="table">
                <thead><tr><th>Field</th><th>Old value</th><th>New value</th></tr></thead>
                <tbody>
                @forelse ($keys as $k)
                    @php
                        $o = $log->old_values[$k] ?? null; $n = $log->new_values[$k] ?? null;
                        $fmt = fn ($v) => is_array($v) ? json_encode($v) : (is_bool($v) ? ($v ? 'true' : 'false') : $v);
                    @endphp
                    <tr>
                        <td class="font-medium text-slate-700">{{ \Illuminate\Support\Str::headline($k) }}</td>
                        <td class="text-rose-600 break-all">{{ $fmt($o) ?? '—' }}</td>
                        <td class="text-mint-700 break-all">{{ $fmt($n) ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-8 text-center text-sm text-slate-400">No field values recorded for this event.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
