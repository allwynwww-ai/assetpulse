@props(['title', 'subtitle' => null, 'icon' => null, 'crumbs' => []])
<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div class="flex items-start gap-3">
        @if ($icon)
            <div class="hidden sm:flex h-11 w-11 items-center justify-center rounded-xl bg-white border border-slate-200 shadow-card text-brand-600">
                <x-icon :name="$icon" class="h-5 w-5"/>
            </div>
        @endif
        <div>
            @if ($crumbs)
                <nav class="mb-1 flex items-center gap-1 text-xs text-slate-400">
                    @foreach ($crumbs as $label => $url)
                        @if ($url)<a href="{{ $url }}" class="hover:text-brand-600">{{ $label }}</a>@else<span>{{ $label }}</span>@endif
                        @unless ($loop->last)<x-icon name="chevron-right" class="h-3 w-3"/>@endunless
                    @endforeach
                </nav>
            @endif
            <h1 class="text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">{{ $title }}</h1>
            @if ($subtitle)<p class="mt-0.5 text-sm text-slate-500">{{ $subtitle }}</p>@endif
        </div>
    </div>
    @if (trim($slot))
        <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</div>
