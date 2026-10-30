@php
    $u = auth()->user();
    $hasTenant = Tenant::id() !== null;
    $link = fn ($route, $pattern = null) => 'nav-link '.(request()->routeIs($pattern ?? $route) ? 'nav-link-active' : '');
@endphp
<div class="flex h-full flex-col">
    <div class="flex h-16 items-center gap-2.5 border-b border-slate-100 px-5">
        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 shadow-sm">
            <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h4l3-8 4 16 3-8h4"/></svg>
        </div>
        <div>
            <div class="text-[15px] font-bold tracking-tight text-slate-900">Asset<span class="text-brand-600">Pulse</span></div>
            <div class="text-[10px] font-medium uppercase tracking-widest text-slate-400">Fixed Assets</div>
        </div>
    </div>

    <nav class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 pb-4">
        @if ($hasTenant)
            <div class="nav-section">Overview</div>
            <a href="{{ route('dashboard') }}" class="{{ $link('dashboard') }}"><x-icon name="home"/> Dashboard</a>

            <div class="nav-section">Asset Register</div>
            <a href="{{ route('assets.index') }}" class="{{ request()->routeIs('assets.*') && ! request()->routeIs('assets.create') ? 'nav-link nav-link-active' : 'nav-link' }}"><x-icon name="cube"/> All Assets</a>
            @can('manage-assets')
                <a href="{{ route('assets.create') }}" class="{{ $link('assets.create') }}"><x-icon name="plus"/> Add Asset</a>
            @endcan

            <div class="nav-section">Accounting</div>
            <a href="{{ route('depreciation.index') }}" class="{{ $link('depreciation.*') }}"><x-icon name="calculator"/> Depreciation
                @if ($openRuns = \App\Models\DepreciationRun::where('status', 'posted')->where('run_type', 'periodic')->count())<span class="ml-auto rounded-full bg-sky-100 px-1.5 text-[10px] font-semibold text-sky-700" title="Posted, not finalised">{{ $openRuns }}</span>@endif</a>
            <a href="{{ route('disposals.index') }}" class="{{ $link('disposals.*') }}"><x-icon name="trash"/> Disposals</a>

            <div class="nav-section">Operations</div>
            <a href="{{ route('transfers.index') }}" class="{{ $link('transfers.*') }}"><x-icon name="arrows"/> Transfers</a>
            <a href="{{ route('pm-schedules.index') }}" class="{{ $link('pm-schedules.*') }}"><x-icon name="calendar"/> PM Schedules
                @if ($pmDue = \App\Services\MaintenanceService::dueCount())<span class="ml-auto rounded-full bg-rose-500 px-1.5 py-px text-[10px] font-semibold text-white" title="PM overdue or due soon">{{ $pmDue }}</span>@endif</a>
            <a href="{{ route('work-orders.index') }}" class="{{ $link('work-orders.*') }}"><x-icon name="wrench"/> Work Orders
                @if ($woOpen = \App\Models\WorkOrder::whereIn('status', ['open', 'in_progress', 'on_hold'])->count())<span class="ml-auto rounded-full bg-amber-100 px-1.5 text-[10px] font-semibold text-amber-700" title="Open work orders">{{ $woOpen }}</span>@endif</a>

            <div class="nav-section">Insights</div>
            <a href="{{ route('reports.index') }}" class="{{ $link('reports.*') }}"><x-icon name="chart"/> Reports</a>

            <div class="nav-section">Masters</div>
            <a href="{{ route('admin.asset-categories.index') }}" class="{{ $link('admin.asset-categories.*') }}"><x-icon name="tag"/> Asset Categories</a>
            <a href="{{ route('admin.locations.index') }}" class="{{ $link('admin.locations.*') }}"><x-icon name="map"/> Locations / Plants</a>
            <a href="{{ route('admin.departments.index') }}" class="{{ $link('admin.departments.*') }}"><x-icon name="office"/> Departments</a>
            <a href="{{ route('admin.cost-centers.index') }}" class="{{ $link('admin.cost-centers.*') }}"><x-icon name="banknotes"/> Cost Centers</a>
            <a href="{{ route('admin.vendors.index') }}" class="{{ $link('admin.vendors.*') }}"><x-icon name="truck"/> Vendors / Suppliers</a>

            <div class="nav-section">Configuration</div>
            <a href="{{ route('admin.depreciation-methods.index') }}" class="{{ $link('admin.depreciation-methods.*') }}"><x-icon name="calculator"/> Depreciation Methods</a>
            <a href="{{ route('admin.asset-statuses.index') }}" class="{{ $link('admin.asset-statuses.*') }}"><x-icon name="flag"/> Asset Statuses</a>
            <a href="{{ route('admin.financial-years.index') }}" class="{{ $link('admin.financial-years.*') }}"><x-icon name="calendar"/> Financial Years</a>
            <a href="{{ route('admin.depreciation-run-parameters.index') }}" class="{{ $link('admin.depreciation-run-parameters.*') }}"><x-icon name="adjust"/> Run Parameters</a>
            <a href="{{ route('admin.users.index') }}" class="{{ $link('admin.users.*') }}"><x-icon name="users"/> Users &amp; Roles</a>
            <a href="{{ route('admin.audit-logs.index') }}" class="{{ $link('admin.audit-logs.*') }}"><x-icon name="shield"/> Audit Log</a>
            @if ($u->role === \App\Enums\Role::CompanyAdmin)
                <a href="{{ route('admin.company-profile.edit') }}" class="{{ $link('admin.company-profile.*') }}"><x-icon name="building"/> Company Profile</a>
            @endif
        @endif

        @if ($u->isSuperAdmin())
            <div class="nav-section">Platform</div>
            <a href="{{ route('admin.companies.index') }}" class="{{ $link('admin.companies.*') }}"><x-icon name="building"/> Companies</a>
            @unless ($hasTenant)
                <a href="{{ route('admin.users.index') }}" class="{{ $link('admin.users.*') }}"><x-icon name="users"/> All Users</a>
                <a href="{{ route('admin.audit-logs.index') }}" class="{{ $link('admin.audit-logs.*') }}"><x-icon name="shield"/> Platform Audit Log</a>
            @endunless
        @endif

        {{-- Footer lives inside the scroll area so it can never cover the last nav items --}}
        <div class="mt-6 border-t border-slate-100 px-1 pt-4">
            <div class="rounded-xl bg-gradient-to-br from-brand-50 to-mint-50 p-3 text-xs text-slate-600">
                <div class="font-semibold text-slate-800">Schedule II compliant</div>
                Companies Act 2013 · Ind AS 16
            </div>
        </div>
    </nav>
</div>
