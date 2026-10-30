@php
    $canWrite = auth()->user()->canManageAssets();
    $s = $asset->status;
    $row = fn ($label, $value) => '<div><dt class="dl-label">'.e($label).'</dt><dd class="dl-value">'.($value === null || $value === '' ? '<span class="text-slate-300">—</span>' : e($value)).'</dd></div>';
@endphp
<x-app-layout :title="$asset->asset_code">
    <x-slot name="header">
        <x-page-header :title="$asset->name" icon="cube" :crumbs="['Assets' => route('assets.index'), $asset->asset_code => null]">
            <a href="{{ route('assets.label', $asset) }}" target="_blank" class="btn-secondary"><x-icon name="printer" class="h-4 w-4"/> Print label</a>
            @if ($canWrite && ! $asset->trashed())
                <a href="{{ route('assets.edit', $asset) }}" class="btn-primary"><x-icon name="pencil" class="h-4 w-4"/> Edit</a>
                <form method="POST" action="{{ route('assets.destroy', $asset) }}" onsubmit="return confirm('Deactivate {{ $asset->asset_code }}? It will be hidden from the register but can be restored.')">@csrf @method('DELETE')
                    <button class="btn-ghost text-rose-600 hover:bg-rose-50"><x-icon name="trash" class="h-4 w-4"/> Deactivate</button>
                </form>
            @endif
        </x-page-header>
        <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
            <span class="rounded-md bg-brand-50 px-2 py-0.5 font-mono text-xs font-semibold text-brand-700 ring-1 ring-brand-200">{{ $asset->asset_code }}</span>
            @if ($s)<span class="badge bg-{{ $s->color }}-50 text-{{ $s->color }}-700 ring-{{ $s->color }}-200">{{ $s->name }}</span>@endif
            @if ($asset->trashed())<span class="badge bg-slate-100 text-slate-600 ring-slate-200">Inactive</span>@endif
            <span class="text-slate-400">·</span><span class="text-slate-600">{{ $asset->category?->name }}</span>
            @if ($asset->location)<span class="text-slate-400">·</span><span class="text-slate-600">{{ $asset->location->name }}</span>@endif
        </div>
    </x-slot>

    {{-- Value KPIs --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card p-5"><div class="stat-label">Original cost</div><div class="stat-value">{{ inr($asset->original_cost) }}</div><div class="mt-1 text-xs text-slate-400">Residual {{ inr($asset->residual_value) }}</div></div>
        <div class="card p-5"><div class="stat-label">Accumulated depreciation</div><div class="stat-value text-amber-700">{{ inr($asset->accumulated_depreciation) }}</div>
            <div class="mt-2 h-1.5 w-full rounded-full bg-slate-100"><div class="h-1.5 rounded-full bg-amber-400" style="width: {{ $asset->depreciated_percent }}%"></div></div>
            <div class="mt-1 text-xs text-slate-400">{{ $asset->depreciated_percent }}% of depreciable amount</div></div>
        <div class="card p-5 ring-1 ring-mint-200"><div class="stat-label">Net book value</div><div class="stat-value text-mint-700">{{ inr($asset->current_book_value) }}</div>
            <div class="mt-1 text-xs text-slate-400">{{ $asset->last_depreciation_date ? 'As of '.fmt_date($asset->last_depreciation_date) : ($asset->opening_balance_date ? 'Opening balance as of '.fmt_date($asset->opening_balance_date) : 'No depreciation posted yet') }}</div></div>
        <div class="card p-5"><div class="stat-label">Useful life</div><div class="stat-value">{{ $asset->useful_life_label }}</div>
            <div class="mt-2 h-1.5 w-full rounded-full bg-slate-100"><div class="h-1.5 rounded-full bg-brand-400" style="width: {{ $asset->life_consumed_percent }}%"></div></div>
            <div class="mt-1 text-xs text-slate-400">{{ $asset->remaining_life_months !== null ? $asset->remaining_life_months.' months remaining · ends '.fmt_date($asset->end_of_life_date) : '—' }}</div></div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="card">
                <div class="card-header"><div class="card-title">Asset details</div></div>
                <dl class="card-body grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                    {!! $row('Category', $asset->category?->name) !!}
                    {!! $row('Manufacturer / make', $asset->manufacturer) !!}
                    {!! $row('Model', $asset->model) !!}
                    {!! $row('Serial number', $asset->serial_number) !!}
                    {!! $row('Asset tag', $asset->asset_tag) !!}
                    {!! $row('Quantity', $asset->quantity.' '.$asset->unit_of_measure) !!}
                    {!! $row('Parent asset', $asset->parentAsset ? $asset->parentAsset->asset_code.' – '.$asset->parentAsset->name : null) !!}
                    @if ($asset->description)<div class="sm:col-span-2 lg:col-span-3"><dt class="dl-label">Description</dt><dd class="dl-value whitespace-pre-line">{{ $asset->description }}</dd></div>@endif
                </dl>
            </div>

            <div class="card">
                <div class="card-header"><div class="card-title">Location & ownership</div></div>
                <dl class="card-body grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
                    {!! $row('Location / Plant', $asset->location?->name) !!}
                    {!! $row('Department', $asset->department?->name) !!}
                    {!! $row('Cost center', $asset->costCenter ? $asset->costCenter->code.' – '.$asset->costCenter->name : null) !!}
                    {!! $row('Custodian', $asset->custodian) !!}
                </dl>
            </div>

            <div class="card">
                <div class="card-header"><div class="card-title">Purchase & depreciation</div></div>
                <dl class="card-body grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
                    {!! $row('Vendor', $asset->vendor?->name) !!}
                    {!! $row('PO number', $asset->purchase_order_number) !!}
                    {!! $row('Invoice', trim($asset->invoice_number.($asset->invoice_date ? ' · '.fmt_date($asset->invoice_date) : ''), ' ·')) !!}
                    {!! $row('Purchase date', fmt_date($asset->purchase_date)) !!}
                    {!! $row('Put to use', fmt_date($asset->capitalization_date)) !!}
                    {!! $row('Method', $asset->depreciationMethod?->label) !!}
                    {!! $row('Useful life', $asset->useful_life_label) !!}
                    {!! $row('Depreciable amount', inr($asset->depreciable_amount)) !!}
                    @if ($asset->wdv_rate !== null && $asset->depreciationMethod?->code === 'WDV'){!! $row('WDV rate', number_format((float) $asset->wdv_rate, 2).'% p.a.') !!}@endif
                    @if ($asset->total_units_capacity){!! $row('Units', number_format((float) $asset->units_consumed).' / '.number_format((float) $asset->total_units_capacity)) !!}@endif
                    {!! $row('Opening acc. dep.', $asset->opening_accumulated_depreciation > 0 ? inr($asset->opening_accumulated_depreciation).' as of '.fmt_date($asset->opening_balance_date) : null) !!}
                    {!! $row('Last depreciation', fmt_date($asset->last_depreciation_date)) !!}
                </dl>
            </div>

            <div class="card">
                <div class="card-header"><div class="card-title">Warranty & insurance</div></div>
                <dl class="card-body grid gap-x-6 gap-y-4 sm:grid-cols-3">
                    <div><dt class="dl-label">Warranty expiry</dt><dd class="dl-value">{{ fmt_date($asset->warranty_expiry_date) ?: '—' }}
                        @if ($asset->warranty_expiry_date)<span class="badge ml-1 {{ $asset->warranty_expiry_date->isPast() ? 'bg-slate-100 text-slate-500 ring-slate-200' : 'bg-emerald-50 text-emerald-700 ring-emerald-200' }}">{{ $asset->warranty_expiry_date->isPast() ? 'Expired' : 'In warranty' }}</span>@endif</dd></div>
                    {!! $row('Insurance policy', $asset->insurance_policy_number) !!}
                    {!! $row('Insurance expiry', fmt_date($asset->insurance_expiry_date)) !!}
                    @if ($asset->notes)<div class="sm:col-span-3"><dt class="dl-label">Notes</dt><dd class="dl-value whitespace-pre-line">{{ $asset->notes }}</dd></div>@endif
                </dl>
            </div>

            {{-- Attachments --}}
            <div class="card">
                <div class="card-header"><div class="card-title">Attachments <span class="ml-1 text-slate-400">{{ $asset->attachments->count() }}</span></div><x-icon name="paperclip" class="h-4 w-4 text-slate-400"/></div>
                <div class="card-body">
                    @if ($asset->attachments->count())
                        <ul class="divide-y divide-slate-100">
                            @foreach ($asset->attachments as $file)
                                <li class="flex items-center gap-3 py-2.5">
                                    @if ($file->is_image)
                                        <img src="{{ $file->url }}" alt="" class="h-10 w-10 rounded-lg object-cover ring-1 ring-slate-200">
                                    @else
                                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-500"><x-icon name="doc" class="h-5 w-5"/></span>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ $file->url }}" target="_blank" class="block truncate text-sm font-medium text-slate-800 hover:text-brand-700">{{ $file->title ?: $file->original_name }}</a>
                                        <div class="text-xs text-slate-400">{{ \App\Models\AssetAttachment::TYPES[$file->type] ?? $file->type }} · {{ $file->size_label }} · {{ $file->uploader?->name }} · {{ $file->created_at?->format('d M Y') }}</div>
                                    </div>
                                    <a href="{{ $file->url }}" download="{{ $file->original_name }}" class="btn-ghost btn-sm" title="Download"><x-icon name="download" class="h-4 w-4"/></a>
                                    @if ($canWrite)
                                        <form method="POST" action="{{ route('assets.attachments.destroy', [$asset, $file]) }}" onsubmit="return confirm('Remove this attachment?')">@csrf @method('DELETE')
                                            <button class="btn-ghost btn-sm text-rose-500 hover:bg-rose-50" title="Remove"><x-icon name="x" class="h-4 w-4"/></button>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-slate-400">No documents attached yet.</p>
                    @endif

                    @if ($canWrite && ! $asset->trashed())
                        <form method="POST" action="{{ route('assets.attachments.store', $asset) }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-end gap-3 rounded-xl border border-dashed border-slate-200 bg-slate-50/60 p-4">
                            @csrf
                            <div><label class="form-label text-xs">Type</label>
                                <select name="type" class="form-input py-1.5 text-sm">@foreach (\App\Models\AssetAttachment::TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                            <div class="flex-1 min-w-[10rem]"><label class="form-label text-xs">Title (optional)</label><input name="title" class="form-input py-1.5 text-sm"></div>
                            <div><label class="form-label text-xs">Files</label><input type="file" name="files[]" multiple required class="text-sm text-slate-500 file:mr-2 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-brand-700"></div>
                            <button class="btn-primary py-1.5"><x-icon name="paperclip" class="h-4 w-4"/> Upload</button>
                            @error('files')<p class="form-error w-full">{{ $message }}</p>@enderror
                            @error('files.*')<p class="form-error w-full">{{ $message }}</p>@enderror
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card">
                <div class="card-header"><div class="card-title">QR code</div><x-icon name="qr" class="h-4 w-4 text-slate-400"/></div>
                <div class="card-body text-center">
                    <div class="mx-auto w-44 [&>svg]:h-auto [&>svg]:w-full">{!! $qrSvg !!}</div>
                    <div class="mt-2 font-mono text-sm font-semibold text-slate-800">{{ $asset->asset_code }}</div>
                    <p class="mt-1 text-xs text-slate-400">Scan to open this asset (sign-in required).</p>
                    <div class="mt-4 flex justify-center gap-2">
                        <a href="{{ route('assets.qr', [$asset, 'format' => 'png']) }}" class="btn-secondary btn-sm"><x-icon name="download" class="h-3.5 w-3.5"/> PNG</a>
                        <a href="{{ route('assets.qr', $asset) }}" target="_blank" class="btn-secondary btn-sm">SVG</a>
                        <a href="{{ route('assets.label', $asset) }}" target="_blank" class="btn-secondary btn-sm"><x-icon name="printer" class="h-3.5 w-3.5"/> Label</a>
                    </div>
                </div>
            </div>

            @if ($asset->components->count())
                <div class="card">
                    <div class="card-header"><div class="card-title">Components</div></div>
                    <ul class="divide-y divide-slate-100">
                        @foreach ($asset->components as $c)
                            <li class="px-5 py-2.5 text-sm"><a href="{{ route('assets.show', $c) }}" class="font-mono text-xs text-brand-700">{{ $c->asset_code }}</a> <span class="text-slate-700">{{ $c->name }}</span></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card">
                <div class="card-header"><div class="card-title">Coming in Phase 2</div></div>
                <div class="card-body space-y-2 text-sm text-slate-500">
                    @foreach ([['calculator', 'Depreciation schedule & postings'], ['arrows', 'Transfers between plants / departments'], ['wrench', 'Preventive maintenance & work orders'], ['trash', 'Disposal with profit / loss']] as [$i, $l])
                        <div class="flex items-center gap-2"><x-icon :name="$i" class="h-4 w-4 text-slate-300"/>{{ $l }}</div>
                    @endforeach
                </div>
            </div>

            <div class="card">
                <div class="card-header"><div class="card-title">Change history</div><x-icon name="shield" class="h-4 w-4 text-slate-400"/></div>
                <div class="card-body">
                    @include('partials.audit-timeline', ['logs' => $history])
                    <div class="mt-4 border-t border-slate-100 pt-3 text-xs text-slate-400">
                        Created {{ $asset->created_at?->format('d M Y, h:i A') }}{{ $asset->creator ? ' by '.$asset->creator->name : '' }}<br>
                        Updated {{ $asset->updated_at?->format('d M Y, h:i A') }}{{ $asset->updater ? ' by '.$asset->updater->name : '' }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
