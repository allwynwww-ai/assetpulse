@foreach (['success' => ['mint', 'check'], 'warning' => ['amber', 'info'], 'error' => ['rose', 'x'], 'status' => ['brand', 'info']] as $key => [$color, $icon])
    @if (session($key) && ! ($key === 'status' && in_array(session('status'), ['profile-updated', 'password-updated'])))
        <div x-data="{ show: true }" x-show="show" x-transition
             class="mb-5 flex items-start gap-3 rounded-xl border border-{{ $color }}-200 bg-{{ $color }}-50 px-4 py-3 text-sm text-{{ $color }}-700">
            <x-icon :name="$icon" class="mt-0.5 h-4 w-4 shrink-0"/>
            <div class="flex-1">{{ session($key) }}</div>
            <button @click="show = false" class="opacity-60 hover:opacity-100"><x-icon name="x" class="h-4 w-4"/></button>
        </div>
    @endif
@endforeach
@if ($errors->any())
    <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        <div class="font-semibold">Please fix the following:</div>
        <ul class="mt-1 list-disc pl-5 text-xs space-y-0.5">
            @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
@endif
