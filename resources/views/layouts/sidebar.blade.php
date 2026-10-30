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

    <nav class="flex-1 overflow-y-auto px-3 pb-6">
        @if ($hasTenant)
            <div class="nav-section">Overview</div>
            <a href="{{ route('dashboard') }}" class="{{ $link('dashboard') }}"><x-icon name="home"/> Dashboard</a>

            <div class="nav-section">Asset Register</div>
            <a href="{{ route('assets.index') }}" class="{{ request()->routeIs('assets.*') && ! request()->routeIs('assets.create') ? 'nav-link nav-link-active' : 'nav-link' }}"><x-icon name="cube"/> All Assets</a>
            @can('manage-assets')
                <a href="{{ route('assets.create') }}" class="{{ $link('assets.create') }}"><x-icon name="plus"/> Add Asset</a>
            @endcan

            <div class="nav-section">Operations <span class="ml-1 rounded bg-slate-100 px-1 py-px text-[9px] tracking-normal text-slate-400">Phase 2</span></div>
            @foreach ([['calculator', 'Depreciation'], ['arrows', 'Transfers'], ['wrench', 'Maintenance'], ['trash', 'Disposals'], ['chart', 'Reports']] as [$ic, $lbl])
                <span class="nav-link cursor-not-allowed opacity-50" title="Coming in phase 2"><x-icon :name="$ic"/> {{ $lbl }}</span>
            @endforeach

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
    </nav>

    <div class="border-t border-slate-100 p-4">
        <div class="rounded-xl bg-gradient-to-br from-brand-50 to-mint-50 p-3 text-xs text-slate-600">
            <div class="font-semibold text-slate-800">Schedule II compliant</div>
            Companies Act 2013 · Ind AS 16
        </div>
    </div>
</div>
