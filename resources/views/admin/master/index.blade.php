<x-app-layout :title="$title">
    <x-slot name="header">
        <x-page-header :title="$title" :subtitle="$description" :icon="$icon" :crumbs="['Admin' => null, $title => null]">
            @if ($canWrite && Route::has($routeName.'.create'))
                <a href="{{ route($routeName.'.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4"/> New {{ $singular }}</a>
            @endif
        </x-page-header>
    </x-slot>

    <div class="card">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-3.5 lg:flex-row lg:items-center lg:justify-between">
            {{-- Status tabs --}}
            <div class="inline-flex rounded-lg bg-slate-100 p-0.5 text-xs font-medium">
                @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'all' => 'All'] as $key => $label)
                    <a href="{{ request()->fullUrlWithQuery(['status' => $key, 'page' => null]) }}"
                       class="rounded-md px-3 py-1.5 {{ $status === $key ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                        {{ $label }}
                        @if (isset($counts[$key]))<span class="ml-1 text-slate-400">{{ $counts[$key] }}</span>@endif
                    </a>
                @endforeach
            </div>

            <form method="GET" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="status" value="{{ $status }}">
                @foreach ($filters as $param => $f)
                    <select name="{{ $param }}" class="form-input w-auto py-1.5 text-xs" onchange="this.form.submit()">
                        <option value="">{{ $f['label'] }}: All</option>
                        @foreach ($f['options'] as $val => $lbl)
                            <option value="{{ $val }}" @selected((string) request($param) === (string) $val)>{{ $lbl }}</option>
                        @endforeach
                    </select>
                @endforeach
                <label class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-2.5 flex items-center text-slate-400"><x-icon name="search" class="h-4 w-4"/></span>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search {{ strtolower($title) }}…" class="form-input w-64 py-1.5 pl-8 text-sm">
                </label>
                <button class="btn-secondary py-1.5">Search</button>
                @if (request('q') || collect($filters)->keys()->contains(fn ($k) => request()->filled($k)))
                    <a href="{{ route($routeName.'.index', ['status' => $status]) }}" class="btn-ghost py-1.5 text-xs">Clear</a>
                @endif
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    @foreach ($columns as $col)
                        <th class="{{ $col['class'] ?? '' }}">{{ $col['label'] }}</th>
                    @endforeach
                    <th class="text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr class="{{ $row->trashed() ? 'opacity-60' : '' }}">
                        @foreach ($columns as $col)
                            <td class="{{ $col['class'] ?? '' }}">{!! $col['value']($row) !!}</td>
                        @endforeach
                        <td class="text-right whitespace-nowrap">
                            @if ($row->trashed())
                                <span class="badge bg-slate-100 text-slate-500 ring-slate-200 mr-2">Inactive</span>
                                @if ($canWrite)
                                    <form method="POST" action="{{ route($routeName.'.restore', $row->getKey()) }}" class="inline">@csrf @method('PATCH')
                                        <button class="btn-secondary btn-sm"><x-icon name="restore" class="h-3.5 w-3.5"/> Restore</button>
                                    </form>
                                @endif
                            @else
                                @if (Route::has($routeName.'.show'))
                                    <a href="{{ route($routeName.'.show', $row->getKey()) }}" class="btn-ghost btn-sm" title="View"><x-icon name="eye" class="h-4 w-4"/></a>
                                @endif
                                @if ($canWrite)
                                    <a href="{{ route($routeName.'.edit', $row->getKey()) }}" class="btn-ghost btn-sm" title="Edit"><x-icon name="pencil" class="h-4 w-4"/></a>
                                    @if (Route::has($routeName.'.destroy'))
                                        <form method="POST" action="{{ route($routeName.'.destroy', $row->getKey()) }}" class="inline"
                                              onsubmit="return confirm('Deactivate this {{ strtolower($singular) }}? It can be restored later.')">@csrf @method('DELETE')
                                            <button class="btn-ghost btn-sm text-rose-500 hover:bg-rose-50" title="Deactivate"><x-icon name="trash" class="h-4 w-4"/></button>
                                        </form>
                                    @endif
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) + 1 }}" class="py-14 text-center">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400"><x-icon :name="$icon"/></div>
                            <div class="mt-3 text-sm font-medium text-slate-700">No {{ strtolower($title) }} found</div>
                            <div class="text-xs text-slate-400">{{ request('q') ? 'Try a different search term.' : ($status === 'inactive' ? 'Nothing has been deactivated.' : 'Create the first one to get started.') }}</div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($rows->hasPages())
            <div class="border-t border-slate-100 px-5 py-3">{{ $rows->links() }}</div>
        @else
            <div class="border-t border-slate-100 px-5 py-3 text-xs text-slate-400">{{ $rows->total() }} {{ \Illuminate\Support\Str::plural('record', $rows->total()) }}</div>
        @endif
    </div>
</x-app-layout>
