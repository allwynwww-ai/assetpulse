@props(['values' => [], 'w' => 84, 'h' => 34, 'color' => '#4840D6'])
@php
    $vals = array_values(array_map('floatval', is_array($values) ? $values : collect($values)->all()));
    $n = count($vals);
    $uid = 'sp'.substr(md5(json_encode($vals).$color), 0, 8);
@endphp
@if ($n >= 2 && max($vals) > 0)
    @php
        $mn = min($vals); $mx = max($vals); $rng = ($mx - $mn) ?: 1;
        $pts = []; foreach ($vals as $i => $v) { $pts[] = [$i * $w / ($n - 1), $h - 3 - ($v - $mn) / $rng * ($h - 6)]; }
        $d = 'M'.implode(' L', array_map(fn ($p) => sprintf('%.1f,%.1f', $p[0], $p[1]), $pts));
        [$lx, $ly] = end($pts);
    @endphp
    <svg width="{{ $w }}" height="{{ $h }}" viewBox="0 0 {{ $w }} {{ $h }}" class="overflow-visible" aria-hidden="true">
        <defs><linearGradient id="{{ $uid }}" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="{{ $color }}" stop-opacity=".22"/><stop offset="1" stop-color="{{ $color }}" stop-opacity="0"/></linearGradient></defs>
        <path d="{{ $d }} L{{ $w }},{{ $h }} L0,{{ $h }} Z" fill="url(#{{ $uid }})"/>
        <path d="{{ $d }}" fill="none" stroke="{{ $color }}" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
        <circle cx="{{ sprintf('%.1f', $lx) }}" cy="{{ sprintf('%.1f', $ly) }}" r="2.5" fill="{{ $color }}" style="stroke: rgb(var(--card))" stroke-width="1.5"/>
    </svg>
@endif
