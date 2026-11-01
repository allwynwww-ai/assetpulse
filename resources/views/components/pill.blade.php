@props(['value'])
@php
    $map = [
        'posted' => 'sky', 'finalized' => 'emerald', 'reversed' => 'slate', 'draft' => 'amber',
        'open' => 'sky', 'in_progress' => 'amber', 'on_hold' => 'slate', 'completed' => 'emerald', 'cancelled' => 'slate',
        'low' => 'slate', 'medium' => 'sky', 'high' => 'amber', 'critical' => 'rose',
        'preventive' => 'emerald', 'breakdown' => 'rose', 'corrective' => 'amber', 'inspection' => 'sky',
        'sale' => 'sky', 'scrap' => 'slate', 'write_off' => 'amber', 'donation' => 'violet', 'theft_loss' => 'rose', 'insurance_claim' => 'violet',
        'periodic' => 'brand', 'disposal' => 'rose', 'locked' => 'emerald', 'unlocked' => 'amber', 'overdue' => 'rose', 'due' => 'amber', 'ok' => 'emerald',
    ];
    $c = $map[$value] ?? 'slate';
    $label = \Illuminate\Support\Str::headline($value === 'finalized' ? 'finalised' : $value);
@endphp
<span {{ $attributes->merge(['class' => "badge bg-{$c}-500/10 text-{$c}-700 ring-{$c}-600/20 dark:text-{$c}-300"]) }}><span class="h-1.5 w-1.5 rounded-full bg-{{ $c }}-500"></span>{{ $slot->isEmpty() ? $label : $slot }}</span>
