@php
    $editing = $asset->exists;
    $title = $editing ? 'Edit '.$asset->asset_code : 'Add asset';
    $f = fn ($name, $label, $type = 'text', $extra = []) => array_merge(['name' => $name, 'label' => $label, 'type' => $type], $extra);
    $v = fn ($k) => old($k, $asset->{$k} instanceof \Carbon\CarbonInterface ? $asset->{$k}->format('Y-m-d') : $asset->{$k});
@endphp
<x-app-layout :title="$title">
    <x-slot name="header">
        <x-page-header :title="$title" :subtitle="$editing ? $asset->name : 'Pick a category first — depreciation defaults and the asset code are filled in for you.'" icon="cube"
                       :crumbs="['Assets' => route('assets.index'), ($editing ? $asset->asset_code : 'New') => null]"/>
    </x-slot>

    <form method="POST" enctype="multipart/form-data" novalidate
          action="{{ $editing ? route('assets.update', $asset) : route('assets.store') }}"
          x-data="assetForm(@js($categoryData), @js($lookups['methodCodes']), {
              category: @js((string) old('asset_category_id', $asset->asset_category_id)),
              method: @js((string) $v('depreciation_method_id')),
              years: @js($v('useful_life_years')),
              months: @js($v('useful_life_months')),
              wdv: @js($v('wdv_rate')),
              cost: @js($v('original_cost')),
              residual: @js($v('residual_value')),
              opening: @js($v('opening_accumulated_depreciation')),
              code: @js(old('asset_code', $editing ? $asset->asset_code : '')),
              editing: {{ $editing ? 'true' : 'false' }},
          })">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                {{-- Identification --}}
                <div class="card">
                    <div class="card-header"><div class="card-title">Identification</div></div>
                    <div class="card-body grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <label class="form-label" for="f_asset_category_id">Category <span class="text-rose-500">*</span></label>
                            <select id="f_asset_category_id" name="asset_category_id" class="form-input" x-model="category" @change="applyCategory()">
                                <option value="">— Select category —</option>
                                @foreach ($lookups['categories'] as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                            </select>
                            <p class="form-help" x-show="cat()?.schedule" x-text="'Schedule II: ' + (cat()?.schedule ?? '')"></p>
                            @error('asset_category_id')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="form-label" for="f_asset_code">Asset code</label>
                            <input id="f_asset_code" name="asset_code" x-model="code" class="form-input font-mono uppercase" :placeholder="editing ? '' : (cat()?.next_code ?? 'Auto-generated')" maxlength="40">
                            <p class="form-help" x-show="!editing">Leave blank for the next number<span x-show="cat()"> (<span class="font-mono" x-text="cat()?.next_code"></span>)</span>, or type your own.</p>
                            @error('asset_code')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        @include('admin.master._field', ['record' => $asset, 'field' => $f('asset_status_id', 'Status', 'select', ['options' => $lookups['statuses'], 'required' => true, 'placeholder' => false])])
                        @include('admin.master._field', ['record' => $asset, 'field' => $f('name', 'Asset name', 'text', ['required' => true, 'span' => 2, 'placeholder' => 'e.g. Engel Victory 200T Injection Moulding Machine'])])
                        @include('admin.master._field', ['record' => $asset, 'field' => $f('asset_tag', 'Asset tag / label no.')])
                        @include('admin.master._field', ['record' => $asset, 'field' => $f('manufacturer', 'Manufacturer / make')])
                        @include('admin.master._field', ['record' => $asset, 'field' => $f('model', 'Model')])
                        @include('admin.master._field', ['record' => $asset, 'field' => $f('serial_number', 'Serial number')])
                        @include('admin.master._field', ['record' => $asset, 'field' => $f('quantity', 'Quantity', 'number', ['min' => 1])])
                        @include('admin.master._field', ['record' => $asset, 'field' => $f('unit_of_measure', 'Unit')])
                        @include('admin.master._field', ['record' => $asset, 'field' => $f('parent_asset_id', 'Parent asset', 'select', ['options' => $lookups['parents'], 'placeholder' => '— None (stand-alone) —', 'help' => 'For components, e.g. a mould attached to a machine.'])])
                        @include('admin.master._field', ['record' => $asset, 'field' => $f('description', 'Description', 'textarea', ['span' => 'full', 'rows' => 2])])
                    </div>
                </div>

                {{-- Where --}}
                <div class="card">
                    <div class="card-header"><div class="card-title">Location & ownership</div></div>
                    <div class="card-body grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                        @include('admin.master._field', ['record' => $asset, 'field' => $f('location_id', 'Location / Plant', 'select', ['options' => $lookups['locations']])])
                        @include('admin.master._field', ['record' => $asset, 'field' => $f('department_id', 'Department', 'select', ['options' => $lookups['departments']])])
                        @include('admin.master._field', ['record' => $asset, 'field' => $f('cost_center_id', 'Cost center', 'select', ['options' => $lookups['costCenters']])])
                        @include('admin.master._field', ['record' => $asset, 'field' => $f('custodian', 'Custodian')])
                    </div>
                </div>

                {{-- Purchase --}}
                <div class="card">
                    <div class="card-header"><div class="card-title">Purchase & capitalisation</div></div>
                    <div class="card-body grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                        @include('admin.master._field', ['record' => $asset, 'field' => $f('vendor_id', 'Vendor', 'select', ['options' => $lookups['vendors']])])
                        @include('admin.master._field', ['record' => $asset, 'field' => $f('purchase_order_number', 'PO number')])
                        @include('admin.master._field', ['record' => $asset, 'field' => $f('purchase_date', 'Purchase date', 'date')])
                        @include('admin.master._field', ['record' => $asset, 'field' => $f('invoice_number', 'Invoice number')])
                        @include('admin.master._field', ['record' => $asset, 'field' => $f('invoice_date', 'Invoice date', 'date')])
                        @include('admin.master._field', ['record' => $asset, 'field' => $f('capitalization_date', 'Put-to-use / capitalisation date', 'date', ['required' => true, 'help' => 'Depreciation starts from this date.'])])
                        <div>
                            <label class="form-label" for="f_original_cost">Original cost <span class="text-rose-500">*</span></label>
                            <div class="relative"><span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-slate-400">₹</span>
                                <input id="f_original_cost" type="number" step="0.01" min="0" name="original_cost" x-model="cost" @input="syncResidual()" class="form-input pl-7 tabular-nums"></div>
                            <p class="form-help" x-show="cat() && Number(cost) > 0 && Number(cost) < cat().threshold" x-cloak>Below the category capitalisation threshold (<span x-text="inr(cat()?.threshold)"></span>).</p>
                            @error('original_cost')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                {{-- Depreciation --}}
                <div class="card">
                    <div class="card-header">
                        <div><div class="card-title">Depreciation</div><div class="text-xs text-slate-400">Defaulted from the category; override if this asset differs.</div></div>
                        <button type="button" class="btn-ghost btn-sm" x-show="cat()" @click="applyCategory(true)"><x-icon name="restore" class="h-3.5 w-3.5"/> Reset to category</button>
                    </div>
                    <div class="card-body grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <label class="form-label" for="f_depreciation_method_id">Method <span class="text-rose-500">*</span></label>
                            <select id="f_depreciation_method_id" name="depreciation_method_id" class="form-input" x-model="method">
                                <option value="">— Select —</option>
                                @foreach ($lookups['methods'] as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                            </select>
                            @error('depreciation_method_id')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div><label class="form-label" for="f_years">Life (years) <span class="text-rose-500">*</span></label><input id="f_years" type="number" min="0" name="useful_life_years" x-model="years" class="form-input tabular-nums"></div>
                            <div><label class="form-label" for="f_months">+ months</label><input id="f_months" type="number" min="0" max="11" name="useful_life_months" x-model="months" class="form-input tabular-nums"></div>
                            @error('useful_life_years')<p class="form-error col-span-2">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="form-label" for="f_residual">Residual value</label>
                            <div class="relative"><span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-slate-400">₹</span>
                                <input id="f_residual" type="number" step="0.01" min="0" name="residual_value" x-model="residual" @input="residualTouched = true" class="form-input pl-7 tabular-nums"></div>
                            <p class="form-help" x-show="cat()">Category: <span x-text="cat()?.residual_pct"></span>% of cost</p>
                            @error('residual_value')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div x-show="methodCode() === 'WDV'" x-cloak>
                            <label class="form-label" for="f_wdv">WDV rate</label>
                            <div class="relative"><input id="f_wdv" type="number" step="0.0001" name="wdv_rate" x-model="wdv" class="form-input pr-8 tabular-nums"><span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">%</span></div>
                            @error('wdv_rate')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <template x-if="methodCode() === 'UOP'">
                            <div class="contents">
                                <div>
                                    <label class="form-label" for="f_units">Total units capacity <span class="text-rose-500">*</span></label>
                                    <input id="f_units" type="number" step="0.01" min="0" name="total_units_capacity" value="{{ $v('total_units_capacity') }}" class="form-input tabular-nums" placeholder="Lifetime shots / hours">
                                </div>
                                <div>
                                    <label class="form-label" for="f_consumed">Units consumed to date</label>
                                    <input id="f_consumed" type="number" step="0.01" min="0" name="units_consumed" value="{{ $v('units_consumed') }}" class="form-input tabular-nums">
                                </div>
                            </div>
                        </template>
                        @error('total_units_capacity')<p class="form-error md:col-span-2 lg:col-span-3">{{ $message }}</p>@enderror
                    </div>
                    <div class="border-t border-slate-100 bg-slate-50/60 px-5 py-4">
                        <div class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Opening balance (takeover / migration)</div>
                        <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                            @include('admin.master._field', ['record' => $asset, 'field' => $f('opening_balance_date', 'As at date', 'date', ['help' => 'Date up to which the opening depreciation was charged.'])])
                            <div>
                                <label class="form-label" for="f_opening">Opening accumulated depreciation</label>
                                <div class="relative"><span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-slate-400">₹</span>
                                    <input id="f_opening" type="number" step="0.01" min="0" name="opening_accumulated_depreciation" x-model="opening" class="form-input pl-7 tabular-nums"></div>
                                @error('opening_accumulated_depreciation')<p class="form-error">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Warranty --}}
                <div class="card">
                    <div class="card-header"><div class="card-title">Warranty, insurance & notes</div></div>
                    <div class="card-body grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                        @include('admin.master._field', ['record' => $asset, 'field' => $f('warranty_expiry_date', 'Warranty expiry', 'date')])
                        @include('admin.master._field', ['record' => $asset, 'field' => $f('insurance_policy_number', 'Insurance policy no.')])
                        @include('admin.master._field', ['record' => $asset, 'field' => $f('insurance_expiry_date', 'Insurance expiry', 'date')])
                        @include('admin.master._field', ['record' => $asset, 'field' => $f('notes', 'Notes', 'textarea', ['span' => 'full', 'rows' => 2])])
                    </div>
                </div>
            </div>

            {{-- Right rail: live summary + attachments --}}
            <div class="space-y-6">
                <div class="card xl:sticky xl:top-20">
                    <div class="card-header"><div class="card-title">Summary</div></div>
                    <div class="card-body space-y-3 text-sm">
                        <div class="flex justify-between"><span class="text-slate-500">Asset code</span><span class="font-mono font-semibold text-brand-700" x-text="code || (cat()?.next_code ?? '—')"></span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Method</span><span class="font-medium" x-text="methodCode() || '—'"></span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Useful life</span><span class="font-medium" x-text="lifeLabel()"></span></div>
                        <hr class="border-slate-100">
                        <div class="flex justify-between"><span class="text-slate-500">Original cost</span><span class="tabular-nums" x-text="inr(cost)"></span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Residual value</span><span class="tabular-nums" x-text="inr(residual)"></span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Depreciable amount</span><span class="tabular-nums" x-text="inr(Math.max(0, cost - residual))"></span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Accumulated dep.</span><span class="tabular-nums text-amber-700" x-text="inr(opening)"></span></div>
                        <div class="flex justify-between rounded-lg bg-mint-50 px-3 py-2 font-semibold text-mint-800"><span>Net book value</span><span class="tabular-nums" x-text="inr(cost - opening)"></span></div>
                        <div class="flex justify-between text-xs text-slate-400" x-show="methodCode() === 'SLM' && lifeMonths() > 0"><span>SLM charge / year</span><span class="tabular-nums" x-text="inr(Math.max(0, cost - residual) / (lifeMonths() / 12))"></span></div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><div class="card-title">Attachments</div><x-icon name="paperclip" class="h-4 w-4 text-slate-400"/></div>
                    <div class="card-body space-y-3">
                        <select name="attachment_type" class="form-input text-sm">
                            @foreach (\App\Models\AssetAttachment::TYPES as $k => $lbl)<option value="{{ $k }}" @selected(old('attachment_type', 'invoice') === $k)>{{ $lbl }}</option>@endforeach
                        </select>
                        <input type="file" name="attachments[]" multiple class="block w-full text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700 hover:file:bg-brand-100">
                        <p class="form-help">Invoice, photos, warranty card… PDF, images, Office files up to 10 MB each.</p>
                        @error('attachments.*')<p class="form-error">{{ $message }}</p>@enderror
                        @if ($editing && $asset->attachments()->count())
                            <p class="text-xs text-slate-400">{{ $asset->attachments()->count() }} file(s) already attached — manage them on the asset page.</p>
                        @endif
                    </div>
                </div>

                <div class="flex gap-2">
                    <a href="{{ $editing ? route('assets.show', $asset) : route('assets.index') }}" class="btn-secondary flex-1 justify-center">Cancel</a>
                    <button class="btn-primary flex-1 justify-center"><x-icon name="check" class="h-4 w-4"/> {{ $editing ? 'Save changes' : 'Add to register' }}</button>
                </div>
            </div>
        </div>
    </form>

    <script>
        function assetForm(categories, methodCodes, init) {
            return {
                categories, methodCodes, ...init,
                residualTouched: init.editing || (init.residual !== null && init.residual !== '' && Number(init.residual) > 0),
                cat() { return this.categories[this.category] ?? null; },
                methodCode() { return this.methodCodes[this.method] ?? ''; },
                lifeMonths() { return (Number(this.years) || 0) * 12 + (Number(this.months) || 0); },
                lifeLabel() { const y = Number(this.years) || 0, m = Number(this.months) || 0; return (y || m) ? `${y ? y + ' yrs' : ''} ${m ? m + ' mths' : ''}`.trim() : '—'; },
                inr(v) { const n = Number(v) || 0; return '₹ ' + n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
                applyCategory(force = false) {
                    const c = this.cat(); if (!c) return;
                    if (force || !this.editing) {
                        this.method = String(c.method ?? '');
                        this.years = c.years; this.months = c.months;
                        this.wdv = c.wdv_rate;
                        this.residualTouched = false; this.syncResidual();
                    }
                },
                syncResidual() {
                    const c = this.cat();
                    if (c && !this.residualTouched && Number(this.cost) > 0) {
                        this.residual = (Number(this.cost) * c.residual_pct / 100).toFixed(2);
                    }
                },
                init() { if (!this.editing && this.category && !this.method) this.applyCategory(); },
            };
        }
    </script>
</x-app-layout>
