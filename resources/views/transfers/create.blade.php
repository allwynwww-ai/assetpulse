@php
    $assetJson = $assets->mapWithKeys(fn ($a) => [$a->id => ['loc' => $a->location_id, 'dept' => $a->department_id, 'cc' => $a->cost_center_id, 'cust' => $a->custodian]]);
    $sel = old('asset_id', $asset?->id);
@endphp
<x-app-layout title="New transfer">
    <x-slot name="header">
        <x-page-header title="Transfer an asset" subtitle="Effective immediately; recorded in the asset history and audit trail." icon="arrows" :crumbs="['Transfers' => route('transfers.index'), 'New' => null]"/>
    </x-slot>
    <form method="POST" action="{{ route('transfers.store') }}" class="card max-w-4xl"
          x-data="{ assets: @js($assetJson), id: '{{ $sel }}', locs: @js($locations), depts: @js($departments), ccs: @js($costCenters),
                    get cur() { return this.assets[this.id] || {} } }">
        @csrf
        <div class="card-body grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="form-label">Asset *</label>
                <select name="asset_id" x-model="id" class="form-input" required>
                    <option value="">Select asset…</option>
                    @foreach ($assets as $a)<option value="{{ $a->id }}" @selected($sel == $a->id)>{{ $a->asset_code }} – {{ $a->name }}</option>@endforeach
                </select>
                @error('asset_id')<p class="form-error">{{ $message }}</p>@enderror
                <div x-show="id" x-cloak class="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                    Currently at <b x-text="locs[cur.loc] || '—'"></b> · <span x-text="depts[cur.dept] || 'no department'"></span> · <span x-text="ccs[cur.cc] || 'no cost center'"></span> · custodian <span x-text="cur.cust || '—'"></span>
                </div>
            </div>
            <div><label class="form-label">Transfer date *</label><input type="date" name="transfer_date" value="{{ old('transfer_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="form-input" required>@error('transfer_date')<p class="form-error">{{ $message }}</p>@enderror</div>
            <div><label class="form-label">To location / plant *</label>
                <select name="to_location_id" class="form-input" required><option value="">Select…</option>@foreach ($locations as $id => $n)<option value="{{ $id }}" @selected(old('to_location_id') == $id)>{{ $n }}</option>@endforeach</select>
                @error('to_location_id')<p class="form-error">{{ $message }}</p>@enderror</div>
            <div><label class="form-label">To department</label>
                <select name="to_department_id" class="form-input"><option value="">(unchanged)</option>@foreach ($departments as $id => $n)<option value="{{ $id }}" @selected(old('to_department_id') == $id)>{{ $n }}</option>@endforeach</select></div>
            <div><label class="form-label">To cost center</label>
                <select name="to_cost_center_id" class="form-input"><option value="">(unchanged)</option>@foreach ($costCenters as $id => $n)<option value="{{ $id }}" @selected(old('to_cost_center_id') == $id)>{{ $n }}</option>@endforeach</select></div>
            <div><label class="form-label">New custodian</label><input name="to_custodian" value="{{ old('to_custodian') }}" class="form-input" placeholder="(unchanged)"></div>
            <div class="flex items-start gap-2 pt-7">
                <input type="checkbox" name="affects_depreciation" value="1" id="ad" class="mt-1 rounded border-slate-300 text-brand-600" @checked(old('affects_depreciation'))>
                <label for="ad" class="text-sm text-slate-700">Split depreciation by cost center from the transfer date<div class="form-help">The period charge is apportioned by days between old and new cost centers.</div></label>
            </div>
            <div class="sm:col-span-2"><label class="form-label">Reason *</label><textarea name="reason" rows="2" class="form-input" required minlength="5">{{ old('reason') }}</textarea>@error('reason')<p class="form-error">{{ $message }}</p>@enderror</div>
            <div class="sm:col-span-2"><label class="form-label">Remarks</label><input name="remarks" value="{{ old('remarks') }}" class="form-input"></div>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4">
            <a href="{{ url()->previous() }}" class="btn-secondary">Cancel</a>
            <button class="btn-primary"><x-icon name="arrows" class="h-4 w-4"/> Record transfer</button>
        </div>
    </form>
</x-app-layout>
