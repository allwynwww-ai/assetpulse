<x-app-layout title="Dispose asset">
    <x-slot name="header">
        <x-page-header title="Dispose an asset" subtitle="Charges pro-rata depreciation to the disposal date and books profit or loss. This is final." icon="trash" :crumbs="['Disposals' => route('disposals.index'), 'New' => null]"/>
    </x-slot>
    <form method="POST" action="{{ route('disposals.store') }}"
          x-data="{ asset: '{{ old('asset_id', $asset?->id) }}', date: '{{ old('disposal_date', now()->toDateString()) }}', type: '{{ old('disposal_type', 'sale') }}', sale: '{{ old('sale_value') }}', cost: '{{ old('disposal_cost') }}', p: null, loading: false,
                    async load() { if (!this.asset || !this.date) { this.p = null; return; } this.loading = true;
                        const r = await fetch('{{ route('disposals.preview') }}?' + new URLSearchParams({ asset_id: this.asset, disposal_date: this.date, sale_value: this.sale || 0, disposal_cost: this.cost || 0 }), { headers: { Accept: 'application/json' } });
                        this.p = r.ok ? await r.json() : null; this.loading = false; } }"
          x-init="load(); $watch('asset', () => load()); $watch('date', () => load()); $watch('sale', () => load()); $watch('cost', () => load())"
          class="grid gap-6 lg:grid-cols-3">
        @csrf
        <div class="card lg:col-span-2">
            <div class="card-body grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2"><label class="form-label">Asset *</label>
                    <select name="asset_id" x-model="asset" class="form-input" required>
                        <option value="">Select asset…</option>
                        @foreach ($assets as $a)<option value="{{ $a->id }}">{{ $a->asset_code }} – {{ $a->name }}</option>@endforeach
                    </select>@error('asset_id')<p class="form-error">{{ $message }}</p>@enderror</div>
                <div><label class="form-label">Disposal date *</label><input type="date" name="disposal_date" x-model="date" max="{{ now()->toDateString() }}" @if ($lockedThrough) min="{{ $lockedThrough->copy()->addDay()->toDateString() }}" @endif class="form-input" required>
                    @if ($lockedThrough)<p class="form-help">Periods are locked through {{ fmt_date($lockedThrough) }}.</p>@endif
                    @error('disposal_date')<p class="form-error">{{ $message }}</p>@enderror</div>
                <div><label class="form-label">Disposal type *</label><select name="disposal_type" x-model="type" class="form-input">@foreach (\App\Models\AssetDisposal::TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                <div><label class="form-label">Sale / realisable value (₹) <span x-show="type==='sale'">*</span></label><input type="number" step="0.01" min="0" name="sale_value" x-model.debounce.400ms="sale" class="form-input">@error('sale_value')<p class="form-error">{{ $message }}</p>@enderror</div>
                <div><label class="form-label">Cost of disposal (₹)</label><input type="number" step="0.01" min="0" name="disposal_cost" x-model.debounce.400ms="cost" class="form-input"><p class="form-help">Dismantling, transport, brokerage.</p></div>
                <div><label class="form-label">Buyer <span x-show="type==='sale'">*</span></label><input name="buyer_name" value="{{ old('buyer_name') }}" class="form-input">@error('buyer_name')<p class="form-error">{{ $message }}</p>@enderror</div>
                <div><label class="form-label">Buyer GSTIN</label><input name="buyer_gstin" value="{{ old('buyer_gstin') }}" class="form-input uppercase" maxlength="15">@error('buyer_gstin')<p class="form-error">{{ $message }}</p>@enderror</div>
                <div><label class="form-label">Invoice no.</label><input name="sale_invoice_number" value="{{ old('sale_invoice_number') }}" class="form-input"></div>
                <div class="sm:col-span-2"><label class="form-label">Reason *</label><textarea name="reason" rows="2" class="form-input" required minlength="5">{{ old('reason') }}</textarea>@error('reason')<p class="form-error">{{ $message }}</p>@enderror</div>
                <label class="flex items-start gap-2 text-sm sm:col-span-2"><input type="checkbox" name="confirm" value="1" class="mt-0.5 rounded border-slate-300 text-rose-600"> I understand disposal is final; the asset becomes read-only and its PM schedules and open work orders are closed.</label>
                @error('confirm')<p class="form-error sm:col-span-2">{{ $message }}</p>@enderror
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4"><a href="{{ url()->previous() }}" class="btn-secondary">Cancel</a><button class="btn-danger"><x-icon name="trash" class="h-4 w-4"/> Record disposal</button></div>
        </div>
        <div class="card self-start">
            <div class="card-header"><div class="card-title">Disposal calculation</div><span x-show="loading" class="text-xs text-slate-400">calculating…</span></div>
            <div class="card-body space-y-2 text-sm" x-show="p" x-cloak>
                <div class="flex justify-between"><span class="text-slate-500">Gross block</span><span class="tabular-nums" x-text="p?.fmt.gross"></span></div>
                <div class="flex justify-between"><span class="text-slate-500">Depreciation posted through</span><span x-text="p?.last_dep"></span></div>
                <div class="flex justify-between"><span class="text-slate-500">Pro-rata charge to disposal</span><span class="tabular-nums" x-text="p?.fmt.charge"></span></div>
                <div class="flex justify-between"><span class="text-slate-500">Accumulated depreciation</span><span class="tabular-nums" x-text="p?.fmt.accumulated"></span></div>
                <div class="flex justify-between border-t border-slate-100 pt-2 font-medium"><span>Net book value</span><span class="tabular-nums" x-text="p?.fmt.nbv"></span></div>
                <div class="flex justify-between rounded-lg px-3 py-2 font-semibold" :class="p?.pl < 0 ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700'">
                    <span x-text="p?.pl < 0 ? 'Loss on disposal' : 'Profit on disposal'"></span><span class="tabular-nums" x-text="p?.fmt.pl"></span></div>
                <p x-show="p?.warning" class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800" x-text="p?.warning"></p>
            </div>
            <div class="card-body text-sm text-slate-400" x-show="!p">Pick an asset and date to see the figures.</div>
        </div>
    </form>
</x-app-layout>
