@php
    $colors = ['created' => 'mint', 'updated' => 'brand', 'deactivated' => 'rose', 'deleted' => 'rose', 'restored' => 'amber'];
@endphp
@forelse ($logs as $log)
    <div class="relative pb-5 pl-6 last:pb-0">
        @unless ($loop->last)<span class="absolute left-[7px] top-4 h-full w-px bg-slate-200"></span>@endunless
        <span class="absolute left-0 top-1 h-3.5 w-3.5 rounded-full border-2 border-white bg-{{ $colors[$log->event] ?? 'slate' }}-500 ring-1 ring-{{ $colors[$log->event] ?? 'slate' }}-200"></span>
        <div class="text-sm"><span class="font-medium capitalize text-slate-800">{{ $log->event }}</span> <span class="text-slate-500">by {{ $log->user_name ?? 'system' }}</span></div>
        <div class="text-[11px] text-slate-400">{{ $log->created_at?->format('d M Y, h:i A') }} · {{ $log->ip_address ?? 'console' }}</div>
        @if ($log->event === 'updated' && $log->new_values)
            <div class="mt-1.5 space-y-0.5 rounded-lg bg-slate-50 p-2 text-[11px]">
                @foreach ($log->new_values as $k => $v)
                    <div><span class="font-medium text-slate-600">{{ \Illuminate\Support\Str::headline($k) }}:</span>
                        <span class="text-rose-500 line-through">{{ \Illuminate\Support\Str::limit(is_array($log->old_values[$k] ?? null) ? json_encode($log->old_values[$k]) : ($log->old_values[$k] ?? '∅'), 40) }}</span>
                        → <span class="text-mint-700">{{ \Illuminate\Support\Str::limit(is_array($v) ? json_encode($v) : ($v ?? '∅'), 40) }}</span></div>
                @endforeach
            </div>
        @endif
    </div>
@empty
    <p class="text-sm text-slate-400">No changes recorded yet.</p>
@endforelse
