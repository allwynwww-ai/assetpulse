@props(['values' => [], 'w' => 84, 'h' => 34, 'color' => '#4840D6'])
@php $vals = array_values(array_map('floatval', is_array($values) ? $values : collect($values)->all())); $n = count($vals); $mx = $n ? max($vals) : 0; @endphp
@if ($n >= 1 && $mx > 0)
    @php $gap = $w / $n; $bw = $gap * 0.62; @endphp
    <svg width="{{ $w }}" height="{{ $h }}" viewBox="0 0 {{ $w }} {{ $h }}" aria-hidden="true">
        @foreach ($vals as $i => $v)
            @php $bh = max(2, $v / $mx * ($h - 2)); @endphp
            <rect x="{{ sprintf('%.1f', $i * $gap + ($gap - $bw) / 2) }}" y="{{ sprintf('%.1f', $h - $bh) }}" width="{{ sprintf('%.1f', $bw) }}" height="{{ sprintf('%.1f', $bh) }}" rx="1.5" fill="{{ $color }}" fill-opacity="{{ $i === $n - 1 ? '1' : '.28' }}"/>
        @endforeach
    </svg>
@endif
