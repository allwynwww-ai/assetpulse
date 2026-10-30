@php $t = $transfer; @endphp
<x-app-layout :title="$t->transfer_number">
    <x-slot name="header">
        <x-page-header :title="'Transfer '.$t->transfer_number" icon="arrows" :crumbs="['Transfers' => route('transfers.index'), $t->transfer_number => null]">
            <a href="{{ route('assets.show', $t->asset_id) }}" class="btn-secondary"><x-icon name="cube" class="h-4 w-4"/> View asset</a>
        </x-page-header>
    </x-slot>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="card-header"><div class="card-title">{{ $t->asset?->asset_code }} · {{ $t->asset?->name }}</div><x-pill :value="$t->status"/></div>
            <div class="card-body grid gap-6 sm:grid-cols-2">
                @foreach (['From' => ['fromLocation', 'fromDepartment', 'fromCostCenter', 'from_custodian'], 'To' => ['toLocation', 'toDepartment', 'toCostCenter', 'to_custodian']] as $h => [$l, $d, $c, $cu])
                    <div class="rounded-xl border border-slate-200 p-4 {{ $h === 'To' ? 'bg-brand-50/40 border-brand-200' : '' }}">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $h }}</div>
                        <div class="mt-1 text-base font-semibold text-slate-800">{{ $t->$l?->name ?? '—' }}</div>
                        <div class="text-sm text-slate-600">{{ $t->$d?->name ?? '—' }}</div>
                        <div class="text-sm text-slate-500">{{ $t->$c ? $t->$c->code.' – '.$t->$c->name : '—' }}</div>
                        <div class="mt-1 text-xs text-slate-400">Custodian: {{ $t->$cu ?? '—' }}</div>
                    </div>
                @endforeach
                <div><div class="dl-label">Transfer date</div><div class="dl-value">{{ fmt_date($t->transfer_date) }}</div></div>
                <div><div class="dl-label">Depreciation split by cost center</div><div class="dl-value">{{ $t->affects_depreciation ? 'Yes – from transfer date' : 'No' }}</div></div>
                <div class="sm:col-span-2"><div class="dl-label">Reason</div><div class="dl-value whitespace-pre-line">{{ $t->reason }}</div></div>
                @if ($t->remarks)<div class="sm:col-span-2"><div class="dl-label">Remarks</div><div class="dl-value">{{ $t->remarks }}</div></div>@endif
                <div><div class="dl-label">Recorded by</div><div class="dl-value">{{ $t->requestedBy?->name ?? 'system' }} · {{ $t->created_at?->format('d M Y, h:i A') }}</div></div>
            </div>
        </div>
        <div class="card self-start"><div class="card-header"><div class="card-title">Audit</div></div><div class="card-body">@include('partials.audit-timeline', ['logs' => $t->auditLogs])</div></div>
    </div>
</x-app-layout>
