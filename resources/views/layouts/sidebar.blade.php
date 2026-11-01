@php
    $u = auth()->user();
    $hasTenant = Tenant::id() !== null;
    $shell = \App\Support\ShellData::get();
    $link = fn ($route, $pattern = null) => 'nav-link '.(request()->routeIs($pattern ?? $route) ? 'nav-link-active' : '');
    $badge = fn ($n, $tone, $title) => $n ? '<span class="nav-badge '.[
        'n' => 'bg-soft text-ink3', 'b' => 'bg-brand-500/10 text-brand-600 dark:text-brand-300',
        'r' => 'bg-rose-500/10 text-rose-600 dark:text-rose-300', 'a' => 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    ][$tone].'" title="'.e($title).'">'.number_format($n).'</span>' : '';
    $masters = [
        ['admin.asset-categories', 'tag', 'Asset categories'], ['admin.locations', 'map', 'Locations / plants'],
        ['admin.departments', 'office', 'Departments'], ['admin.cost-centers', 'banknotes', 'Cost centers'], ['admin.vendors', 'truck', 'Vendors / suppliers'],
    ];
    $config = [
        ['admin.depreciation-methods', 'calculator', 'Depreciation methods'], ['admin.asset-statuses', 'flag', 'Asset statuses'],
        ['admin.financial-years', 'calendar', 'Financial years'], ['admin.depreciation-run-parameters', 'adjust', 'Run parameters'],
        ['admin.users', 'users', 'Users & roles'], ['admin.audit-logs', 'shield', 'Audit log'],
    ];
    if ($u->role === \App\Enums\Role::CompanyAdmin) {
        $config[] = ['admin.company-profile', 'company', 'Company profile', 'admin.company-profile.edit'];
    }
    $groupOpen = fn ($items) => collect($items)->contains(fn ($i) => request()->routeIs($i[0].'.*'));
@endphp
<div class="flex h-full flex-col">
    <div class="sb-brand flex h-16 shrink-0 items-center gap-2.5 border-b border-line px-5">
        <a href="{{ route('dashboard') }}" class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-800 text-white shadow-lift" title="AssetPulse">
            <x-icon name="pulse" class="h-5 w-5" stroke="2.25"/>
        </a>
        <div class="sb-brand-text min-w-0 leading-tight">
            <div class="font-display text-[15.5px] font-bold tracking-tight text-ink">AssetPulse</div>
            <div class="text-[10.5px] font-medium text-ink3">Fixed Assets Cloud</div>
        </div>
        {{-- Mobile drawer close --}}
        <button type="button" class="icon-btn -mr-2 ml-auto lg:hidden" @click="sidebarOpen = false" aria-label="Close menu">
            <x-icon name="x"/>
        </button>
    </div>

    <nav class="no-sb min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 pb-4">
        @if ($hasTenant)
            <div class="nav-section">Overview</div>
            <a href="{{ route('dashboard') }}" class="{{ $link('dashboard') }}" title="Dashboard"><x-icon name="dashboard"/> <span class="nav-label">Dashboard</span></a>

            <div class="nav-section">Assets</div>
            <a href="{{ route('assets.index') }}" class="{{ request()->routeIs('assets.*') && ! request()->routeIs('assets.create') ? 'nav-link nav-link-active' : 'nav-link' }}" title="Asset register"><x-icon name="cube"/> <span class="nav-label">Asset register</span>{!! $badge($shell['assets'], 'n', 'Assets on the register') !!}</a>
            @can('manage-assets')
                <a href="{{ route('assets.create') }}" class="{{ $link('assets.create') }}" title="Add asset"><x-icon name="plus"/> <span class="nav-label">Add asset</span></a>
            @endcan

            <div class="nav-section">Accounting</div>
            <a href="{{ route('depreciation.index') }}" class="{{ $link('depreciation.*') }}" title="Depreciation runs"><x-icon name="calculator"/> <span class="nav-label">Depreciation runs</span>{!! $badge($shell['postedRuns'], 'b', 'Posted, not finalised') !!}</a>
            <a href="{{ route('disposals.index') }}" class="{{ $link('disposals.*') }}" title="Disposals"><x-icon name="archive"/> <span class="nav-label">Disposals</span></a>

            <div class="nav-section">Operations</div>
            <a href="{{ route('transfers.index') }}" class="{{ $link('transfers.*') }}" title="Transfers"><x-icon name="arrows"/> <span class="nav-label">Transfers</span></a>
            <a href="{{ route('pm-schedules.index') }}" class="{{ $link('pm-schedules.*') }}" title="PM schedules"><x-icon name="calendar-check"/> <span class="nav-label">PM schedules</span>{!! $badge($shell['pmDue'], 'r', 'PM overdue or due soon') !!}</a>
            <a href="{{ route('work-orders.index') }}" class="{{ $link('work-orders.*') }}" title="Work orders"><x-icon name="wrench"/> <span class="nav-label">Work orders</span>{!! $badge($shell['openWo'], 'a', 'Open work orders') !!}</a>

            <div class="nav-section">Insights</div>
            <a href="{{ route('reports.index') }}" class="{{ $link('reports.*') }}" title="Reports"><x-icon name="chart"/> <span class="nav-label">Reports</span></a>

            <div class="nav-section">Setup</div>
            @foreach ([['Masters', 'database', $masters], ['Configuration', 'sliders', $config]] as [$gLabel, $gIcon, $items])
                <div x-data="{ open: @js($groupOpen($items)) }">
                    <button type="button" class="sb-group-head nav-link w-full" @click="open = !open" :aria-expanded="open.toString()">
                        <x-icon :name="$gIcon"/> <span class="nav-label">{{ $gLabel }}</span>
                        <x-icon name="chev-right" class="ml-auto !h-3.5 !w-3.5 text-ink3 transition-transform" ::class="open && 'rotate-90'"/>
                    </button>
                    <div class="sb-sub" x-show="open" x-cloak>
                        @foreach ($items as $it)
                            <a href="{{ route($it[3] ?? $it[0].'.index') }}" class="{{ $link($it[0].'.*') }}" title="{{ $it[2] }}"><x-icon :name="$it[1]"/> <span class="nav-label">{{ $it[2] }}</span></a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif

        @if ($u->isSuperAdmin())
            <div class="nav-section">Platform</div>
            <a href="{{ route('admin.companies.index') }}" class="{{ $link('admin.companies.*') }}" title="Companies"><x-icon name="company"/> <span class="nav-label">Companies</span></a>
            @unless ($hasTenant)
                <a href="{{ route('admin.users.index') }}" class="{{ $link('admin.users.*') }}" title="All users"><x-icon name="users"/> <span class="nav-label">All users</span></a>
                <a href="{{ route('admin.audit-logs.index') }}" class="{{ $link('admin.audit-logs.*') }}" title="Platform audit log"><x-icon name="shield"/> <span class="nav-label">Platform audit log</span></a>
            @endunless
        @endif

        {{-- Footer lives inside the scroll area so it can never cover the last nav items --}}
        <div class="sb-footer mt-6 rounded-xl border border-line bg-soft/60 p-3">
            <div class="flex items-center gap-2 text-[12px] font-semibold text-ink"><x-icon name="shield" class="h-4 w-4 text-emerald-600"/> Schedule II compliant</div>
            <div class="mt-0.5 pl-6 text-[11.5px] text-ink3">Companies Act 2013 · Ind AS 16</div>
        </div>
    </nav>
</div>
