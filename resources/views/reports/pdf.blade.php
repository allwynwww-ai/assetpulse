@php
    use App\Services\Reports\ReportBuilder as RB;
    $cols = $r['columns'];
    $num = fn ($c) => in_array($c['type'], ['money', 'pl', 'number', 'int', 'percent']);
    $many = count($cols) > 11;
@endphp
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>{{ $r['title'] }}</title>
<style>
    @page { margin: 18mm 10mm 14mm 10mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: {{ $many ? '6.6pt' : '7.6pt' }}; color: #1e293b; }
    .hdr { border-bottom: 2px solid #2563eb; padding-bottom: 6px; margin-bottom: 8px; }
    .co { font-size: 13pt; font-weight: bold; color: #0f172a; }
    .addr { font-size: 7pt; color: #64748b; }
    .title { font-size: 10.5pt; font-weight: bold; color: #1d4ed8; margin-top: 4px; }
    .meta { font-size: 6.8pt; color: #64748b; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #2563eb; color: #fff; padding: 4px 3px; text-align: left; font-weight: bold; font-size: {{ $many ? '6.2pt' : '7pt' }}; }
    th.g { background: #e2e8f0; color: #1e293b; text-align: center; }
    td { padding: 3px; border-bottom: 0.5px solid #e2e8f0; vertical-align: top; }
    tr:nth-child(even) td { background: #f8fafc; }
    .r { text-align: right; white-space: nowrap; }
    .neg { color: #be123c; }
    tfoot td { font-weight: bold; background: #f1f5f9; border-top: 1px solid #94a3b8; border-bottom: 2px double #94a3b8; }
    thead { display: table-header-group; }
    .notes { margin-top: 8px; font-size: 6.6pt; color: #64748b; }
    .mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 95%; }
</style></head>
<body>
<div class="hdr">
    <div class="co">{{ $company?->legal_name ?: $company?->name }}</div>
    <div class="addr">{{ collect([$company?->address_line1, $company?->address_line2, $company?->city, $company?->state, $company?->pincode])->filter()->implode(', ') }}@if ($company?->gstin) · GSTIN {{ $company->gstin }}@endif @if ($company?->cin) · CIN {{ $company->cin }}@endif</div>
    <div class="title">{{ $r['title'] }}</div>
    <div class="meta">
        @foreach ($r['applied'] ?? [] as $k => $v){{ $k }}: <b>{{ $v }}</b> &nbsp;·&nbsp; @endforeach
        Amounts in ₹ · Generated {{ now()->format('d M Y, h:i A') }} IST by {{ auth()->user()?->name ?? 'system' }}
    </div>
</div>
<table>
    <thead>
        @if (! empty($r['groupHeaders']))<tr>@foreach ($r['groupHeaders'] as [$l, $span])<th class="g" colspan="{{ $span }}">{{ $l }}</th>@endforeach</tr>@endif
        <tr>@foreach ($cols as $c)<th class="{{ $num($c) ? 'r' : '' }}">{{ $c['label'] }}</th>@endforeach</tr>
    </thead>
    <tbody>
    @forelse ($r['rows'] as $row)
        <tr>@foreach ($cols as $c)@php $v = $row[$c['key']] ?? null; @endphp<td class="{{ $num($c) ? 'r' : '' }} {{ $c['type'] === 'code' ? 'mono' : '' }} {{ $c['type'] === 'pl' && $v < 0 ? 'neg' : '' }}">{{ RB::format($v, $c['type'], true) }}</td>@endforeach</tr>
    @empty
        <tr><td colspan="{{ max(1, count($cols)) }}" style="text-align:center;padding:20px;color:#94a3b8">No data for the selected filters.</td></tr>
    @endforelse
    </tbody>
    @if (! empty($r['totals']) && count($r['rows']))
        <tfoot><tr>@foreach ($cols as $i => $c)<td class="{{ $num($c) ? 'r' : '' }}">{{ $i === 0 ? 'Total' : (array_key_exists($c['key'], $r['totals']) ? RB::format($r['totals'][$c['key']], $c['type']) : '') }}</td>@endforeach</tr></tfoot>
    @endif
</table>
@if (! empty($r['notes']))<div class="notes">@foreach ($r['notes'] as $n)<div>Note: {{ $n }}</div>@endforeach</div>@endif
<script type="text/php">
    if (isset($pdf)) {
        $font = $fontMetrics->getFont('DejaVu Sans', 'normal');
        $pdf->page_text(760, 570, "Page {PAGE_NUM} of {PAGE_COUNT}", $font, 6.5, [0.4, 0.45, 0.55]);
        $pdf->page_text(28, 570, 'AssetPulse · {!! str_replace(['\\', "'"], ['\\\\', "\\'"], $r['title']) !!}', $font, 6.5, [0.4, 0.45, 0.55]);
    }
</script>
</body></html>
