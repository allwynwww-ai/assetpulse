<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('partials.shell.head')
    <title>{{ $title ?? 'Sign in' }} · {{ config('app.name', 'AssetPulse') }}</title>
</head>
@php
    $feats = [
        ['calculator', 'Schedule II & Ind AS 16 depreciation, posted monthly'],
        ['scan', 'QR tagging and asset lookup from any phone'],
        ['wrench', 'Preventive maintenance and breakdown work orders'],
        ['building', 'Multi-plant, multi-company — one secure login'],
    ];
@endphp
<body class="h-full bg-canvas font-sans text-ink antialiased">
<div class="min-h-full lg:grid lg:min-h-screen lg:grid-cols-[1.05fr_1fr]">
    {{-- Brand panel (desktop) --}}
    <section class="relative hidden overflow-hidden bg-gradient-to-br from-brand-700 via-brand-800 to-brand-950 p-12 text-white lg:block">
        <div class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-brand-400/25 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-32 -left-16 h-96 w-96 rounded-full bg-teal-400/20 blur-3xl"></div>
        <svg class="pointer-events-none absolute inset-0 h-full w-full opacity-[.07]" aria-hidden="true"><defs><pattern id="ap-grid" width="32" height="32" patternUnits="userSpaceOnUse"><path d="M32 0H0V32" fill="none" stroke="white"/></pattern></defs><rect width="100%" height="100%" fill="url(#ap-grid)"/></svg>
        <div class="relative flex h-full flex-col">
            <div class="flex items-center gap-3">
                <span class="grid h-11 w-11 place-items-center rounded-2xl bg-white text-brand-700 shadow-lift"><x-icon name="pulse" class="h-6 w-6" stroke="2.25"/></span>
                <div class="leading-tight"><div class="font-display text-[19px] font-bold">AssetPulse</div><div class="text-[12px] text-white/60">Fixed Assets Cloud</div></div>
            </div>
            <div class="my-auto max-w-[520px] py-10">
                <h1 class="font-display text-[40px] font-bold leading-[1.12] tracking-tight text-white">Every asset.<br>Every rupee.<br><span class="bg-gradient-to-r from-teal-200 to-brand-200 bg-clip-text text-transparent">Always audit-ready.</span></h1>
                <p class="mt-4 text-[15.5px] leading-relaxed text-white/70">The fixed asset register built for Indian manufacturers — from capitalisation to depreciation to disposal.</p>
                <ul class="mt-8 space-y-3.5">
                    @foreach ($feats as [$ic, $t])
                        <li class="flex items-center gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-white/10 ring-1 ring-white/15"><x-icon :name="$ic" class="h-[18px] w-[18px]"/></span><span class="text-[14.5px] text-white/85">{{ $t }}</span></li>
                    @endforeach
                </ul>
            </div>
            {{-- Decorative product cards: illustrative shapes only, no figures --}}
            <div class="relative h-[150px]" aria-hidden="true">
                <div class="absolute left-0 top-6 w-[250px] -rotate-2 rounded-2xl bg-white/95 p-4 text-[#15123A] shadow-pop">
                    <div class="flex items-center justify-between text-[12px] text-slate-500"><span class="font-semibold text-[#15123A]">Net block over life</span><span class="rounded-full bg-emerald-500/10 px-1.5 text-[11px] font-semibold text-emerald-700">SLM · WDV</span></div>
                    <svg class="mt-3" width="218" height="52" viewBox="0 0 218 52"><defs><linearGradient id="lg1" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#4840D6" stop-opacity=".22"/><stop offset="1" stop-color="#4840D6" stop-opacity="0"/></linearGradient></defs><path d="M0,6 L27,10 L54,15 L81,20 L109,26 L136,31 L163,36 L190,41 L218,46 L218,52 L0,52 Z" fill="url(#lg1)"/><path d="M0,6 L27,10 L54,15 L81,20 L109,26 L136,31 L163,36 L190,41 L218,46" fill="none" stroke="#4840D6" stroke-width="1.75" stroke-linecap="round"/><path d="M0,6 C40,24 80,34 120,40 S190,47 218,48" fill="none" stroke="#14B8A6" stroke-width="1.75" stroke-dasharray="4 3"/></svg>
                </div>
                <div class="absolute left-[268px] top-0 w-[262px] rotate-2 rounded-2xl bg-white/95 p-4 text-[#15123A] shadow-pop">
                    <div class="flex items-center gap-2 text-[12px] font-semibold text-emerald-700"><x-icon name="check-circle" class="h-4 w-4"/> Period finalised &amp; locked</div>
                    <div class="mt-1.5 text-[13px] text-slate-600">Posted runs, reversals and approvals — every change in the audit trail.</div>
                    <div class="mt-2.5 flex items-center gap-1.5">
                        @foreach (['bg-brand-500', 'bg-teal-500', 'bg-amber-500', 'bg-violet-500'] as $c)<span class="h-1.5 flex-1 rounded-full {{ $c }}"></span>@endforeach
                    </div>
                </div>
            </div>
            <div class="mt-6 flex items-center justify-between text-[12px] text-white/50"><span>© {{ date('Y') }} AssetPulse</span><span class="flex items-center gap-1.5"><x-icon name="lock" class="h-3.5 w-3.5"/> Encrypted in transit · Role-based access</span></div>
        </div>
    </section>

    {{-- Brand header (mobile) --}}
    <div class="relative overflow-hidden bg-gradient-to-br from-brand-700 via-brand-800 to-brand-950 px-6 pb-16 pt-10 text-white lg:hidden">
        <div class="pointer-events-none absolute -right-16 -top-16 h-60 w-60 rounded-full bg-brand-400/30 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-20 -left-10 h-52 w-52 rounded-full bg-teal-400/20 blur-3xl"></div>
        <div class="relative">
            <div class="flex items-center gap-2.5"><span class="grid h-10 w-10 place-items-center rounded-xl bg-white text-brand-700"><x-icon name="pulse" class="h-5 w-5" stroke="2.25"/></span><span class="font-display text-[18px] font-bold">AssetPulse</span></div>
            <div class="mt-7 font-display text-[26px] font-bold leading-tight text-white">Every asset. Every rupee.<br><span class="text-teal-200">Always audit-ready.</span></div>
        </div>
    </div>

    <section class="relative -mt-8 flex justify-center rounded-t-[28px] bg-canvas px-6 pb-10 pt-7 lg:mt-0 lg:items-center lg:rounded-none lg:p-12">
        <div class="w-full max-w-[400px]">
            {{ $slot }}
        </div>
    </section>
</div>
</body>
</html>
