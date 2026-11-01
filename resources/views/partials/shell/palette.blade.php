@php
    $u = auth()->user();
    $canWrite = $u->canManageAssets();
    $pages = [
        ['label' => 'Dashboard', 'group' => 'Overview', 'icon' => 'dashboard', 'url' => route('dashboard'), 'keywords' => 'home'],
        ['label' => 'Asset register', 'group' => 'Assets', 'icon' => 'cube', 'url' => route('assets.index'), 'keywords' => 'far list'],
        ['label' => 'Depreciation runs', 'group' => 'Accounting', 'icon' => 'calculator', 'url' => route('depreciation.index')],
        ['label' => 'Disposals', 'group' => 'Accounting', 'icon' => 'archive', 'url' => route('disposals.index'), 'keywords' => 'sale scrap'],
        ['label' => 'Transfers', 'group' => 'Operations', 'icon' => 'arrows', 'url' => route('transfers.index'), 'keywords' => 'move'],
        ['label' => 'PM schedules', 'group' => 'Operations', 'icon' => 'calendar-check', 'url' => route('pm-schedules.index'), 'keywords' => 'preventive maintenance'],
        ['label' => 'Work orders', 'group' => 'Operations', 'icon' => 'wrench', 'url' => route('work-orders.index'), 'keywords' => 'breakdown maintenance'],
        ['label' => 'Reports', 'group' => 'Insights', 'icon' => 'chart', 'url' => route('reports.index')],
    ];
    foreach (\App\Services\Reports\ReportBuilder::REPORTS as $slug => $r) {
        $pages[] = ['label' => 'Reports › '.$r[0], 'group' => 'Reports', 'icon' => 'chart', 'url' => route('reports.show', $slug), 'keywords' => $r[1].' report export excel pdf'];
    }
    foreach ([
        ['admin.asset-categories', 'tag', 'Asset categories'], ['admin.locations', 'map', 'Locations / plants'], ['admin.departments', 'office', 'Departments'],
        ['admin.cost-centers', 'banknotes', 'Cost centers'], ['admin.vendors', 'truck', 'Vendors / suppliers'],
    ] as [$r, $i, $l]) {
        $pages[] = ['label' => 'Masters › '.$l, 'group' => 'Masters', 'icon' => $i, 'url' => route($r.'.index'), 'keywords' => 'setup'];
    }
    foreach ([
        ['admin.depreciation-methods', 'calculator', 'Depreciation methods'], ['admin.asset-statuses', 'flag', 'Asset statuses'],
        ['admin.financial-years', 'calendar', 'Financial years'], ['admin.depreciation-run-parameters', 'adjust', 'Run parameters'],
        ['admin.users', 'users', 'Users & roles'], ['admin.audit-logs', 'shield', 'Audit log'],
    ] as [$r, $i, $l]) {
        $pages[] = ['label' => 'Configuration › '.$l, 'group' => 'Configuration', 'icon' => $i, 'url' => route($r.'.index'), 'keywords' => 'setup settings'];
    }
    $pages[] = ['label' => 'My profile', 'group' => 'Account', 'icon' => 'user', 'url' => route('profile.edit'), 'keywords' => 'password account'];

    $actions = [];
    if ($canWrite) {
        $actions = [
            ['label' => 'Add asset…', 'sub' => 'Capitalise a new asset on the register', 'icon' => 'plus', 'tone' => 'brand', 'url' => route('assets.create'), 'keywords' => 'new create capitalise', 'kbd' => 'N'],
            ['label' => 'Raise a work order…', 'sub' => 'Breakdown or corrective job against any asset', 'icon' => 'wrench', 'tone' => 'amber', 'url' => route('work-orders.create'), 'keywords' => 'new breakdown maintenance'],
            ['label' => 'Transfer an asset…', 'sub' => 'Move between locations, departments or cost centres', 'icon' => 'arrows', 'tone' => 'sky', 'url' => route('transfers.create'), 'keywords' => 'move relocate'],
            ['label' => 'Run depreciation…', 'sub' => 'Preview and post a period', 'icon' => 'calculator', 'tone' => 'violet', 'url' => route('depreciation.create'), 'keywords' => 'post period month'],
            ['label' => 'Record a disposal…', 'sub' => 'Sale, scrap or write-off', 'icon' => 'archive', 'tone' => 'rose', 'url' => route('disposals.create'), 'keywords' => 'sell scrap write off'],
            ['label' => 'Schedule preventive maintenance…', 'sub' => 'New PM schedule', 'icon' => 'calendar-check', 'tone' => 'teal', 'url' => route('pm-schedules.create'), 'keywords' => 'pm new'],
        ];
    }
    $actions[] = ['label' => 'Toggle dark mode', 'sub' => 'Switch between light and dark theme', 'icon' => 'moon', 'tone' => 'slate', 'js' => 'theme', 'keywords' => 'theme light night'];
    $actions[] = ['label' => 'Collapse / expand sidebar', 'sub' => 'Desktop icon rail', 'icon' => 'sidebar', 'tone' => 'slate', 'js' => 'sidebar', 'keywords' => 'menu rail'];
    $actions[] = ['label' => 'Scan an asset QR label', 'sub' => 'Use the camera to open an asset', 'icon' => 'scan', 'tone' => 'teal', 'js' => 'scan', 'keywords' => 'qr camera'];
    $tones = ['brand' => 'bg-brand-500/10 text-brand-600 dark:text-brand-300', 'amber' => 'bg-amber-500/10 text-amber-600 dark:text-amber-300', 'sky' => 'bg-sky-500/10 text-sky-600 dark:text-sky-300',
        'violet' => 'bg-violet-500/10 text-violet-600 dark:text-violet-300', 'rose' => 'bg-rose-500/10 text-rose-600 dark:text-rose-300', 'teal' => 'bg-teal-500/10 text-teal-600 dark:text-teal-300', 'slate' => 'bg-soft text-ink2'];
    $paletteData = ['pages' => $pages, 'actions' => collect($actions)->map(fn ($a) => $a + ['toneClass' => $tones[$a['tone']]])->values(), 'searchUrl' => route('assets.search'), 'newUrl' => $canWrite ? route('assets.create') : null];
    $iconNames = collect($pages)->pluck('icon')->merge(collect($actions)->pluck('icon'))->push('cube')->unique();
