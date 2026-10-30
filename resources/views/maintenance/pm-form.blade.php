@php $editing = $pm->exists; @endphp
<x-app-layout :title="$editing ? 'Edit PM schedule' : 'New PM schedule'">
    <x-slot name="header">
        <x-page-header :title="$editing ? 'Edit PM schedule' : 'New PM schedule'" icon="wrench" :crumbs="['PM schedules' => route('pm-schedules.index'), ($editing ? 'Edit' : 'New') => null]"/>
    </x-slot>
    <form method="POST" action="{{ $editing ? route('pm-schedules.update', $pm) : route('pm-schedules.store') }}" class="card max-w-4xl" x-data="{ unit: '{{ old('frequency_unit', $pm->frequency_unit) }}', get meter() { return ['running_hours','shots'].includes(this.unit) } }">
        @csrf @if ($editing) @method('PUT') @endif
        <div class="card-body grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2"><label class="form-label">Asset *</label>
                <select name="asset_id" class="form-input" required @disabled($editing)>
                    <option value="">Select asset…</option>
                    @foreach ($assets as $a)<option value="{{ $a->id }}" @selected(old('asset_id', $pm->asset_id) == $a->id)>{{ $a->asset_code }} – {{ $a->name }}</option>@endforeach
                </select>
                @if ($editing)<input type="hidden" name="asset_id" value="{{ $pm->asset_id }}">@endif
                @error('asset_id')<p class="form-error">{{ $message }}</p>@enderror</div>
            <div><label class="form-label">Title *</label><input name="title" value="{{ old('title', $pm->title) }}" class="form-input" required>@error('title')<p class="form-error">{{ $message }}</p>@enderror</div>
            <div><label class="form-label">Code</label><input name="code" value="{{ old('code', $pm->code) }}" class="form-input" placeholder="e.g. PM-IMM-M"></div>
            <div><label class="form-label">Frequency *</label>
                <div class="flex gap-2">
                    <input type="number" name="frequency_interval" min="1" value="{{ old('frequency_interval', $pm->frequency_interval) }}" class="form-input w-32" required>
                    <select name="frequency_unit" x-model="unit" class="form-input">@foreach (\App\Models\PmSchedule::FREQUENCY_UNITS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                </div>@error('frequency_interval')<p class="form-error">{{ $message }}</p>@enderror</div>
            <div><label class="form-label">Start date *</label><input type="date" name="start_date" value="{{ old('start_date', optional($pm->start_date)->toDateString()) }}" class="form-input" required></div>
            <div><label class="form-label">Last done</label><input type="date" name="last_done_date" value="{{ old('last_done_date', optional($pm->last_done_date)->toDateString()) }}" class="form-input">@error('last_done_date')<p class="form-error">{{ $message }}</p>@enderror</div>
            <div><label class="form-label">Next due (override)</label><input type="date" name="next_due_date" value="{{ old('next_due_date', optional($pm->next_due_date)->toDateString()) }}" class="form-input"><p class="form-help">Leave as is to auto-calculate from the frequency.</p></div>
            <template x-if="meter">
                <div class="grid gap-5 sm:col-span-2 sm:grid-cols-3 rounded-xl bg-slate-50 p-4">
                    <div><label class="form-label">Reading at last service *</label><input type="number" step="0.01" name="meter_last_service_reading" value="{{ old('meter_last_service_reading', $pm->meter_last_service_reading) }}" class="form-input">@error('meter_last_service_reading')<p class="form-error">{{ $message }}</p>@enderror</div>
                    <div><label class="form-label">Current reading *</label><input type="number" step="0.01" name="meter_current_reading" value="{{ old('meter_current_reading', $pm->meter_current_reading) }}" class="form-input">@error('meter_current_reading')<p class="form-error">{{ $message }}</p>@enderror</div>
                    <div><label class="form-label">Average per day *</label><input type="number" step="0.01" name="meter_daily_average" value="{{ old('meter_daily_average', $pm->meter_daily_average) }}" class="form-input"><p class="form-help">Used to forecast the due date.</p>@error('meter_daily_average')<p class="form-error">{{ $message }}</p>@enderror</div>
                </div>
            </template>
            <div><label class="form-label">Alert days before *</label><input type="number" name="alert_days_before" min="0" max="365" value="{{ old('alert_days_before', $pm->alert_days_before) }}" class="form-input" required></div>
            <div><label class="form-label">Assigned to</label><input name="assigned_to" value="{{ old('assigned_to', $pm->assigned_to) }}" class="form-input"></div>
            <div><label class="form-label">Service vendor</label><select name="vendor_id" class="form-input"><option value="">In-house</option>@foreach ($vendors as $id => $n)<option value="{{ $id }}" @selected(old('vendor_id', $pm->vendor_id) == $id)>{{ $n }}</option>@endforeach</select></div>
            <div class="grid grid-cols-2 gap-3"><div><label class="form-label">Est. cost (₹)</label><input type="number" step="0.01" min="0" name="estimated_cost" value="{{ old('estimated_cost', $pm->estimated_cost) }}" class="form-input"></div><div><label class="form-label">Est. hours</label><input type="number" step="0.25" min="0" name="estimated_hours" value="{{ old('estimated_hours', $pm->estimated_hours) }}" class="form-input"></div></div>
            <div class="sm:col-span-2"><label class="form-label">Checklist</label><textarea name="checklist" rows="3" class="form-input" placeholder="One task per line">{{ old('checklist', $pm->checklist) }}</textarea></div>
            <label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-brand-600" @checked(old('is_active', $pm->is_active))> Active</label>
        </div>
        <div class="flex items-center justify-between gap-2 border-t border-slate-100 px-5 py-4">
            <div>@if ($editing)<button form="del" class="btn-ghost text-rose-600">Remove</button>@endif</div>
            <div class="flex gap-2"><a href="{{ route('pm-schedules.index') }}" class="btn-secondary">Cancel</a><button class="btn-primary">{{ $editing ? 'Save changes' : 'Create schedule' }}</button></div>
        </div>
    </form>
    @if ($editing)<form id="del" method="POST" action="{{ route('pm-schedules.destroy', $pm) }}" onsubmit="return confirm('Remove this PM schedule?')">@csrf @method('DELETE')</form>@endif
</x-app-layout>
