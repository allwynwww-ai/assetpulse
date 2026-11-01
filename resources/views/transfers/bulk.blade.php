<x-app-layout title="Bulk transfer">
    <x-slot name="header">
        <x-page-header title="Transfer {{ $selected->count() }} assets" subtitle="One destination for every selected asset. All-or-nothing: if any asset cannot move, nothing is recorded." icon="arrows" :crumbs="['Transfers' => route('transfers.index'), 'Bulk' => null]"/>
    </x-slot>
    <form method="POST" action="{{ route('transfers.store') }}" class="card max-w-4xl">
        @csrf
        <div class="border-b border-line px-5 py-4">
            <div class="form-label">Selected assets</div>
            @error('asset_ids')
                <div class="mb-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                    @foreach ($errors->get('asset_ids') as $m)<div>{{ $m }}</div>@endforeach
                </div>
            @enderror
            <div class="flex flex-wrap gap-1.5">
                @foreach ($selected as $a)
                    <input type="hidden" name="asset_ids[]" value="{{ $a->id }}">
                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-soft px-2 py-1 text-xs"><span class="font-mono font-semibold text-ink">{{ $a->asset_code }}</span><span class="text-ink3">{{ $a->location?->name }}</span></span>
                @endforeach
            </div>
        </div>
        <div class="card-body grid gap-5 sm:grid-cols-2">
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
                <input type="checkbox" name="affects_depreciation" value="1" id="ad" class="form-check mt-1" @checked(old('affects_depreciation'))>
                <label for="ad" class="text-sm text-ink2">Split depreciation by cost center from the transfer date</label>
            </div>
            <div class="sm:col-span-2"><label class="form-label">Reason *</label><textarea name="reason" rows="2" class="form-input" required minlength="5">{{ old('reason') }}</textarea>@error('reason')<p class="form-error">{{ $message }}</p>@enderror</div>
            <div class="sm:col-span-2"><label class="form-label">Remarks</label><input name="remarks" value="{{ old('remarks') }}" class="form-input"></div>
        </div>
        <div class="flex justify-end gap-2 border-t border-line px-5 py-4">
            <a href="{{ route('assets.index') }}" class="btn-secondary">Cancel</a>
            <button class="btn-primary"><x-icon name="arrows" class="h-4 w-4"/> Transfer {{ $selected->count() }} assets</button>
        </div>
    </form>
</x-app-layout>
