@php
    $canRun = auth()->user()->canManageAssets();
    $isAdmin = auth()->user()->isCompanyAdmin() || auth()->user()->isSuperAdmin();
@endphp
<x-app-layout title="Depreciation">
    <x-slot name="header">
        <x-page-header title="Depreciation Runs" subtitle="Preview → post → finalise. Finalised periods are locked against changes." icon="calculator" :crumbs="['Operations' => null, 'Depreciation' => null]">
            <a href="{{ route('reports.show', 'depreciation-summary') }}" class="btn-secondary"><x-icon name="chart" class="h-4 w-4"/> Schedule</a>
            @if ($canRun)
                <a href="{{ route('depreciation.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4"/> Run depreciation</a>
            @endif
        </x-page-header>
    </x-slot>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card p-5"><div class="stat-label">Depreciation this FY</div><div class="stat-value">{{ inr_short($stats['fy_total']) }}</div><div class="mt-1 text-xs text-slate-400">Posted & finalised entries</div></div>
        <div class="card p-5"><div class="stat-label">Last period posted</div><div class="stat-value">{{ $stats['last']?->period_label ?? '—' }}</div><div class="mt-1 text-xs text-slate-400">{{ $stats['last'] ? $stats['last']->run_number.' · '.inr_short($stats['last']->total_depreciation) : 'No runs yet' }}</div></div>
        <div class="card p-5"><div class="stat-label">Locked through</div><div class="stat-value">{{ $stats['locked_through'] ? fmt_date($stats['locked_through']) : '—' }}</div><div class="mt-1 text-xs text-slate-400">Finalised periods are read-only</div></div>
        <div class="card p-5 {{ $stats['posted_open'] ? 'ring-1 ring-amber-200' : '' }}"><div class="stat-label">Awaiting finalisation</div><div class="stat-value {{ $stats['posted_open'] ? 'text-amber-700' : '' }}">{{ $stats['posted_open'] }}</div><div class="mt-1 text-xs text-slate-400">Next period: {{ $nextLabel }}</div></div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="card-header">
                <div class="card-title">Runs</div>
                <form method="GET" class="flex gap-2">
                    <select name="type" class="form-input py-1 text-xs" onchange="this.form.submit()">
                        <option value="">All types</option>
                        <option value="periodic" @selected(request('type') === 'periodic')>Periodic</option>
                        <option value="disposal" @selected(request('type') === 'disposal')>Disposal</option>
                    </select>
                    <select name="status" class="form-input py-1 text-xs" onchange="this.form.submit()">
                        <option value="">All statuses</option>
                        @foreach (['posted', 'finalized', 'reversed'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s === 'finalized' ? 'finalised' : $s) }}</option>@endforeach
                    </select>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Run</th><th>Period</th><th>Type</th><th class="text-right">Assets</th><th class="text-right">Depreciation</th><th>Status</th><th>Posted by</th></tr></thead>
                    <tbody>
                    @forelse ($runs as $r)
                        <tr class="{{ $r->status === 'reversed' ? 'opacity-60' : '' }}">
                            <td><a href="{{ route('depreciation.show', $r) }}" class="font-mono text-xs font-semibold text-brand-700 hover:underline">{{ $r->run_number }}</a></td>
                            <td class="whitespace-nowrap text-sm"><div class="font-medium text-slate-800">{{ $r->period_label }}</div><div class="text-xs text-slate-400">{{ fmt_date($r->period_start) }} – {{ fmt_date($r->period_end) }}</div></td>
                            <td><x-pill :value="$r->run_type"/></td>
                            <td class="text-right tabular-nums">{{ $r->total_assets }}</td>
                            <td class="text-right tabular-nums font-semibold">{{ inr($r->total_depreciation) }}</td>
                            <td><x-pill :value="$r->status"/></td>
                            <td class="text-xs text-slate-500">{{ $r->runBy?->name ?? 'system' }}<div class="text-slate-400">{{ $r->run_at?->format('d M Y') }}</div></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-12 text-center text-sm text-slate-400">No depreciation runs yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-5 py-3">{{ $runs->links() }}</div>
        </div>

        <div class="space-y-6">
            <div class="card">
                <div class="card-header"><div class="card-title">Period locks</div><x-icon name="shield" class="h-4 w-4 text-slate-400"/></div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($locks as $l)
                        <li class="flex items-center gap-3 px-5 py-2.5" x-data="{ open: false }">
                            <div class="flex-1">
                                <div class="text-sm font-medium text-slate-800">{{ $l->period_label }}</div>
                                <div class="text-xs text-slate-400">{{ $l->is_locked ? 'Locked '.$l->locked_at?->format('d M Y').' by '.($l->lockedBy?->name ?? 'system') : 'Unlocked '.$l->unlocked_at?->format('d M Y') }}</div>
                            </div>
                            <x-pill :value="$l->is_locked ? 'locked' : 'unlocked'"/>
                            @if ($isAdmin && $l->is_locked && $loop->first)
                                <button type="button" @click="open = true" class="btn-ghost btn-sm text-amber-700" title="Unlock">Unlock</button>
                                <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" @keydown.escape.window="open = false">
                                    <form method="POST" action="{{ route('depreciation.locks.unlock', $l) }}" class="card w-full max-w-md p-6" @click.outside="open = false">
                                        @csrf
                                        <div class="text-base font-semibold text-slate-900">Unlock {{ $l->period_label }}?</div>
                                        <p class="mt-1 text-sm text-slate-500">The run returns to “posted” so it can be reversed and re-run. This is recorded in the audit trail.</p>
                                        <label class="form-label mt-4">Reason</label>
                                        <textarea name="reason" required minlength="5" rows="2" class="form-input" placeholder="e.g. Correct capitalisation date of IMM-0004"></textarea>
                                        <div class="mt-4 flex justify-end gap-2"><button type="button" class="btn-secondary" @click="open = false">Cancel</button><button class="btn-danger">Unlock period</button></div>
                                    </form>
                                </div>
                            @endif
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-sm text-slate-400">No periods locked yet.</li>
                    @endforelse
                </ul>
                @if ($isAdmin)<div class="border-t border-slate-100 px-5 py-2.5 text-xs text-slate-400">Only the latest locked period can be unlocked (Company Admin).</div>@endif
            </div>
            <div class="card">
                <div class="card-header"><div class="card-title">How it works</div><x-icon name="info" class="h-4 w-4 text-slate-400"/></div>
                <ol class="card-body list-decimal space-y-1.5 pl-9 text-sm text-slate-600">
                    <li><b>Preview</b> the next period – nothing is saved.</li>
                    <li><b>Post</b> – entries are written and asset NBVs update. A posted run can still be reversed.</li>
                    <li><b>Finalise</b> (Company Admin) – the period is locked: no re-runs, back-dated transfers/disposals or cost changes.</li>
                </ol>
            </div>
        </div>
    </div>
</x-app-layout>
