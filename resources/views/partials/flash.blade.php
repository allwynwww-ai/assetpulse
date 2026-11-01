@foreach (['success' => ['emerald', 'check-circle'], 'warning' => ['amber', 'alert'], 'error' => ['rose', 'alert'], 'status' => ['brand', 'info']] as $key => [$color, $icon])
    @if (session($key) && ! ($key === 'status' && in_array(session('status'), ['profile-updated', 'password-updated'])))
        <div x-data="{ show: true }" x-show="show" x-transition role="status"
             class="mb-5 flex items-start gap-3 rounded-2xl border border-line bg-card px-4 py-3 text-[13.5px] text-ink shadow-card">
            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-{{ $color }}-500/10 text-{{ $color }}-600 dark:text-{{ $color }}-300"><x-icon :name="$icon" class="h-4 w-4"/></span>
            <div class="flex-1 pt-0.5">{{ session($key) }}</div>
            <button type="button" @click="show = false" class="text-ink3 hover:text-ink" aria-label="Dismiss"><x-icon name="x" class="h-4 w-4"/></button>
        </div>
    @endif
@endforeach
@if ($errors->any())
    <div class="mb-5 flex items-start gap-3 rounded-2xl border border-rose-500/25 bg-rose-500/[.06] px-4 py-3 text-[13.5px] text-rose-700 dark:text-rose-300">
        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-rose-500/10"><x-icon name="alert" class="h-4 w-4"/></span>
        <div class="pt-0.5">
            <div class="font-semibold">Please fix the following:</div>
            <ul class="mt-1 list-disc space-y-0.5 pl-5 text-[12.5px]">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    </div>
@endif
