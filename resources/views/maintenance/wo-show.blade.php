@php
    $canWrite = auth()->user()->canManageAssets();
    $open = ! in_array($wo->status, ['completed', 'cancelled']);
    $meter = $wo->pmSchedule && in_array($wo->pmSchedule->frequency_unit, ['running_hours', 'shots']);
@endphp
<x-app-layout :title="$wo->wo_number">
    <x-slot name="header">
        <x-page-header :title="$wo->title" icon="clipboard" :crumbs="['Work orders' => route('work-orders.index'), $wo->wo_number => null]">
            @if ($canWrite && $open)
                <a href="{{ route('work-orders.edit', $wo) }}" class="btn-secondary"><x-icon name="pencil" class="h-4 w-4"/> Edit</a>
                @if ($wo->status === 'open' || $wo->status === 'on_hold')
                    <form method="POST" action="{{ route('work-orders.start', $wo) }}">@csrf<button class="btn-primary">Start work</button></form>
                @endif
            @endif
        </x-page-header>
        <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
            <span class="rounded-md bg-brand-50 px-2 py-0.5 font-mono text-xs font-semibold text-brand-700 ring-1 ring-brand-200">{{ $wo->wo_number }}</span>
            <x-pill :value="$wo->status"/> <x-pill :value="$wo->type"/> <x-pill :value="$wo->priority">{{ ucfirst($wo->priority) }} priority</x-pill>
        </div>
    </x-slot>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="card">
                <div class="card-header"><div class="card-title">Details</div></div>
                <dl class="card-body grid gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-3"><dt class="dl-label">Asset</dt><dd class="dl-value"><a href="{{ route('assets.show', $wo->asset_id) }}" class="text-brand-700 hover:underline">{{ $wo->asset?->asset_code }} – {{ $wo->asset?->name }}</a> <span class="text-xs text-slate-400">{{ $wo->asset?->location?->name }} · {{ $wo->asset?->status?->name }}</span></dd></div>
                    <div><dt class="dl-label">Reported</dt><dd class="dl-value">{{ fmt_date($wo->reported_date) }}</dd></div>
                    <div><dt class="dl-label">Scheduled</dt><dd class="dl-value">{{ fmt_date($wo->scheduled_date) }}</dd></div>
                    <div><dt class="dl-label">PM schedule</dt><dd class="dl-value">{{ $wo->pmSchedule?->title ?? '—' }}</dd></div>
                    <div><dt class="dl-label">Started</dt><dd class="dl-value">{{ $wo->started_at?->format('d M Y, h:i A') ?? '—' }}</dd></div>
                    <div><dt class="dl-label">Completed</dt><dd class="dl-value">{{ $wo->completed_at?->format('d M Y, h:i A') ?? '—' }}</dd></div>
                    <div><dt class="dl-label">Assigned</dt><dd class="dl-value">{{ $wo->assigned_to ?? '—' }}{{ $wo->vendor ? ' · '.$wo->vendor->name : '' }}</dd></div>
                    @if ($wo->description)<div class="sm:col-span-3"><dt class="dl-label">Description</dt><dd class="dl-value whitespace-pre-line">{{ $wo->description }}</dd></div>@endif
                    @if ($wo->root_cause)<div class="sm:col-span-3"><dt class="dl-label">Root cause</dt><dd class="dl-value whitespace-pre-line">{{ $wo->root_cause }}</dd></div>@endif
                    @if ($wo->action_taken)<div class="sm:col-span-3"><dt class="dl-label">Action taken</dt><dd class="dl-value whitespace-pre-line">{{ $wo->action_taken }}</dd></div>@endif
                </dl>
            </div>
            <div class="grid gap-4 sm:grid-cols-5">
                @foreach (['Labour' => $wo->labour_cost, 'Parts' => $wo->parts_cost, 'External' => $wo->external_cost, 'Total cost' => $wo->total_cost] as $l => $v)
                    <div class="card p-4"><div class="stat-label">{{ $l }}</div><div class="text-lg font-semibold tabular-nums {{ $l === 'Total cost' ? 'text-brand-700' : 'text-slate-800' }}">{{ inr($v, 0) }}</div></div>
                @endforeach
                <div class="card p-4"><div class="stat-label">Downtime</div><div class="text-lg font-semibold">{{ number_format((float) $wo->downtime_hours, 1) }} h</div></div>
            </div>

            @if ($canWrite && $open)
                <form method="POST" action="{{ route('work-orders.complete', $wo) }}" class="card">
                    @csrf
                    <div class="card-header"><div class="card-title">Complete work order</div></div>
                    <div class="card-body grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="sm:col-span-2"><label class="form-label">Completed at *</label><input type="datetime-local" name="completed_at" value="{{ old('completed_at', now()->format('Y-m-d\TH:i')) }}" max="{{ now()->format('Y-m-d\TH:i') }}" class="form-input" required>@error('completed_at')<p class="form-error">{{ $message }}</p>@enderror</div>
                        <div class="sm:col-span-2"><label class="form-label">Downtime (hours)</label><input type="number" step="0.1" min="0" name="downtime_hours" value="{{ old('downtime_hours', $wo->downtime_hours) }}" class="form-input"></div>
                        @foreach (['labour_cost' => 'Labour (₹)', 'parts_cost' => 'Parts (₹)', 'external_cost' => 'External (₹)'] as $f => $l)
                            <div><label class="form-label">{{ $l }}</label><input type="number" step="0.01" min="0" name="{{ $f }}" value="{{ old($f, $wo->$f) }}" class="form-input">@error($f)<p class="form-error">{{ $message }}</p>@enderror</div>
                        @endforeach
                        @if ($meter)<div><label class="form-label">Meter reading now</label><input type="number" step="0.01" min="0" name="meter_reading" value="{{ old('meter_reading', $wo->pmSchedule->meter_current_reading) }}" class="form-input"></div>@endif
                        <div class="sm:col-span-2 lg:col-span-4"><label class="form-label">Root cause {{ $wo->type === 'breakdown' ? '*' : '' }}</label><textarea name="root_cause" rows="2" class="form-input">{{ old('root_cause', $wo->root_cause) }}</textarea>@error('root_cause')<p class="form-error">{{ $message }}</p>@enderror</div>
                        <div class="sm:col-span-2 lg:col-span-4"><label class="form-label">Action taken *</label><textarea name="action_taken" rows="2" class="form-input" required>{{ old('action_taken', $wo->action_taken) }}</textarea>@error('action_taken')<p class="form-error">{{ $message }}</p>@enderror</div>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 px-5 py-4">
                        <span class="text-xs text-slate-400">{{ $wo->pmSchedule ? 'Completing updates the PM schedule’s last-done and next-due dates.' : '' }}</span>
                        <div class="flex gap-2"><button type="button" class="btn-ghost text-rose-600" onclick="document.getElementById('cancel-wo').classList.toggle('hidden')">Cancel WO…</button><button class="btn-success"><x-icon name="check" class="h-4 w-4"/> Mark completed</button></div>
                    </div>
                </form>
                <form id="cancel-wo" method="POST" action="{{ route('work-orders.cancel', $wo) }}" class="card hidden p-5">
                    @csrf
                    <label class="form-label">Reason for cancelling *</label>
                    <div class="flex gap-2"><input name="reason" required minlength="3" class="form-input"><button class="btn-danger">Cancel work order</button></div>
                </form>
            @endif
        </div>
        <div class="card self-start"><div class="card-header"><div class="card-title">History</div></div><div class="card-body">@include('partials.audit-timeline', ['logs' => $wo->auditLogs])</div></div>
    </div>
</x-app-layout>
