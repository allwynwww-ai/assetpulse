@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="flex flex-wrap items-center justify-between gap-3 text-[12.5px] text-ink3">
        <div>
            @if ($paginator->firstItem())
                Showing <b class="font-semibold text-ink tnum">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</b> of <b class="font-semibold text-ink tnum">{{ number_format($paginator->total()) }}</b>
            @else
                Showing {{ $paginator->count() }}
            @endif
        </div>
        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="grid h-8 min-w-8 place-items-center rounded-lg px-2 text-ink3/50" aria-disabled="true"><x-icon name="chev-left" class="h-4 w-4"/></span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="grid h-8 min-w-8 place-items-center rounded-lg px-2 text-ink2 hover:bg-soft" aria-label="Previous"><x-icon name="chev-left" class="h-4 w-4"/></a>
            @endif
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="grid h-8 min-w-8 place-items-center px-1">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="grid h-8 min-w-8 place-items-center rounded-lg bg-brand-600 px-2 font-semibold text-white tnum">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="hidden h-8 min-w-8 place-items-center rounded-lg px-2 font-semibold text-ink2 hover:bg-soft tnum sm:grid">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="grid h-8 min-w-8 place-items-center rounded-lg px-2 text-ink2 hover:bg-soft" aria-label="Next"><x-icon name="chev-right" class="h-4 w-4"/></a>
            @else
                <span class="grid h-8 min-w-8 place-items-center rounded-lg px-2 text-ink3/50" aria-disabled="true"><x-icon name="chev-right" class="h-4 w-4"/></span>
            @endif
        </div>
    </nav>
@endif
