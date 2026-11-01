@props(['title', 'subtitle' => null, 'icon' => null, 'crumbs' => []])
<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div class="flex min-w-0 items-start gap-3.5">
        @if ($icon)
            <div class="hidden h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-500/10 text-brand-600 ring-1 ring-brand-500/15 dark:text-brand-300 sm:flex">
                <x-icon :name="$icon" class="h-5 w-5"/>
            </div>
        @endif
        <div class="min-w-0">
            @if ($crumbs)
                <nav class="mb-1 flex flex-wrap items-center gap-1 text-[12.5px] text-ink3" aria-label="Breadcrumb">
                    @foreach ($crumbs as $label => $url)
                        @if ($url)<a href="{{ $url }}" class="hover:text-brand-600 dark:hover:text-brand-300">{{ $label }}</a>@else<span>{{ $label }}</span>@endif
                        @unless ($loop->last)<x-icon name="chevron-right" class="h-3 w-3"/>@endunless
                    @endforeach
                </nav>
            @endif
            <h1 class="font-display text-[22px] font-bold tracking-tight text-ink sm:text-[26px]">{{ $title }}</h1>
            @if ($subtitle)<p class="mt-0.5 text-[13.5px] text-ink3">{{ $subtitle }}</p>@endif
        </div>
    </div>
    @if (trim($slot))
        <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</div>
