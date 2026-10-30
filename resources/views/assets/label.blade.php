<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Label · {{ $asset->asset_code }}</title>
    <style>
        @page { size: 90mm 50mm; margin: 0; }
        body { margin: 0; font-family: Inter, ui-sans-serif, system-ui, sans-serif; background: #f1f5f9; }
        .label { width: 90mm; height: 50mm; box-sizing: border-box; padding: 4mm; display: flex; gap: 3mm; background: #fff; margin: 10mm auto; border: 1px dashed #cbd5e1; }
        .qr svg { width: 40mm; height: 40mm; }
        .meta { display: flex; flex-direction: column; justify-content: center; min-width: 0; }
        .co { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: .05em; }
        .code { font: 700 14pt ui-monospace, monospace; color: #0f172a; margin: 1.5mm 0; }
        .name { font-size: 8pt; color: #334155; line-height: 1.25; max-height: 3em; overflow: hidden; }
        .loc { font-size: 7pt; color: #64748b; margin-top: 1.5mm; }
        .hint { text-align: center; color: #64748b; font-size: 12px; }
        @media print { body { background: #fff; } .label { margin: 0; border: 0; } .hint { display: none; } }
    </style>
</head>
<body onload="window.print()">
    <div class="label">
        <div class="qr">{!! $qrSvg !!}</div>
        <div class="meta">
            <div class="co">{{ $currentCompany?->name ?? config('app.name') }}</div>
            <div class="code">{{ $asset->asset_code }}</div>
            <div class="name">{{ $asset->name }}</div>
            <div class="loc">{{ $asset->location?->name }} · {{ $asset->category?->name }}</div>
        </div>
    </div>
    <p class="hint">90 × 50 mm asset label — use your browser's print dialog.</p>
</body>
</html>
