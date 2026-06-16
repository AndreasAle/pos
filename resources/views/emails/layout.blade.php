<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
  body { margin:0; padding:0; background:#f9fafb; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#1f2937; }
  .wrapper { max-width:600px; margin:30px auto; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 1px 6px rgba(0,0,0,0.08); }
  .header  { background:#059669; padding:24px 28px; }
  .header h1 { color:#fff; margin:0; font-size:20px; }
  .header p  { color:#a7f3d0; margin:4px 0 0; font-size:13px; }
  .body    { padding:28px; }
  .card    { background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:16px; margin-bottom:16px; }
  .stat    { display:inline-block; text-align:center; padding:12px 20px; background:#ecfdf5; border-radius:8px; margin:4px; }
  .stat .num  { font-size:22px; font-weight:700; color:#059669; }
  .stat .lbl  { font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:0.05em; }
  .badge   { display:inline-block; padding:2px 10px; border-radius:99px; font-size:12px; font-weight:600; }
  .badge-red   { background:#fee2e2; color:#b91c1c; }
  .badge-green { background:#d1fae5; color:#065f46; }
  .badge-orange{ background:#ffedd5; color:#c2410c; }
  table.data { width:100%; border-collapse:collapse; font-size:13px; }
  table.data th { background:#f3f4f6; padding:8px 12px; text-align:left; font-size:11px; text-transform:uppercase; color:#6b7280; }
  table.data td { padding:8px 12px; border-bottom:1px solid #f3f4f6; }
  .footer  { background:#f3f4f6; padding:16px 28px; text-align:center; color:#9ca3af; font-size:12px; border-top:1px solid #e5e7eb; }
  .btn     { display:inline-block; background:#059669; color:#fff; padding:10px 24px; border-radius:8px; text-decoration:none; font-weight:600; font-size:13px; margin-top:12px; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1>🏪 {{ $business->name ?? 'FNB POS System' }}</h1>
    <p>{{ now()->isoFormat('dddd, D MMMM Y') }}</p>
  </div>
  <div class="body">
    @yield('content')
  </div>
  <div class="footer">
    Email ini dikirim otomatis oleh FNB POS System.<br>
    Jangan balas email ini.
  </div>
</div>
</body>
</html>
