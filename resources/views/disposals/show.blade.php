<x-app-layout :title="$d->disposal_number">
    <x-slot name="header">
        <x-page-header :title="'Disposal '.$d->disposal_number" icon="trash" :crumbs="['Disposals' => route('disposals.index'), $d->disposal_number => null]">
            <a href="{{ route('assets.show', $d->asset_id) }}" class="btn-secondary"><x-icon name="cube" class="h-4 w-4"/> View asset</a>
        </x-page-header>
    </x-slot>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="grid gap-4 sm:grid-cols-4">
                <div class="card p-4"><div class="stat-label">Gross block</div><div class="text-lg font-semibold tabular-nums">{{ inr($d->gross_block_at_disposal, 0) }}</div></div>
                <div class="card p-4"><div class="stat-label">Acc. depreciation</div><div class="text-lg font-semibold tabular-nums">{{ inr($d->accumulated_depreciation_at_disposal, 0) }}</div><div class="text-[11px] text-slate-400">incl. {{ inr($d->depreciation_upto_disposal) }} pro-rata</div></div>
                <div class="card p-4"><div class="stat-label">NBV</div><div class="text-lg font-semibold tabular-nums">{{ inr($d->nbv_at_disposal, 0) }}</div></div>
                <div class="card p-4 {{ $d->profit_loss < 0 ? 'bg-rose-50' : 'bg-emerald-50' }}"><div class="stat-label">{{ $d->profit_loss < 0 ? 'Loss' : 'Profit' }}</div><div class="text-lg font-semibold tabular-nums {{ $d->profit_loss < 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ inr(abs($d->profit_loss), 0) }}</div></div>
            </div>
            <div class="card">
                <div class="card-header"><div class="card-title">{{ $d->asset?->asset_code }} · {{ $d->asset?->name }}</div><x-pill :value="$d->disposal_type"/></div>
                <dl class="card-body grid gap-4 sm:grid-cols-3">
                    <div><dt class="dl-label">Disposal date</dt><dd class="dl-value">{{ fmt_date($d->disposal_date) }}</dd></div>
                    <div><dt class="dl-label">Category</dt><dd class="dl-value">{{ $d->asset?->category?->name }}</dd></div>
                    <div><dt class="dl-label">Location</dt><dd class="dl-value">{{ $d->asset?->location?->name }}</dd></div>
                    <div><dt class="dl-label">Sale value</dt><dd class="dl-value">{{ inr($d->sale_value) }}</dd></div>
                    <div><dt class="dl-label">Cost of disposal</dt><dd class="dl-value">{{ inr($d->disposal_cost) }}</dd></div>
                    <div><dt class="dl-label">Net proceeds</dt><dd class="dl-value">{{ inr($d->sale_value - $d->disposal_cost) }}</dd></div>
                    <div><dt class="dl-label">Buyer</dt><dd class="dl-value">{{ $d->buyer_name ?? '—' }}</dd></div>
                    <div><dt class="dl-label">GSTIN</dt><dd class="dl-value font-mono text-xs">{{ $d->buyer_gstin ?? '—' }}</dd></div>
                    <div><dt class="dl-label">Invoice</dt><dd class="dl-value">{{ $d->sale_invoice_number ?? '—' }}</dd></div>
                    <div class="sm:col-span-3"><dt class="dl-label">Reason</dt><dd class="dl-value whitespace-pre-line">{{ $d->reason }}</dd></div>
                    <div class="sm:col-span-3"><dt class="dl-label">Recorded</dt><dd class="dl-value">{{ $d->creator?->name ?? 'system' }} · {{ $d->created_at?->format('d M Y, h:i A') }}</dd></div>
                </dl>
                <div class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">Profit / (loss) = net proceeds {{ inr($d->sale_value - $d->disposal_cost) }} − NBV {{ inr($d->nbv_at_disposal) }} = <b>{{ inr($d->profit_loss) }}</b></div>
            </div>
        </div>
        <div class="card self-start"><div class="card-header"><div class="card-title">Audit</div></div><div class="card-body">@include('partials.audit-timeline', ['logs' => $d->auditLogs])</div></div>
    </div>
</x-app-layout>
