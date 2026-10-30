@php
    $readonly = $mode === 'show';
    $backUrl = $backUrl ?? route($routeName.'.index');
    $action = $action ?? ($mode === 'create' ? route($routeName.'.store') : route($routeName.'.update', $record->getKey()));
    $heading = match ($mode) { 'create' => 'New '.$singular, 'edit' => 'Edit '.$singular, default => $singular.' details' };
    // Split schema into sections
    $sections = [];
    $current = ['label' => null, 'help' => null, 'fields' => []];
    foreach ($fields as $f) {
        if (($f['type'] ?? null) === 'section') {
            if ($current['fields']) { $sections[] = $current; }
            $current = ['label' => $f['label'], 'help' => $f['help'] ?? null, 'fields' => []];
        } else {
            $current['fields'][] = $f;
        }
    }
    if ($current['fields']) { $sections[] = $current; }
@endphp
<x-app-layout :title="$heading">
    <x-slot name="header">
        <x-page-header :title="$heading" :subtitle="$record->exists ? $record->auditLabel() : $description" :icon="$icon"
                       :crumbs="['Admin' => null, $title => $backUrl, ($mode === 'create' ? 'New' : $record->auditLabel()) => null]">
            @if ($readonly && $canWrite && ! $record->trashed())
                <a href="{{ route($routeName.'.edit', $record->getKey()) }}" class="btn-primary"><x-icon name="pencil" class="h-4 w-4"/> Edit</a>
            @endif
            @if ($record->exists && method_exists($record, 'trashed') && $record->trashed())
                <span class="badge bg-slate-100 text-slate-600 ring-slate-200">Inactive</span>
            @endif
        </x-page-header>
    </x-slot>

    <form method="POST" action="{{ $action }}" novalidate>
        @csrf
        @if ($mode === 'edit') @method('PUT') @endif

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 {{ $readonly && isset($history) ? 'xl:col-span-2' : 'xl:col-span-3' }}">
                @foreach ($sections as $section)
                    <div class="card">
                        @if ($section['label'])
                            <div class="card-header">
                                <div>
                                    <div class="card-title">{{ $section['label'] }}</div>
                                    @if ($section['help'])<div class="text-xs text-slate-400">{{ $section['help'] }}</div>@endif
                                </div>
                            </div>
                        @endif
                        <div class="card-body grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                            @foreach ($section['fields'] as $field)
                                @include('admin.master._field', ['field' => $field, 'record' => $record, 'readonly' => $readonly])
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <div class="flex items-center justify-end gap-2">
                    <a href="{{ $backUrl }}" class="btn-secondary">{{ $readonly ? 'Back to list' : 'Cancel' }}</a>
                    @unless ($readonly)
                        <button type="submit" class="btn-primary"><x-icon name="check" class="h-4 w-4"/> {{ $mode === 'create' ? 'Create '.$singular : 'Save changes' }}</button>
                    @endunless
                </div>
            </div>

            @if ($readonly && isset($history))
                <div class="card self-start">
                    <div class="card-header"><div class="card-title">Change history</div><x-icon name="shield" class="h-4 w-4 text-slate-400"/></div>
                    <div class="card-body">
                        @include('partials.audit-timeline', ['logs' => $history])
                    </div>
                </div>
            @endif
        </div>
    </form>
</x-app-layout>
