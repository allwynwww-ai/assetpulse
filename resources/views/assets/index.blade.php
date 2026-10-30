@php
    $canWrite = auth()->user()->canManageAssets();
    $sortLink = function ($col, $label) use ($sort, $dir) {
        $next = $sort === $col && $dir === 'asc' ? 'desc' : 'asc';
        $arrow = $sort === $col ? ($dir === 'asc' ? '↑' : '↓') : '';
        return '<a href="'.e(request()->fullUrlWithQuery(['sort' => $col, 'dir' => $next, 'page' => null])).'" class="hover:text-slate-700">'.e($label).' '.$arrow.'</a>';
    };
    $filterDefs = ['category' => ['Category', $lookups['categories']], 'location' => ['Location', $lookups['locations']], 'department' => ['Department', $lookups['departments']], 'cost_center' => ['Cost center', $lookups['costCenters']], 'status' => ['Status', $lookups['statuses']]];
@endphp
<x-app-layout title="Asset Register">
    <x-slot name="header">
        <x-page-header title="Asset Register" subtitle="Every capitalised asset with cost, accumulated depreciation and net book value." icon="cube" :crumbs="['Assets' => null, 'Register' => null]">
            @if ($canWrite)
                <a href="{{ route('assets.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4"/> Add asset</a>
            @endif
        </x-page-header>
    </x-slot>

    {{-- Summary --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([['Assets', number_format($totals->n), 'cube', 'brand'], ['Gross block (cost)', inr_short($totals->cost), 'banknotes', 'brand'], ['Accumulated depreciation', inr_short($totals->acc), 'calculator', 'amber'], ['Net book value', inr_short($totals->nbv), 'chart', 'mint']] as [$label, $val, $icon, $c])
            <div class="card flex items-center gap-4 p-5">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-{{ $c }}-50 text-{{ $c }}-600"><x-icon :name="$icon" class="h-5 w-5"/></div>
                <div><div class="stat-label">{{ $label }}</div><div class="stat-value">{{ $val }}</div></div>
            </div>
        @endforeach
    </div>

    <div class="card" x-data="{ open: {{ $filtered ? 'true' : 'false' }} }">
        <form method="GET" class="border-b border-slate-100 px-5 py-3.5">
            <div class="flex flex-wrap items-center gap-2">
                <label class="relative flex-1 min-w-[16rem]">
                    <span class="pointer-events-none absolute inset-y-0 left-2.5 flex items-center text-slate-400"><x-icon name="search" class="h-4 w-4"/></span>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search code, name, serial no., invoice, make/model…" class="form-input py-1.5 pl-8 text-sm">
                </label>
                <button type="button" @click="open = !open" class="btn-secondary py-1.5"><x-icon name="adjust" class="h-4 w-4"/> Filters</button>
                <button class="btn-primary py-1.5">Apply</button>
                @if ($filtered)<a href="{{ route('assets.index') }}" class="btn-ghost py-1.5 text-xs">Clear all</a>@endif
                <div class="ml-auto inline-flex rounded-lg bg-slate-100 p-0.5 text-xs font-medium">
                    <a href="{{ request()->fullUrlWithQuery(['show' => null, 'page' => null]) }}" class="rounded-md px-3 py-1.5 {{ request('show') !== 'inactive' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500' }}">Active</a>
                    <a href="{{ request()->fullUrlWithQuery(['show' => 'inactive', 'page' => null]) }}" class="rounded-md px-3 py-1.5 {{ request('show') === 'inactive' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500' }}">Inactive</a>
                </div>
            </div>
            <div x-show="open" x-cloak class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                @foreach ($filterDefs as $param => [$label, $opts])
                    <div>
                        <label class="form-label text-xs">{{ $label }}</label>
                        <select name="{{ $param }}" class="form-input py-1.5 text-sm">
                            <option value="">All</option>
                            @foreach ($opts as $id => $name)<option value="{{ $id }}" @selected((string) request($param) === (string) $id)>{{ $name }}</option>@endforeach
                        </select>
                    </div>
                @endforeach
                <div><label class="form-label text-xs">Put to use from</label><input type="date" name="from" value="{{ request('from') }}" class="form-input py-1.5 text-sm"></div>
                <div><label class="form-label text-xs">to</label><input type="date" name="to" value="{{ request('to') }}" class="form-input py-1.5 text-sm"></div>
            </div>
            @if (request('show'))<input type="hidden" name="show" value="{{ request('show') }}">@endif
        </form>

        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    <th>{!! $sortLink('asset_code', 'Code') !!}</th>
                    <th>{!! $sortLink('name', 'Asset') !!}</th>
                    <th>Location / Dept.</th>
                    <th>Cost center</th>
                    <th>{!! $sortLink('capitalization_date', 'Put to use') !!}</th>
                    <th class="text-right">{!! $sortLink('original_cost', 'Cost (₹)') !!}</th>
                    <th class="text-right">Acc. dep. (₹)</th>
                    <th class="text-right">{!! $sortLink('current_book_value', 'NBV (₹)') !!}</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($assets as $a)
                    <tr class="{{ $a->trashed() ? 'opacity-60' : '' }}">
                        <td class="whitespace-nowrap"><a href="{{ route('assets.show', $a) }}" class="font-mono text-xs font-semibold text-brand-700 hover:underline">{{ $a->asset_code }}</a></td>
                        <td class="max-w-xs"><a href="{{ route('assets.show', $a) }}" class="font-medium text-slate-800 hover:text-brand-700">{{ $a->name }}</a><div class="text-xs text-slate-400">{{ $a->category?->name }}</div></td>
                        <td class="text-sm"><div>{{ $a->location?->name ?? '—' }}</div><div class="text-xs text-slate-400">{{ $a->department?->name }}</div></td>
                        <td class="text-xs text-slate-500">{{ $a->costCenter?->code ?? '—' }}</td>
                        <td class="whitespace-nowrap text-sm">{{ fmt_date($a->capitalization_date) }}</td>
                        <td class="text-right tabular-nums">{{ inr($a->original_cost) }}</td>
                        <td class="text-right tabular-nums text-amber-700">{{ inr($a->accumulated_depreciation) }}</td>
                        <td class="text-right tabular-nums font-semibold text-slate-800">{{ inr($a->current_book_value) }}</td>
                        <td>@if ($a->status)<span class="badge bg-{{ $a->status->color }}-50 text-{{ $a->status->color }}-700 ring-{{ $a->status->color }}-200">{{ $a->status->name }}</span>@endif</td>
                        <td class="whitespace-nowrap text-right">
                            @if ($a->trashed())
                                @if ($canWrite)
                                    <form method="POST" action="{{ route('assets.restore', $a->id) }}" class="inline">@csrf @method('PATCH')<button class="btn-secondary btn-sm"><x-icon name="restore" class="h-3.5 w-3.5"/> Restore</button></form>
                                @endif
                            @else
                                <a href="{{ route('assets.show', $a) }}" class="btn-ghost btn-sm" title="View"><x-icon name="eye" class="h-4 w-4"/></a>
                                @if ($canWrite)<a href="{{ route('assets.edit', $a) }}" class="btn-ghost btn-sm" title="Edit"><x-icon name="pencil" class="h-4 w-4"/></a>@endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="py-14 text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400"><x-icon name="cube"/></div>
                        <div class="mt-3 text-sm font-medium text-slate-700">No assets found</div>
                        <div class="text-xs text-slate-400">{{ $filtered ? 'Adjust the filters or search.' : 'Add your first asset to start the register.' }}</div>
                    </td></tr>
                @endforelse
                </tbody>
                @if ($assets->count())
                    <tfoot class="bg-slate-50 text-sm font-semibold text-slate-700">
                    <tr>
                        <td colspan="5" class="px-4 py-3">Total ({{ number_format($totals->n) }} assets{{ $filtered ? ', filtered' : '' }})</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ inr($totals->cost) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-amber-700">{{ inr($totals->acc) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ inr($totals->nbv) }}</td>
                        <td colspan="2"></td>
                    </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        <div class="border-t border-slate-100 px-5 py-3">{{ $assets->links() }}</div>
    </div>
</x-app-layout>
