{{-- Self-contained (no Vite / DB dependency) so it renders even when the app is unhealthy. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') · @yield('title') · AssetPulse</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;background:linear-gradient(135deg,#f8fafc 0%,#eff6ff 55%,#ecfdf5 100%);color:#0f172a;padding:24px}
        .card{max-width:520px;width:100%;background:#fff;border:1px solid #e2e8f0;border-radius:20px;box-shadow:0 10px 40px -12px rgba(15,23,42,.15);padding:40px 36px;text-align:center}
        .logo{display:inline-flex;align-items:center;gap:8px;font-weight:700;font-size:15px;color:#0f172a;text-decoration:none;margin-bottom:28px}
        .logo span.b{display:inline-flex;width:32px;height:32px;border-radius:10px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);align-items:center;justify-content:center}
        .logo em{color:#2563eb;font-style:normal}
        .code{font-size:64px;font-weight:800;letter-spacing:-2px;line-height:1;background:linear-gradient(135deg,#2563eb,#10b981);-webkit-background-clip:text;background-clip:text;color:transparent}
        h1{font-size:20px;margin:14px 0 8px}p{color:#64748b;font-size:14px;line-height:1.6;margin:0 0 24px}
        .actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
        a.btn{display:inline-block;padding:9px 18px;border-radius:10px;font-size:14px;font-weight:600;text-decoration:none}
        .primary{background:#2563eb;color:#fff}.primary:hover{background:#1d4ed8}.ghost{color:#334155;border:1px solid #e2e8f0}.ghost:hover{background:#f1f5f9}
        .hint{margin-top:22px;font-size:12px;color:#94a3b8}
    </style>
</head>
<body>
<div class="card">
    <a href="{{ url('/') }}" class="logo"><span class="b"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h4l3-8 4 16 3-8h4"/></svg></span>Asset<em>Pulse</em></a>
    <div class="code">@yield('code')</div>
    <h1>@yield('title')</h1>
    <p>@yield('message')</p>
    <div class="actions">
        <a href="javascript:history.back()" class="btn ghost">← Go back</a>
        <a href="{{ url('/dashboard') }}" class="btn primary">Dashboard</a>
    </div>
    <div class="hint">@yield('hint')</div>
</div>
</body>
</html>
