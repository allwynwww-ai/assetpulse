@php $editing = $wo->exists; @endphp
<x-app-layout :title="$editing ? 'Edit '.$wo->wo_number : 'New work order'">
    <x-slot name="header">
        <x-page-header :title="$editing ? 'Edit '.$wo->wo_number : ($wo->type === 'breakdown' ? 'Report a breakdown' : 'New work order')" icon="clipboard" :crumbs="['Work orders' => route('work-orders.index'), ($editing ? $wo->wo_number : 'New') => null]"/>
    </x-slot>
    <form method="POST" action="{{ $editing ? route('work-orders.update', $wo) : route('work-orders.store') }}" class="card max-w-4xl" x-data="{ asset: '{{ old('asset_id', $wo->asset_id) }}', pms: @js($pms) }">
        @csrf @if ($editing) @method('PUT') @endif
        <div class="card-body grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2"><label class="form-label">Asset *</label>
                <select name="asset_id" x-model="asset" class="form-input" required @disabled($editing)>
                    <option value="">Select asset…</option>
                    @foreach ($assets as $a)<option value="{{ $a->id }}">{{ $a->asset_code }} – {{ $a->name }}</option>@endforeach
                </select>
                @if ($editing)<input type="hidden" name="asset_id" value="{{ $wo->asset_id }}">@endif
                @error('asset_id')<p class="form-error">{{ $message }}</p>@enderror</div>
            <div><label class="form-label">Type *</label><select name="type" class="form-input">@foreach (\App\Models\WorkOrder::TYPES as $k => $l)<option value="{{ $k }}" @selected(old('type', $wo->type) === $k)>{{ $l }}</option>@endforeach</select>
                <p class="form-help">An open breakdown marks the asset “Under Maintenance”.</p></div>
            <div><label class="form-label">Priority *</label><select name="priority" class="form-input">@foreach (\App\Models\WorkOrder::PRIORITIES as $k => $l)<option value="{{ $k }}" @selected(old('priority', $wo->priority) === $k)>{{ $l }}</option>@endforeach</select></div>
            <div><label class="form-label">PM schedule</label>
                <select name="pm_schedule_id" class="form-input"><option value="">— none —</option>
                    <template x-for="p in pms.filter(p => String(p.asset_id) === String(asset))" :key="p.id"><option :value="p.id" x-text="p.title" :selected="String(p.id) === '{{ old('pm_schedule_id', $wo->pm_schedule_id) }}'"></option></template>
                </select>@error('pm_schedule_id')<p class="form-error">{{ $message }}</p>@enderror</div>
            <div><label class="form-label">Title *</label><input name="title" value="{{ old('title', $wo->title) }}" class="form-input" required>@error('title')<p class="form-error">{{ $message }}</p>@enderror</div>
            <div class="sm:col-span-2"><label class="form-label">Description / problem</label><textarea name="description" rows="3" class="form-input">{{ old('description', $wo->description) }}</textarea></div>
            <div><label class="form-label">Reported date *</label><input type="date" name="reported_date" max="{{ now()->toDateString() }}" value="{{ old('reported_date', optional($wo->reported_date)->toDateString()) }}" class="form-input" required>@error('reported_date')<p class="form-error">{{ $message }}</p>@enderror</div>
            <div><label class="form-label">Scheduled date</label><input type="date" name="scheduled_date" value="{{ old('scheduled_date', optional($wo->scheduled_date)->toDateString()) }}" class="form-input"></div>
            <div><label class="form-label">Assigned to</label><input name="assigned_to" value="{{ old('assigned_to', $wo->assigned_to) }}" class="form-input"></div>
            <div><label class="form-label">Vendor</label><select name="vendor_id" class="form-input"><option value="">In-house</option>@foreach ($vendors as $id => $n)<option value="{{ $id }}" @selected(old('vendor_id', $wo->vendor_id) == $id)>{{ $n }}</option>@endforeach</select></div>
            @if ($editing)
                <div class="grid grid-cols-2 gap-3 sm:col-span-2 lg:grid-cols-4">
                    @foreach (['labour_cost' => 'Labour (₹)', 'parts_cost' => 'Parts (₹)', 'external_cost' => 'External (₹)', 'downtime_hours' => 'Downtime (h)'] as $f => $l)
                        <div><label class="form-label">{{ $l }}</label><input type="number" step="0.01" min="0" name="{{ $f }}" value="{{ old($f, $wo->$f) }}" class="form-input"></div>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4"><a href="{{ url()->previous() }}" class="btn-secondary">Cancel</a><button class="btn-primary">{{ $editing ? 'Save changes' : 'Raise work order' }}</button></div>
    </form>
</x-app-layout>