@endphp
<script type="application/json" id="ap-palette-data">{!! json_encode($paletteData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>

{{-- Icon sprites the palette clones by name --}}
<template id="ap-palette-icons">
    @foreach ($iconNames as $n)<span data-icon="{{ $n }}"><x-icon :name="$n" class="h-4 w-4"/></span>@endforeach
</template>

<div x-data="commandPalette()" x-cloak>
    <div x-show="open" x-transition.opacity class="fixed inset-0 z-[60] bg-[#0d0f24]/45 backdrop-blur-[3px]" @click="close()"></div>
    <div x-show="open" x-transition.origin.top role="dialog" aria-modal="true" aria-label="Command palette"
         class="fixed inset-x-3 top-3 z-[61] mx-auto max-w-[680px] overflow-hidden rounded-2xl border border-line bg-card shadow-pop sm:top-[96px] sm:inset-x-6"
         @keydown.escape.prevent.stop="close()" @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
         @keydown.enter.prevent="enter()" @keydown.tab.prevent="cycleFilter($event)">
        <div class="flex items-center gap-3 border-b border-line px-4 py-3.5 sm:px-5 sm:py-4">
            <x-icon name="search" class="h-5 w-5 shrink-0 text-brand-600 dark:text-brand-300" stroke="2"/>
            <input x-ref="input" x-model="q" type="text" autocomplete="off" spellcheck="false" aria-label="Search"
                   placeholder="Search assets, pages or actions…"
                   class="min-w-0 flex-1 border-0 bg-transparent p-0 text-[16px] text-ink placeholder:text-ink3 focus:ring-0">
            <span x-show="loading" class="h-4 w-4 animate-spin rounded-full border-2 border-brand-500/30 border-t-brand-600"></span>
            <button type="button" class="kbd hidden sm:inline-flex" @click="close()">Esc</button>
            <button type="button" class="icon-btn sm:hidden" @click="close()" aria-label="Close"><x-icon name="x"/></button>
        </div>
        <div class="no-sb flex gap-2 overflow-x-auto border-b border-line px-4 py-2.5 text-[12px] font-medium sm:px-5">
            <template x-for="f in [['all','All'],['assets','Assets'],['pages','Pages'],['actions','Actions']]" :key="f[0]">
                <button type="button" @click="filter = f[0]; $refs.input.focus()" class="shrink-0 rounded-full px-2.5 py-1"
                        :class="filter === f[0] ? 'bg-ink text-card' : 'bg-soft text-ink2 hover:text-ink'">
                    <span x-text="f[1]"></span><span x-show="f[0] !== 'all' && q.trim() && counts[f[0]] > 0" class="ml-1 tnum opacity-70" x-text="counts[f[0]]"></span>
                </button>
            </template>
        </div>
        <ul x-ref="list" class="max-h-[min(460px,65vh)] overflow-y-auto px-2 pb-2">
            <template x-for="s in sections" :key="s.key">
                <li>
                    <div class="px-3 pb-1 pt-3 text-[11px] font-semibold uppercase tracking-[.07em] text-ink3" x-text="s.title"></div>
                    <ul>
                        <template x-for="it in s.items" :key="s.key + (it.id || it.url || it.js)">
                            <li>
                                <a :href="it.url || '#'" @click.prevent="go(it)" @mousemove="active = it._i" :data-active="(active === it._i).toString()"
                                   class="flex items-center gap-3 rounded-xl px-3 py-2.5"
                                   :class="active === it._i ? 'bg-brand-500/[.08] ring-1 ring-brand-500/20' : ''">
                                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg"
                                          :class="it.type === 'asset' ? 'bg-brand-500/10 text-brand-600 dark:text-brand-300' : (it.toneClass || 'bg-soft text-ink2')"
                                          x-html="document.querySelector('#ap-palette-icons').content.querySelector('[data-icon=&quot;' + (it.type === 'asset' ? 'cube' : it.icon) + '&quot;]')?.innerHTML || ''"></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-[13.5px] font-medium text-ink" x-html="highlight(it.label)"></span>
                                        <span class="block truncate text-[12px] text-ink3" x-html="it.type === 'asset' ? highlight(it.sub) : (it.sub || it.group || '')"></span>
                                    </span>
                                    <template x-if="it.type === 'asset' && it.status">
                                        <span class="hidden shrink-0 sm:inline-flex badge" :class="'bg-' + (it.color || 'slate') + '-500/10 text-' + (it.color || 'slate') + '-700 ring-' + (it.color || 'slate') + '-600/20 dark:text-' + (it.color || 'slate') + '-300'" x-text="it.status"></span>
                                    </template>
                                    <span x-show="it.kbd" class="kbd hidden sm:inline-flex" x-text="it.kbd"></span>
                                    <span x-show="active === it._i" class="hidden items-center gap-1 text-[11.5px] text-ink3 sm:flex">Open <span class="kbd">↵</span></span>
                                </a>
                            </li>
                        </template>
                    </ul>
                </li>
            </template>
            <li x-show="!sections.length && !loading" class="px-4 py-10 text-center text-[13px] text-ink3">
                <span x-show="q.trim()">Nothing matches “<span class="font-medium text-ink2" x-text="q.trim()"></span>”.</span>
                <span x-show="!q.trim()">Type an asset code, name, serial or tag number.</span>
            </li>
        </ul>
        <div class="hidden items-center gap-4 border-t border-line bg-soft/60 px-5 py-2.5 text-[11.5px] text-ink3 sm:flex">
            <span class="flex items-center gap-1"><span class="kbd">↑</span><span class="kbd">↓</span> navigate</span>
            <span class="flex items-center gap-1"><span class="kbd">↵</span> open</span>
            <span class="flex items-center gap-1"><span class="kbd">Tab</span> filter</span>
            <span class="ml-auto">Search by code, name, serial, tag, model or PO no.</span>
        </div>
    </div>
</div>
