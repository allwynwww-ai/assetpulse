@php $mobile = $mobile ?? false; @endphp
<div x-data="{ open: false }" class="relative" @keydown.escape.window="open = false">
    <button type="button" @click="open = !open" :aria-expanded="open" aria-haspopup="true" title="More actions"
            class="{{ $mobile ? 'grid h-12 w-12 place-items-center rounded-2xl border border-line bg-card text-ink2 shadow-card' : 'grid h-9 w-9 place-items-center rounded-lg border border-line bg-card text-ink2 shadow-card hover:bg-soft' }}">
        <x-icon name="more" class="{{ $mobile ? 'h-5 w-5' : 'h-4 w-4' }}"/>
    </button>
    <div x-cloak x-show="open" @click.outside="open = false" x-transition.origin.top.right
         class="absolute {{ $mobile ? 'right-0 top-14' : 'right-0 top-11' }} z-30 w-60 rounded-xl border border-line bg-card p-1.5 text-left shadow-pop">
        <a href="{{ route('assets.qr', [$asset, 'format' => 'png']) }}" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] text-ink2 hover:bg-soft"><x-icon name="qr" class="h-4 w-4 text-ink3"/> Download QR (PNG)</a>
        <a href="{{ route('reports.show', ['depreciation-detail', 'q' => $asset->asset_code, 'run' => '']) }}" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] text-ink2 hover:bg-soft"><x-icon name="calculator" class="h-4 w-4 text-ink3"/> Depreciation report</a>
        @if ($live)
            <div class="my-1 h-px bg-line"></div>
            <form method="POST" action="{{ route('assets.destroy', $asset) }}" onsubmit="return confirm('Deactivate {{ $asset->asset_code }}? It will be hidden from the register but can be restored.')">@csrf @method('DELETE')
                <button class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] text-ink2 hover:bg-soft"><x-icon name="archive" class="h-4 w-4 text-ink3"/> Deactivate</button>
            </form>
            <a href="{{ route('disposals.create', ['asset' => $asset->id]) }}" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] font-medium text-rose-600 hover:bg-rose-500/10 dark:text-rose-300"><x-icon name="x" class="h-4 w-4"/> Dispose asset…</a>
        @endif
    </div>
</div>
