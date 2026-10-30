<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in · {{ config('app.name', 'AssetPulse') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans antialiased">
<div class="flex min-h-full">
    <div class="relative hidden w-1/2 overflow-hidden bg-gradient-to-br from-brand-600 via-brand-700 to-slate-900 lg:flex lg:flex-col lg:justify-between p-12 text-white">
        <div class="flex items-center gap-2.5">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/20">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h4l3-8 4 16 3-8h4"/></svg>
            </div>
            <span class="text-lg font-bold tracking-tight">AssetPulse</span>
        </div>
        <div>
            <h1 class="text-3xl font-semibold leading-tight text-white">Every machine, mould and laptop —<br>accounted for.</h1>
            <p class="mt-4 max-w-md text-brand-100">Multi-plant fixed asset register with Schedule II lives, QR tagging and a complete audit trail.</p>
            <div class="mt-8 flex gap-6 text-sm text-brand-100">
                <div><div class="text-2xl font-semibold text-white">SLM · WDV</div>Companies Act 2013</div>
                <div><div class="text-2xl font-semibold text-white">Ind AS 16</div>Component ready</div>
            </div>
        </div>
        <div class="text-xs text-brand-200">© {{ date('Y') }} AssetPulse</div>
        <div class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-mint-400/20 blur-3xl"></div>
    </div>
    <div class="flex flex-1 items-center justify-center bg-white px-6 py-12">
        <div class="w-full max-w-sm">
            <div class="mb-8 lg:hidden text-xl font-bold text-slate-900">Asset<span class="text-brand-600">Pulse</span></div>
            {{ $slot }}
        </div>
    </div>
</div>
</body>
</html>
