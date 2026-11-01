<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('partials.shell.head')
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name', 'AssetPulse') }}</title>
</head>
@php
    $u = auth()->user();
    $hasTenant = Tenant::id() !== null;
    $shell = \App\Support\ShellData::get();
    $fy = $shell['fy'];
    $canWrite = $u->canManageAssets();
    $initials = fn ($s) => collect(preg_split('/\s+/', trim((string) $s)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
@endphp
<body class="h-full font-sans antialiased {{ $hasTenant ? 'has-tabbar' : '' }}" x-data="sidebarState()" @keydown.escape.window="sidebarOpen = false" @resize.window.debounce.150ms="if (window.innerWidth >= 1024) sidebarOpen = false">
<div class="min-h-full">
    {{-- Mobile overlay --}}
    <div x-cloak x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-[#0d0f24]/45 backdrop-blur-sm lg:hidden" @click="sidebarOpen = false"></div>

    {{-- Sidebar --}}
    <aside id="app-sidebar" class="app-sidebar fixed inset-y-0 left-0 z-50 w-[252px] max-w-[85vw] -translate-x-full transform border-r border-line bg-card lg:z-30 lg:translate-x-0"
           :class="sidebarOpen ? '!translate-x-0 shadow-pop lg:shadow-none' : ''" aria-label="Main navigation">
        @include('layouts.sidebar')
    </aside>

    <div class="app-content flex min-h-screen flex-col lg:pl-[252px]">
        {{-- Top bar --}}
        <header class="no-print sticky top-0 z-30 flex h-[60px] items-center gap-2 border-b border-line bg-card/85 px-4 backdrop-blur-md sm:px-6 lg:h-16 lg:gap-3 lg:px-8">
            <button type="button" class="icon-btn -ml-2 lg:hidden" @click="sidebarOpen = true" aria-label="Open menu" aria-controls="app-sidebar" :aria-expanded="sidebarOpen.toString()">
                <x-icon name="menu"/>
            </button>
            <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-2 lg:hidden">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-800 text-white"><x-icon name="pulse" class="h-[18px] w-[18px]" stroke="2.25"/></span>
                <span class="min-w-0 leading-tight">
                    <span class="block font-display text-[15px] font-bold text-ink">AssetPulse</span>
                    @if ($currentCompany ?? null)<span class="block truncate text-[11px] text-ink3">{{ $currentCompany->name }}@if ($fy) · {{ $fy->name }}@endif</span>@endif
                </span>
            </a>

            {{-- Desktop: collapse sidebar to an icon rail / expand it back --}}
            <button type="button" id="sidebar-toggle" class="sidebar-toggle icon-btn -ml-2 hidden lg:inline-grid" @click="toggleSidebar()"
                    aria-controls="app-sidebar" :aria-expanded="(!collapsed).toString()" :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'" title="Collapse sidebar">
                <x-icon name="panel" class="h-[18px] w-[18px]"/>
            </button>

            @if ($hasTenant)
                <button type="button" @click="$dispatch('ap:palette')" class="group hidden h-10 w-full max-w-[420px] items-center gap-2.5 rounded-xl border border-line bg-soft/70 px-3.5 text-[13.5px] text-ink3 transition hover:border-brand-300 md:flex" aria-label="Search (Ctrl+K)">
                    <x-icon name="search" class="h-[18px] w-[18px]"/><span class="truncate">Search assets, pages or actions…</span>
                    <span class="ml-auto flex items-center gap-1"><span class="kbd">Ctrl</span><span class="kbd">K</span></span>
                </button>
            @endif

            <div class="ml-auto flex items-center gap-1 lg:gap-1.5">
                @if ($hasTenant)
                    <button type="button" class="icon-btn md:hidden" @click="$dispatch('ap:palette')" aria-label="Search"><x-icon name="search" class="h-5 w-5"/></button>
                    @if ($canWrite)
                        <div x-data="{ open: false }" class="relative hidden sm:block">
                            <button type="button" @click="open = !open" class="btn btn-primary h-9 px-3 text-[13px]"><x-icon name="plus" class="h-4 w-4" stroke="2.25"/> New <x-icon name="chev-down" class="h-3.5 w-3.5 opacity-70"/></button>
                            <div x-cloak x-show="open" @click.outside="open = false" x-transition.origin.top.right class="absolute right-0 z-40 mt-2 w-56 rounded-xl border border-line bg-card p-1.5 shadow-pop">
                                @foreach ([['assets.create', 'plus', 'Asset'], ['work-orders.create', 'wrench', 'Work order'], ['transfers.create', 'arrows', 'Transfer'], ['pm-schedules.create', 'calendar-check', 'PM schedule'], ['disposals.create', 'archive', 'Disposal'], ['depreciation.create', 'calculator', 'Depreciation run']] as [$r, $i, $l])
                                    <a href="{{ route($r) }}" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] text-ink2 hover:bg-soft hover:text-ink"><x-icon :name="$i" class="h-4 w-4 text-ink3"/> {{ $l }}</a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Notifications: the same real alerts as the dashboard --}}
                    <div x-data="{ open: false }" class="relative">
                        <button type="button" @click="open = !open" class="icon-btn relative" aria-label="Notifications">
                            <x-icon name="bell" class="h-[18px] w-[18px]"/>
                            @if (count($shell['alerts']))<span class="absolute right-1 top-1 grid h-4 min-w-4 place-items-center rounded-full bg-rose-500 px-1 text-[9.5px] font-bold text-white ring-2 ring-card">{{ count($shell['alerts']) }}</span>@endif
                        </button>
                        <div x-cloak x-show="open" @click.outside="open = false" x-transition.origin.top.right class="absolute right-0 z-40 mt-2 w-80 max-w-[calc(100vw-2rem)] rounded-xl border border-line bg-card p-1.5 shadow-pop">
                            <div class="px-2.5 pb-1.5 pt-1 text-[11px] font-semibold uppercase tracking-[.07em] text-ink3">Needs attention</div>
                            @forelse ($shell['alerts'] as $a)
                                <a href="{{ $a['url'] }}" class="flex items-center gap-3 rounded-lg px-2.5 py-2 hover:bg-soft">
                                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-{{ $a['tone'] }}-500/10 text-{{ $a['tone'] }}-600 dark:text-{{ $a['tone'] }}-300"><x-icon :name="$a['icon']" class="h-4 w-4"/></span>
                                    <span class="text-[13px] font-medium text-ink">{{ $a['title'] }}</span>
                                </a>
                            @empty
                                <div class="flex items-center gap-2 px-2.5 py-3 text-[13px] text-ink3"><x-icon name="check-circle" class="h-4 w-4 text-emerald-500"/> You're all caught up.</div>
                            @endforelse
                        </div>
                    </div>
                @endif

                <button type="button" class="icon-btn" @click="toggleTheme()" :aria-label="dark ? 'Switch to light mode' : 'Switch to dark mode'" :title="dark ? 'Light mode' : 'Dark mode'" title="Dark mode">
                    <x-icon name="moon" class="h-[18px] w-[18px]" x-show="!dark"/>
                    <x-icon name="sun" class="h-[18px] w-[18px]" x-show="dark" x-cloak/>
                </button>

                @if ($currentCompany ?? null)
                    <span class="mx-1.5 hidden h-6 w-px bg-line xl:block"></span>
                    <div class="hidden h-10 items-center gap-2.5 rounded-xl border border-line bg-card pl-1.5 pr-2.5 xl:flex">
                        <span class="grid h-7 w-7 place-items-center rounded-lg bg-gradient-to-br from-teal-400 to-emerald-600 text-[10.5px] font-bold text-white">{{ $initials($currentCompany->name) }}</span>
                        <span class="max-w-[180px] text-left leading-tight">
                            <span class="block truncate text-[12.5px] font-semibold text-ink">{{ $currentCompany->name }}</span>
                            <span class="block truncate text-[11px] text-ink3">{{ collect([$fy?->name, $currentCompany->city])->filter()->implode(' · ') }}</span>
                        </span>
                        @if ($u->isSuperAdmin())
                            <form method="POST" action="{{ route('admin.companies.exit') }}">@csrf
                                <button class="ml-1 text-ink3 hover:text-rose-500" title="Exit company"><x-icon name="x" class="h-3.5 w-3.5"/></button>
                            </form>
                        @endif
                    </div>
                @endif

                <div x-data="{ open: false }" class="relative">
                    <button type="button" @click="open = !open" class="ml-1 flex items-center gap-2.5 rounded-xl py-1 pl-1 pr-1 hover:bg-soft md:pr-2" aria-label="Account menu">
                        <span class="grid h-9 w-9 place-items-center rounded-full bg-brand-100 text-[12.5px] font-bold text-brand-700 ring-2 ring-card dark:bg-brand-500/20 dark:text-brand-200">{{ $u->initials }}</span>
                        <span class="hidden text-left leading-tight md:block">
                            <span class="block text-[13px] font-semibold text-ink">{{ $u->name }}</span>
                            <span class="block text-[11px] text-ink3">{{ $u->role->label() }}</span>
                        </span>
                    </button>
                    <div x-cloak x-show="open" @click.outside="open = false" x-transition.origin.top.right
                         class="absolute right-0 z-40 mt-2 w-56 rounded-xl border border-line bg-card p-1.5 shadow-pop">
                        <div class="px-2.5 py-2 md:hidden">
                            <div class="text-[13px] font-semibold text-ink">{{ $u->name }}</div>
                            <div class="text-[11.5px] text-ink3">{{ $u->role->label() }}</div>
                        </div>
                        @if (($currentCompany ?? null) && $u->isSuperAdmin())
                            <form method="POST" action="{{ route('admin.companies.exit') }}" class="xl:hidden">@csrf
                                <button class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-[13px] text-ink2 hover:bg-soft"><x-icon name="x" class="h-4 w-4 text-ink3"/> Exit company</button>
                            </form>
                        @endif
                        <a href="{{ route('profile.edit') }}" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] text-ink2 hover:bg-soft"><x-icon name="user" class="h-4 w-4 text-ink3"/> My profile</a>
                        <form method="POST" action="{{ route('logout') }}">@csrf
                            <button class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-[13px] text-rose-600 hover:bg-rose-50 dark:text-rose-300"><x-icon name="logout" class="h-4 w-4"/> Sign out</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-[1400px] flex-1 px-4 py-5 sm:px-6 lg:px-8 lg:py-7">
            @isset($header)
                <div class="mb-6">{{ $header }}</div>
            @endisset

            @include('partials.flash')

            {{ $slot }}
        </main>

        <footer class="no-print hidden px-8 py-4 text-[11.5px] text-ink3 lg:block">
            AssetPulse · Fixed Asset Management · Schedule II / Ind AS 16 ready
        </footer>
    </div>
</div>

@if ($hasTenant)
    @include('partials.shell.palette')
    @include('partials.shell.tabbar')
@endif
@stack('scripts')
</body>
</html>
