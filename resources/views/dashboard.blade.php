<x-app-layout title="Dashboard">
    <x-slot name="header">
        <x-page-header title="Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}"
                       :subtitle="($currentCompany?->name ?? '').($currentFy ? ' · '.$currentFy->name : '')" icon="home">
            @can('manage-assets')<a href="{{ route('assets.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4"/> Add asset</a>@endcan
        </x-page-header>
    </x-slot>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card p-5"><div class="stat-label">Assets in register</div><div class="stat-value">{{ number_format($totals->n) }}</div></div>
        <div class="card p-5"><div class="stat-label">Gross block</div><div class="stat-value">{{ inr_short($totals->cost) }}</div><div class="mt-1 text-xs text-slate-400">{{ inr($totals->cost, 0) }}</div></div>
        <div class="card p-5"><div class="stat-label">Accumulated depreciation</div><div class="stat-value text-amber-700">{{ inr_short($totals->acc) }}</div><div class="mt-1 text-xs text-slate-400">{{ inr($totals->acc, 0) }}</div></div>
        <div class="card p-5 ring-1 ring-mint-200"><div class="stat-label">Net block (NBV)</div><div class="stat-value text-mint-700">{{ inr_short($totals->nbv) }}</div><div class="mt-1 text-xs text-slate-400">{{ inr($totals->nbv, 0) }}</div></div>
    </div>

    <div class="grid gap-6 xl:grid-cols-5">
        <div class="card xl:col-span-3">
            <div class="card-header"><div class="card-title">Gross block by category</div><a href="{{ route('admin.asset-categories.index') }}" class="text-xs font-medium text-brand-600 hover:underline">Categories →</a></div>
            <div class="card-body space-y-4">
                @php $max = max(1, (float) $byCategory->max('cost')); @endphp
                @forelse ($byCategory as $c)
                    <div>
                        <div class="mb-1 flex justify-between text-sm"><a href="{{ route('assets.index', ['category' => $c->id]) }}" class="font-medium text-slate-700 hover:text-brand-700">{{ $c->name }} <span class="text-xs text-slate-400">· {{ $c->assets_count }}</span></a><span class="tabular-nums text-slate-600">{{ inr_short($c->cost) }}</span></div>
                        <div class="h-2 w-full rounded-full bg-slate-100"><div class="h-2 rounded-full bg-gradient-to-r from-brand-400 to-brand-600" style="width: {{ round($c->cost / $max * 100, 1) }}%"></div></div>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">No assets yet.</p>
                @endforelse
            </div>
        </div>
        <div class="card xl:col-span-2">
            <div class="card-header"><div class="card-title">Recently added</div><a href="{{ route('assets.index') }}" class="text-xs font-medium text-brand-600 hover:underline">Register →</a></div>
            <ul class="divide-y divide-slate-100">
                @foreach ($recent as $a)
                    <li class="flex items-center gap-3 px-5 py-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-50 text-brand-600"><x-icon name="cube" class="h-4 w-4"/></span>
                        <div class="min-w-0 flex-1"><a href="{{ route('assets.show', $a) }}" class="block truncate text-sm font-medium text-slate-800 hover:text-brand-700">{{ $a->name }}</a>
                            <div class="text-xs text-slate-400"><span class="font-mono">{{ $a->asset_code }}</span> · {{ $a->location?->name }}</div></div>
                        <span class="text-sm tabular-nums text-slate-600">{{ inr_short($a->original_cost) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="mt-6 rounded-xl border border-dashed border-slate-200 bg-white/60 p-5 text-sm text-slate-500">
        <span class="font-semibold text-slate-700">Coming in Phase 2:</span> depreciation runs & schedules, transfers, maintenance, disposals, Schedule II / IT Act reports and Excel/PDF exports.
    </div>
</x-app-layout>
