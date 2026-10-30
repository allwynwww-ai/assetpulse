@php
    $canWrite = auth()->user()->canManageAssets();
    $disposed = $asset->isDisposed();
    $disposal = $asset->disposals->firstWhere('status', 'completed');
    $s = $asset->status;
    $row = fn ($label, $value) => '<div><dt class="dl-label">'.e($label).'</dt><dd class="dl-value">'.($value === null || $value === '' ? '<span class="text-slate-300">—</span>' : e($value)).'</dd></div>';
@endphp
<x-app-layout :title="$asset->asset_code">
    <x-slot name="header">
        <x-page-header :title="$asset->name" icon="cube" :crumbs="['Assets' => route('assets.index'), $asset->asset_code => null]">
            <a href="{{ route('assets.label', $asset) }}" target="_blank" class="btn-secondary"><x-icon name="printer" class="h-4 w-4"/> Print label</a>
            @if ($canWrite && ! $asset->trashed() && ! $disposed)
                <a href="{{ route('work-orders.create', ['asset' => $asset->id]) }}" class="btn-secondary"><x-icon name="wrench" class="h-4 w-4"/> New work order</a>
                <a href="{{ route('transfers.create', ['asset' => $asset->id]) }}" class="btn-secondary"><x-icon name="arrows" class="h-4 w-4"/> Transfer</a>
                <a href="{{ route('disposals.create', ['asset' => $asset->id]) }}" class="btn-secondary text-rose-600"><x-icon name="trash" class="h-4 w-4"/> Dispose</a>
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

    @if ($disposed && $disposal)
        <div class="mb-5 flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
            <x-icon name="info" class="h-4 w-4 text-slate-400"/>
            <span>{{ \Illuminate\Support\Str::headline($disposal->disposal_type) }} on <b>{{ fmt_date($disposal->disposal_date) }}</b> ({{ $disposal->disposal_number }}) — NBV {{ inr($disposal->nbv_at_disposal) }}, proceeds {{ inr($disposal->sale_value) }},
                <b class="{{ $disposal->profit_loss < 0 ? 'text-rose-600' : 'text-emerald-700' }}">{{ $disposal->profit_loss < 0 ? 'loss' : 'profit' }} {{ inr(abs($disposal->profit_loss)) }}</b>. This record is read-only.</span>
            <a href="{{ route('disposals.show', $disposal) }}" class="ml-auto text-brand-700 hover:underline">View disposal →</a>
        </div>
    @endif

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

            {{-- Depreciation schedule --}}
            <div class="card" x-data="{ all: false }">
                <div class="card-header">
                    <div class="card-title">Depreciation schedule <span class="ml-1 text-slate-400">{{ $entries->count() }}</span></div>
                    <div class="flex items-center gap-3 text-xs">
                        <span class="text-slate-500">Charged to date {{ inr($entries->sum('depreciation_amount')) }}</span>
                        <a href="{{ route('reports.show', ['depreciation-detail', 'q' => $asset->asset_code, 'run' => '']) }}" class="text-brand-700 hover:underline">Full report →</a>
                    </div>
                </div>
                @if ($entries->isEmpty())
                    <p class="card-body text-sm text-slate-400">No depreciation has been posted for this asset yet.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="table text-sm">
                            <thead><tr><th>Period</th><th>Run</th><th class="text-right">Days</th><th class="text-right">Opening WDV</th><th class="text-right">Charge</th><th class="text-right">Acc. dep.</th><th class="text-right">Closing WDV</th></tr></thead>
                            <tbody>
                            @foreach ($entries->reverse()->values() as $i => $e)
                                <tr @if ($i >= 12) x-show="all" x-cloak @endif>
                                    <td class="whitespace-nowrap">{{ $e->period_start->format('d M') }} – {{ $e->period_end->format('d M Y') }}</td>
                                    <td class="whitespace-nowrap">@if ($e->run)<a href="{{ route('depreciation.show', $e->run) }}" class="font-mono text-xs text-brand-700">{{ $e->run->run_number }}</a> <x-pill :value="$e->run->run_type === 'disposal' ? 'disposal' : $e->run->status"/>@endif</td>
                                    <td class="text-right tabular-nums">{{ $e->days }}</td>
                                    <td class="text-right tabular-nums">{{ inr($e->opening_wdv, 2, false) }}</td>
                                    <td class="text-right tabular-nums font-medium">{{ inr($e->depreciation_amount, 2, false) }}</td>
                                    <td class="text-right tabular-nums">{{ inr($e->closing_accumulated_depreciation, 2, false) }}</td>
                                    <td class="text-right tabular-nums">{{ inr($e->closing_wdv, 2, false) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($entries->count() > 12)
                        <div class="border-t border-slate-100 px-5 py-2 text-center"><button type="button" @click="all = !all" class="text-xs font-medium text-brand-700" x-text="all ? 'Show latest 12 only' : 'Show all {{ $entries->count() }} periods'"></button></div>
                    @endif
                @endif
            </div>

            {{-- Transfers --}}
            <div class="card">
                <div class="card-header"><div class="card-title">Transfer history <span class="ml-1 text-slate-400">{{ $asset->transfers->count() }}</span></div>
                    @if ($canWrite && ! $disposed && ! $asset->trashed())<a href="{{ route('transfers.create', ['asset' => $asset->id]) }}" class="btn-ghost btn-sm"><x-icon name="arrows" class="h-3.5 w-3.5"/> Transfer</a>@endif</div>
                @if ($asset->transfers->isEmpty())
                    <p class="card-body text-sm text-slate-400">This asset has not been moved since it was put to use.</p>
                @else
                    <ol class="divide-y divide-slate-100">
                        @foreach ($asset->transfers as $t)
                            <li class="flex flex-wrap items-start gap-x-4 gap-y-1 px-5 py-3 text-sm">
                                <div class="w-24 shrink-0 text-xs text-slate-500">{{ fmt_date($t->transfer_date) }}<br><a href="{{ route('transfers.show', $t) }}" class="font-mono text-brand-700">{{ $t->transfer_number }}</a></div>
                                <div class="min-w-0 flex-1">
                                    @foreach ([['Location', $t->fromLocation?->name, $t->toLocation?->name], ['Department', $t->fromDepartment?->name, $t->toDepartment?->name], ['Cost center', $t->fromCostCenter?->code, $t->toCostCenter?->code], ['Custodian', $t->from_custodian, $t->to_custodian]] as [$lbl, $a, $b])
                                        @if ($a !== $b)<div><span class="text-slate-400">{{ $lbl }}:</span> {{ $a ?: '—' }} <span class="text-slate-400">→</span> <span class="font-medium text-slate-800">{{ $b ?: '—' }}</span></div>@endif
                                    @endforeach
                                    <div class="text-xs text-slate-500">{{ $t->reason }}@if ($t->affects_depreciation) · <span class="text-sky-700">depreciation split by cost center</span>@endif</div>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>

            {{-- Maintenance --}}
            <div class="card">
                <div class="card-header"><div class="card-title">Maintenance</div>
                    <div class="flex items-center gap-2 text-xs"><span class="text-slate-500">Spend to date {{ inr($maintenanceCost) }}</span>
                        @if ($canWrite && ! $disposed && ! $asset->trashed())<a href="{{ route('pm-schedules.create', ['asset' => $asset->id]) }}" class="btn-ghost btn-sm"><x-icon name="calendar" class="h-3.5 w-3.5"/> PM</a><a href="{{ route('work-orders.create', ['asset' => $asset->id]) }}" class="btn-ghost btn-sm"><x-icon name="wrench" class="h-3.5 w-3.5"/> Work order</a>@endif</div></div>
                @if ($asset->pmSchedules->isNotEmpty())
                    <div class="border-b border-slate-100 px-5 py-3">
                        <div class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">PM schedules</div>
                        <div class="space-y-2">
                            @foreach ($asset->pmSchedules as $pm)
                                @php $over = $pm->is_active && $pm->next_due_date && $pm->next_due_date->isPast() && ! $pm->next_due_date->isToday(); $soon = $pm->is_active && $pm->next_due_date && ! $over && $pm->next_due_date->lte(now()->addDays(30)); @endphp
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                                    <a href="{{ $canWrite ? route('pm-schedules.edit', $pm) : route('pm-schedules.index') }}" class="font-medium text-slate-800 hover:text-brand-700">{{ $pm->title }}</a>
                                    <span class="text-xs text-slate-400">every {{ $pm->frequency_interval }} {{ str_replace('_', ' ', $pm->frequency_unit) }}</span>
                                    <span class="ml-auto text-xs {{ $over ? 'font-semibold text-rose-600' : ($soon ? 'font-semibold text-amber-600' : 'text-slate-500') }}">
                                        {{ ! $pm->is_active ? 'Inactive' : ($pm->next_due_date ? ($over ? 'Overdue since ' : 'Due ').fmt_date($pm->next_due_date) : 'Not scheduled') }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
                @if ($asset->workOrders->isEmpty())
                    <p class="card-body text-sm text-slate-400">No work orders recorded.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="table text-sm">
                            <thead><tr><th>WO</th><th>Reported</th><th>Type</th><th>Title</th><th>Status</th><th class="text-right">Downtime</th><th class="text-right">Cost</th></tr></thead>
                            <tbody>
                            @foreach ($asset->workOrders as $wo)
                                <tr>
                                    <td class="whitespace-nowrap"><a href="{{ route('work-orders.show', $wo) }}" class="font-mono text-xs text-brand-700">{{ $wo->wo_number }}</a></td>
                                    <td class="whitespace-nowrap">{{ fmt_date($wo->reported_date) }}</td>
                                    <td><x-pill :value="$wo->type"/></td>
                                    <td class="max-w-xs truncate" title="{{ $wo->title }}">{{ $wo->title }}</td>
                                    <td><x-pill :value="$wo->status"/></td>
                                    <td class="text-right tabular-nums">{{ $wo->downtime_hours > 0 ? rtrim(rtrim(number_format($wo->downtime_hours, 1), '0'), '.').' h' : '—' }}</td>
                                    <td class="text-right tabular-nums">{{ inr($wo->total_cost, 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
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
