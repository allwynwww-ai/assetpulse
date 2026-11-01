@php
    $shell = \App\Support\ShellData::get();
    $tab = fn ($active, $href, $label, $icon, $badge = 0) => [$active, $href, $label, $icon, $badge];
    $tabs = [
        $tab(request()->routeIs('dashboard'), route('dashboard'), 'Home', 'home'),
        $tab(request()->routeIs('assets.*'), route('assets.index'), 'Assets', 'package'),
        null,
        $tab(request()->routeIs('work-orders.*', 'pm-schedules.*'), route('work-orders.index'), 'Maintenance', 'wrench', $shell['openWo']),
    ];
@endphp
<nav class="no-print fixed inset-x-0 bottom-0 z-40 border-t border-line bg-card/95 backdrop-blur-md lg:hidden" style="padding-bottom: max(env(safe-area-inset-bottom), 8px)" aria-label="Quick navigation">
    <div class="flex items-start">
        @foreach ($tabs as $t)
            @if ($t === null)
                <div class="flex flex-1 flex-col items-center">
                    <button type="button" @click="$dispatch('ap:scan')" aria-label="Scan asset QR label"
                            class="-mt-6 grid h-[58px] w-[58px] place-items-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-[0_10px_24px_-6px_rgba(72,64,214,.6)] ring-4 ring-card active:scale-95">
                        <x-icon name="scan" class="h-7 w-7" stroke="2"/>
                    </button>
                    <span class="mt-1 text-[10.5px] font-semibold text-ink2">Scan QR</span>
                </div>
            @else
                @php [$on, $href, $label, $icon, $badge] = $t; @endphp
                <a href="{{ $href }}" class="flex flex-1 flex-col items-center gap-1 pt-2 {{ $on ? 'text-brand-600 dark:text-brand-300' : 'text-ink3' }}" @if($on) aria-current="page" @endif>
                    <span class="relative"><x-icon :name="$icon" class="h-[22px] w-[22px]" :stroke="$on ? '2.1' : '1.9'"/>
                        @if ($badge)<span class="absolute -right-2 -top-1 grid h-4 min-w-4 place-items-center rounded-full bg-rose-500 px-1 text-[9.5px] font-bold text-white ring-2 ring-card">{{ $badge > 99 ? '99+' : $badge }}</span>@endif
                    </span>
                    <span class="text-[10.5px] {{ $on ? 'font-semibold' : 'font-medium' }}">{{ $label }}</span>
                </a>
            @endif
        @endforeach
        <button type="button" @click="sidebarOpen = true" class="flex flex-1 flex-col items-center gap-1 pt-2 text-ink3" aria-label="More">
            <x-icon name="grid" class="h-[22px] w-[22px]" stroke="1.9"/>
            <span class="text-[10.5px] font-medium">More</span>
        </button>
    </div>
</nav>

{{-- Camera QR scanner (native BarcodeDetector; falls back to asset search) --}}
<div x-data="qrScanner()" x-cloak>
    <div x-show="open" x-transition.opacity class="fixed inset-0 z-[70] flex flex-col bg-black">
        <div class="flex items-center justify-between px-4 py-3 text-white">
            <div class="font-display text-[15px] font-bold">Scan asset label</div>
            <button type="button" class="grid h-10 w-10 place-items-center rounded-xl hover:bg-white/10" @click="stop()" aria-label="Close scanner"><x-icon name="x"/></button>
        </div>
        <div class="relative flex-1 overflow-hidden">
            <video x-ref="video" class="absolute inset-0 h-full w-full object-cover" playsinline muted></video>
            <div class="pointer-events-none absolute inset-0 grid place-items-center">
                <div class="h-60 w-60 rounded-3xl border-2 border-white/80 shadow-[0_0_0_9999px_rgba(0,0,0,.45)]"></div>
            </div>
        </div>
        <div class="space-y-3 px-4 py-4 text-center text-[13px] text-white/80" style="padding-bottom: max(env(safe-area-inset-bottom), 16px)">
            <p x-show="!error">Point the camera at the QR code on the asset tag.</p>
            <p x-show="error" class="text-amber-300" x-text="error"></p>
            <button type="button" class="btn btn-secondary w-full" @click="search()"><x-icon name="search" class="h-4 w-4"/> Search by code instead</button>
        </div>
    </div>
</div>
