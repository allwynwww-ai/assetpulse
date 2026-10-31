@php
    $u = auth()->user();
    $hasTenant = Tenant::id() !== null;
    $link = fn ($route, $pattern = null) => 'nav-link '.(request()->routeIs($pattern ?? $route) ? 'nav-link-active' : '');
@endphp
<div class="flex h-full flex-col">
    <div class="sb-brand flex h-16 shrink-0 items-center gap-2.5 border-b border-slate-100 px-5">
        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 shadow-sm" title="AssetPulse">
            <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h4l3-8 4 16 3-8h4"/></svg>
        </div>
        <div class="sb-brand-text min-w-0">
            <div class="text-[15px] font-bold tracking-tight text-slate-900">Asset<span class="text-brand-600">Pulse</span></div>
            <div class="text-[10px] font-medium uppercase tracking-widest text-slate-400">Fixed Assets</div>
        </div>
        {{-- Mobile drawer close --}}
        <button type="button" class="btn-ghost -mr-2 ml-auto p-2 lg:hidden" @click="sidebarOpen = false" aria-label="Close menu">
            <x-icon name="x"/>
        </button>
    </div>

    <nav class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 pb-4">
        @if ($hasTenant)
            <div class="nav-section">Overview</div>
            <a href="{{ route('dashboard') }}" class="{{ $link('dashboard') }}" title="Dashboard"><x-icon name="home"/> <span class="nav-label">Dashboard</span></a>

            <div class="nav-section">Asset Register</div>
            <a href="{{ route('assets.index') }}" class="{{ request()->routeIs('assets.*') && ! request()->routeIs('assets.create') ? 'nav-link nav-link-active' : 'nav-link' }}" title="All Assets"><x-icon name="cube"/> <span class="nav-label">All Assets</span></a>
            @can('manage-assets')
                <a href="{{ route('assets.create') }}" class="{{ $link('assets.create') }}" title="Add Asset"><x-icon name="plus"/> <span class="nav-label">Add Asset</span></a>
            @endcan

            <div class="nav-section">Accounting</div>
            <a href="{{ route('depreciation.index') }}" class="{{ $link('depreciation.*') }}" title="Depreciation"><x-icon name="calculator"/> <span class="nav-label">Depreciation</span>
                @if ($openRuns = \App\Models\DepreciationRun::where('status', 'posted')->where('run_type', 'periodic')->count())<span class="nav-badge ml-auto rounded-full bg-sky-100 px-1.5 text-[10px] font-semibold text-sky-700" title="Posted, not finalised">{{ $openRuns }}</span>@endif</a>
            <a href="{{ route('disposals.index') }}" class="{{ $link('disposals.*') }}" title="Disposals"><x-icon name="trash"/> <span class="nav-label">Disposals</span></a>

            <div class="nav-section">Operations</div>
            <a href="{{ route('transfers.index') }}" class="{{ $link('transfers.*') }}" title="Transfers"><x-icon name="arrows"/> <span class="nav-label">Transfers</span></a>
            <a href="{{ route('pm-schedules.index') }}" class="{{ $link('pm-schedules.*') }}" title="PM Schedules"><x-icon name="calendar"/> <span class="nav-label">PM Schedules</span>
                @if ($pmDue = \App\Services\MaintenanceService::dueCount())<span class="nav-badge ml-auto rounded-full bg-rose-500 px-1.5 py-px text-[10px] font-semibold text-white" title="PM overdue or due soon">{{ $pmDue }}</span>@endif</a>
            <a href="{{ route('work-orders.index') }}" class="{{ $link('work-orders.*') }}" title="Work Orders"><x-icon name="wrench"/> <span class="nav-label">Work Orders</span>
                @if ($woOpen = \App\Models\WorkOrder::whereIn('status', ['open', 'in_progress', 'on_hold'])->count())<span class="nav-badge ml-auto rounded-full bg-amber-100 px-1.5 text-[10px] font-semibold text-amber-700" title="Open work orders">{{ $woOpen }}</span>@endif</a>

            <div class="nav-section">Insights</div>
            <a href="{{ route('reports.index') }}" class="{{ $link('reports.*') }}" title="Reports"><x-icon name="chart"/> <span class="nav-label">Reports</span></a>

            <div class="nav-section">Masters</div>
            <a href="{{ route('admin.asset-categories.index') }}" class="{{ $link('admin.asset-categories.*') }}" title="Asset Categories"><x-icon name="tag"/> <span class="nav-label">Asset Categories</span></a>
            <a href="{{ route('admin.locations.index') }}" class="{{ $link('admin.locations.*') }}" title="Locations / Plants"><x-icon name="map"/> <span class="nav-label">Locations / Plants</span></a>
            <a href="{{ route('admin.departments.index') }}" class="{{ $link('admin.departments.*') }}" title="Departments"><x-icon name="office"/> <span class="nav-label">Departments</span></a>
            <a href="{{ route('admin.cost-centers.index') }}" class="{{ $link('admin.cost-centers.*') }}" title="Cost Centers"><x-icon name="banknotes"/> <span class="nav-label">Cost Centers</span></a>
            <a href="{{ route('admin.vendors.index') }}" class="{{ $link('admin.vendors.*') }}" title="Vendors / Suppliers"><x-icon name="truck"/> <span class="nav-label">Vendors / Suppliers</span></a>

            <div class="nav-section">Configuration</div>
            <a href="{{ route('admin.depreciation-methods.index') }}" class="{{ $link('admin.depreciation-methods.*') }}" title="Depreciation Methods"><x-icon name="calculator"/> <span class="nav-label">Depreciation Methods</span></a>
            <a href="{{ route('admin.asset-statuses.index') }}" class="{{ $link('admin.asset-statuses.*') }}" title="Asset Statuses"><x-icon name="flag"/> <span class="nav-label">Asset Statuses</span></a>
            <a href="{{ route('admin.financial-years.index') }}" class="{{ $link('admin.financial-years.*') }}" title="Financial Years"><x-icon name="calendar"/> <span class="nav-label">Financial Years</span></a>
            <a href="{{ route('admin.depreciation-run-parameters.index') }}" class="{{ $link('admin.depreciation-run-parameters.*') }}" title="Run Parameters"><x-icon name="adjust"/> <span class="nav-label">Run Parameters</span></a>
            <a href="{{ route('admin.users.index') }}" class="{{ $link('admin.users.*') }}" title="Users &amp; Roles"><x-icon name="users"/> <span class="nav-label">Users &amp; Roles</span></a>
            <a href="{{ route('admin.audit-logs.index') }}" class="{{ $link('admin.audit-logs.*') }}" title="Audit Log"><x-icon name="shield"/> <span class="nav-label">Audit Log</span></a>
            @if ($u->role === \App\Enums\Role::CompanyAdmin)
                <a href="{{ route('admin.company-profile.edit') }}" class="{{ $link('admin.company-profile.*') }}" title="Company Profile"><x-icon name="building"/> <span class="nav-label">Company Profile</span></a>
            @endif
        @endif

        @if ($u->isSuperAdmin())
            <div class="nav-section">Platform</div>
            <a href="{{ route('admin.companies.index') }}" class="{{ $link('admin.companies.*') }}" title="Companies"><x-icon name="building"/> <span class="nav-label">Companies</span></a>
            @unless ($hasTenant)
                <a href="{{ route('admin.users.index') }}" class="{{ $link('admin.users.*') }}" title="All Users"><x-icon name="users"/> <span class="nav-label">All Users</span></a>
                <a href="{{ route('admin.audit-logs.index') }}" class="{{ $link('admin.audit-logs.*') }}" title="Platform Audit Log"><x-icon name="shield"/> <span class="nav-label">Platform Audit Log</span></a>
            @endunless
        @endif

        {{-- Footer lives inside the scroll area so it can never cover the last nav items --}}
        <div class="sb-footer mt-6 border-t border-slate-100 px-1 pt-4">
            <div class="rounded-xl bg-gradient-to-br from-brand-50 to-mint-50 p-3 text-xs text-slate-600">
                <div class="font-semibold text-slate-800">Schedule II compliant</div>
                Companies Act 2013 · Ind AS 16
            </div>
        </div>
    </nav>
</div>
