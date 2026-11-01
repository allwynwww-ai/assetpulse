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
                        <div x-data="topMenu({ width: 224 })" class="hidden sm:block" @keydown.escape.window="close(true)">
                            <button type="button" x-ref="trigger" @click="toggle()" class="btn btn-primary h-9 px-3 text-[13px]" aria-haspopup="menu" :aria-expanded="open.toString()"><x-icon name="plus" class="h-4 w-4" stroke="2.25"/> New <x-icon name="chev-down" class="h-3.5 w-3.5 opacity-70"/></button>
                            <template x-teleport="body">
                                <div x-show="open" x-cloak class="fixed inset-0 z-[60]" @click="close()" aria-hidden="true"></div>
                            </template>
                            <template x-teleport="body">
                                <div x-ref="panel" x-show="open" x-cloak x-transition.opacity.duration.120ms :style="pos" role="menu" aria-label="Create new"
                                     class="ap-pop fixed z-[61] overflow-y-auto overscroll-contain rounded-xl border border-line bg-card p-1.5 shadow-pop">
                                    @foreach ([['assets.create', 'plus', 'Asset'], ['work-orders.create', 'wrench', 'Work order'], ['transfers.create', 'arrows', 'Transfer'], ['pm-schedules.create', 'calendar-check', 'PM schedule'], ['disposals.create', 'archive', 'Disposal'], ['depreciation.create', 'calculator', 'Depreciation run']] as [$r, $i, $l])
                                        <a href="{{ route($r) }}" role="menuitem" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] text-ink2 hover:bg-soft hover:text-ink"><x-icon :name="$i" class="h-4 w-4 text-ink3"/> {{ $l }}</a>
                                    @endforeach
                                </div>
                            </template>
                        </div>
                    @endif

                    {{-- Notifications: the same real alerts as the dashboard. Full-width sheet below sm, clamped popover above. --}}
                    @php
                        $alertTone = ['rose' => 'bg-rose-500/10 text-rose-600 dark:bg-rose-500/15 dark:text-rose-300', 'amber' => 'bg-amber-500/15 text-amber-700 dark:text-amber-300', 'brand' => 'bg-brand-500/10 text-brand-600 dark:bg-brand-500/20 dark:text-brand-200', 'violet' => 'bg-violet-500/10 text-violet-600 dark:text-violet-300'];
                        $countTone = ['rose' => 'bg-rose-500 text-white', 'amber' => 'bg-amber-500 text-white', 'brand' => 'bg-brand-600 text-white', 'violet' => 'bg-violet-600 text-white'];
                        $alertTotal = count($shell['alerts']);
                    @endphp
                    <div x-data="topMenu({ sheet: true, width: 384 })" id="ap-notifications" @keydown.escape.window="close(true)">
                        <button type="button" x-ref="trigger" @click="toggle()" class="icon-btn relative" aria-haspopup="dialog" aria-controls="ap-notif-panel" :aria-expanded="open.toString()"
                                aria-label="Notifications{{ $alertTotal ? ' – '.$alertTotal.' '.\Illuminate\Support\Str::plural('item', $alertTotal).' need attention' : '' }}">
                            <x-icon name="bell" class="h-[18px] w-[18px]"/>
                            @if ($alertTotal)<span class="absolute right-1 top-1 grid h-4 min-w-4 place-items-center rounded-full bg-rose-500 px-1 text-[9.5px] font-bold text-white ring-2 ring-card">{{ $alertTotal }}</span>@endif
                        </button>
                        <template x-teleport="body">
                            <div x-show="open" x-cloak x-transition.opacity.duration.150ms @click="close()" aria-hidden="true"
                                 class="ap-notif-backdrop fixed inset-0 z-[60] bg-[#0d0f24]/50 backdrop-blur-[2px] sm:bg-[#0d0f24]/10 sm:backdrop-blur-0"></div>
                        </template>
                        <template x-teleport="body">
                            <section x-ref="panel" x-show="open" x-cloak x-transition.opacity.duration.150ms :style="pos" id="ap-notif-panel"
                                     role="dialog" aria-modal="true" aria-labelledby="ap-notif-title"
                                     class="ap-notif-panel fixed z-[61] flex flex-col overflow-hidden rounded-2xl border border-line bg-card shadow-pop ring-1 ring-black/5 dark:ring-white/5">
                                <header class="flex shrink-0 items-center gap-2 border-b border-line px-4 py-3">
                                    <h2 id="ap-notif-title" class="font-display text-[15px] font-semibold text-ink">Needs attention</h2>
                                    @if ($alertTotal)<span class="rounded-full bg-rose-500/10 px-2 py-0.5 text-[11.5px] font-bold text-rose-600 dark:text-rose-300">{{ $alertTotal }}</span>@endif
                                    <button type="button" @click="close(true)" class="icon-btn -mr-2 ml-auto h-9 w-9" aria-label="Close notifications"><x-icon name="x" class="h-[18px] w-[18px]"/></button>
                                </header>
                                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-2">
                                    @forelse ($shell['alerts'] as $a)
                                        <a href="{{ $a['url'] }}" class="ap-notif-item group flex items-center gap-3 rounded-xl px-2.5 py-3 hover:bg-soft focus-visible:bg-soft">
                                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl {{ $alertTone[$a['tone']] ?? $alertTone['brand'] }}"><x-icon :name="$a['icon']" class="h-5 w-5"/></span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block break-words text-[13.5px] font-semibold leading-snug text-ink">{{ $a['title'] }}</span>
                                                <span class="mt-0.5 block break-words text-[12px] leading-snug text-ink3">{{ $a['sub'] }}</span>
                                            </span>
                                            <span class="grid h-6 min-w-6 shrink-0 place-items-center rounded-full px-1.5 text-[11.5px] font-bold {{ $countTone[$a['tone']] ?? $countTone['brand'] }}" aria-label="{{ $a['count'] }}">{{ $a['count'] }}</span>
                                            <x-icon name="chev-right" class="h-4 w-4 shrink-0 text-ink3 transition group-hover:translate-x-0.5 group-hover:text-ink"/>
                                        </a>
                                    @empty
                                        <div class="flex flex-col items-center px-6 py-8 text-center">
                                            <span class="grid h-12 w-12 place-items-center rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-300"><x-icon name="check-circle" class="h-6 w-6"/></span>
                                            <p class="mt-3 text-[14px] font-semibold text-ink">You're all caught up</p>
                                            <p class="mt-1 text-[12.5px] text-ink3">No overdue maintenance, open breakdowns or depreciation runs waiting for you.</p>
                                        </div>
                                    @endforelse
                                </div>
                                @if ($alertTotal)
                                    <footer class="shrink-0 border-t border-line px-4 py-2.5 text-[11.5px] text-ink3">Live from your records · tap an item to open it</footer>
                                @endif
                            </section>
                        </template>
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

                <div x-data="topMenu({ width: 224 })" @keydown.escape.window="close(true)">
                    <button type="button" x-ref="trigger" @click="toggle()" aria-haspopup="menu" :aria-expanded="open.toString()" class="ml-1 flex items-center gap-2.5 rounded-xl py-1 pl-1 pr-1 hover:bg-soft md:pr-2" aria-label="Account menu">
                        <span class="grid h-9 w-9 place-items-center rounded-full bg-brand-100 text-[12.5px] font-bold text-brand-700 ring-2 ring-card dark:bg-brand-500/20 dark:text-brand-200">{{ $u->initials }}</span>
                        <span class="hidden text-left leading-tight md:block">
                            <span class="block text-[13px] font-semibold text-ink">{{ $u->name }}</span>
                            <span class="block text-[11px] text-ink3">{{ $u->role->label() }}</span>
                        </span>
                    </button>
                    <template x-teleport="body">
                        <div x-show="open" x-cloak class="fixed inset-0 z-[60]" @click="close()" aria-hidden="true"></div>
                    </template>
                    <template x-teleport="body">
                    <div x-ref="panel" x-cloak x-show="open" x-transition.opacity.duration.120ms :style="pos" role="menu" aria-label="Account"
                         class="ap-pop fixed z-[61] overflow-y-auto overscroll-contain rounded-xl border border-line bg-card p-1.5 shadow-pop">
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
                    </template>
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
