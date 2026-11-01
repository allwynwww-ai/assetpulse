<div class="card">
    <div class="flex items-center justify-between px-5 pb-2 pt-4">
        <h3 class="card-title">Components <span class="ml-1 text-[13px] font-semibold text-ink3">{{ $asset->components->count() }}</span></h3>
    </div>
    <div class="grid gap-x-3 px-3 pb-3 sm:grid-cols-2">
        @foreach ($asset->components as $c)
            <a href="{{ route('assets.show', $c) }}" class="flex items-center gap-3 rounded-xl px-2 py-2 hover:bg-soft">
                <x-cat-icon :category="$c->category" size="h-8 w-8" icon-size="h-4 w-4" rounded="rounded-lg"/>
                <div class="min-w-0 flex-1"><div class="truncate text-[13px] font-medium text-ink">{{ $c->name }}</div><div class="font-mono text-[11px] text-brand-600 dark:text-brand-300">{{ $c->asset_code }}</div></div>
                @if ($c->status)<span class="badge bg-{{ $c->status->color }}-500/10 text-{{ $c->status->color }}-700 ring-{{ $c->status->color }}-600/20 dark:text-{{ $c->status->color }}-300"><span class="h-1.5 w-1.5 rounded-full bg-{{ $c->status->color }}-500"></span>{{ $c->status->name }}</span>@endif
            </a>
        @endforeach
    </div>
</div>
