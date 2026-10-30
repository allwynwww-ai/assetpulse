<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name', 'AssetPulse') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans" x-data="{ sidebarOpen: false }">
<div class="min-h-full">
    {{-- Mobile overlay --}}
    <div x-cloak x-show="sidebarOpen" class="fixed inset-0 z-30 bg-slate-900/30 backdrop-blur-sm lg:hidden" @click="sidebarOpen = false"></div>

    {{-- Sidebar --}}
    <aside class="fixed inset-y-0 left-0 z-40 w-64 transform border-r border-slate-200/80 bg-white transition-transform duration-200 lg:translate-x-0"
           :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">
        @include('layouts.sidebar')
    </aside>

    <div class="lg:pl-64 flex min-h-screen flex-col">
        {{-- Top bar --}}
        <header class="sticky top-0 z-20 flex h-16 items-center gap-4 border-b border-slate-200/80 bg-white/85 px-4 backdrop-blur sm:px-6 lg:px-8">
            <button type="button" class="btn-ghost -ml-2 p-2 lg:hidden" @click="sidebarOpen = true" aria-label="Open menu">
                <x-icon name="menu" />
            </button>

            @if (Tenant::id())
                <form method="GET" action="{{ route('assets.index') }}" class="hidden max-w-md flex-1 md:block">
                    <label class="relative block">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400"><x-icon name="search" class="h-4 w-4"/></span>
                        <input name="q" type="search" placeholder="Search assets by code, name, serial, tag…" class="form-input pl-9 bg-slate-50 border-slate-200 shadow-none" value="{{ request()->routeIs('assets.index') ? request('q') : '' }}">
                    </label>
                </form>
            @endif

            <div class="ml-auto flex items-center gap-3">
                @if ($currentCompany ?? null)
                    <div class="hidden sm:flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 py-1 pl-1 pr-3">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-brand-100 text-[10px] font-bold text-brand-700">{{ \Illuminate\Support\Str::of($currentCompany->code)->substr(0, 3) }}</span>
                        <span class="text-xs font-medium text-slate-700">{{ $currentCompany->name }}</span>
                        @if (auth()->user()->isSuperAdmin())
                            <form method="POST" action="{{ route('admin.companies.exit') }}">@csrf
                                <button class="ml-1 text-slate-400 hover:text-rose-500" title="Exit company"><x-icon name="x" class="h-3.5 w-3.5"/></button>
                            </form>
                        @endif
                    </div>
                @endif

                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" class="flex items-center gap-2 rounded-full p-1 pr-2 hover:bg-slate-100">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-brand-500 to-mint-500 text-xs font-semibold text-white">{{ auth()->user()->initials }}</span>
                        <span class="hidden text-left md:block">
                            <span class="block text-sm font-medium leading-4 text-slate-800">{{ auth()->user()->name }}</span>
                            <span class="block text-[11px] text-slate-500">{{ auth()->user()->role->label() }}</span>
                        </span>
                        <x-icon name="chevron-down" class="h-4 w-4 text-slate-400"/>
                    </button>
                    <div x-cloak x-show="open" @click.outside="open = false" x-transition
                         class="absolute right-0 mt-2 w-52 rounded-xl border border-slate-200 bg-white py-1 shadow-soft">
                        <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">My profile</a>
                        <form method="POST" action="{{ route('logout') }}">@csrf
                            <button class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-rose-600 hover:bg-rose-50"><x-icon name="logout" class="h-4 w-4"/> Sign out</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
            @isset($header)
                <div class="mb-6">{{ $header }}</div>
            @endisset

            @include('partials.flash')

            {{ $slot }}
        </main>

        <footer class="px-8 py-4 text-xs text-slate-400">
            AssetPulse · Fixed Asset Management · Schedule II / Ind AS 16 ready
        </footer>
    </div>
</div>
@stack('scripts')
</body>
</html>
