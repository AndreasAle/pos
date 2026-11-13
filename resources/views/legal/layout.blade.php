<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — {{ config('app.name') }}</title>
    <meta name="robots" content="index,follow">
    <link rel="icon" type="image/png" href="{{ asset('images/brand/yoka-icon.png') }}">
    {{-- Self-contained on purpose: these pages are opened inside the Android app
         and by Google Play review, and must not depend on the Vite build. --}}
    <style>
        :root { --green:#16A34A; --navy:#0B1B3F; --ink:#0F172A; --slate:#475569; --line:#E2E8F0; --bg:#F1F5F9; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color:var(--ink); background:var(--bg); line-height:1.65; }
        header { background:var(--navy); padding:16px 20px; }
        header a { display:inline-flex; align-items:center; gap:10px; text-decoration:none; }
        header img { height:28px; width:auto; }
        main { max-width:760px; margin:28px auto 56px; padding:0 16px; }
        article { background:#fff; border:1px solid var(--line); border-radius:12px; padding:28px 28px 20px; }
        h1 { font-size:26px; margin:0 0 4px; color:var(--navy); }
        .meta { color:var(--slate); font-size:13px; margin:0 0 20px; }
        h2 { font-size:17px; margin:26px 0 6px; color:var(--navy); }
        p, li { font-size:15px; color:var(--slate); }
        ul, ol { padding-left:22px; }
        a { color:var(--green); font-weight:600; }
        .note { background:#F0FDF4; border:1px solid #BBF7D0; border-radius:10px; padding:12px 14px; color:#166534; font-size:14px; }
        footer { text-align:center; color:var(--slate); font-size:12px; padding:0 16px 32px; }
        footer a { margin:0 8px; }
        @media (max-width:520px) { article { padding:20px 16px 12px; } h1 { font-size:22px; } }
    </style>
</head>
<body>
    <header>
        <a href="{{ route('landing') }}" aria-label="Yoka POS">
            <img src="{{ asset('images/brand/yoka-logo-white.png') }}" alt="Yoka POS">
        </a>
    </header>

    <main>
        <article>
            <h1>@yield('title')</h1>
            <p class="meta">Terakhir diperbarui: @yield('updated')</p>
            @yield('content')
        </article>
    </main>

    <footer>
        <a href="{{ route('legal.terms') }}">Syarat Layanan</a>
        <a href="{{ route('legal.privacy') }}">Kebijakan Privasi</a>
        <a href="{{ route('legal.delete') }}">Hapus Akun</a>
    </footer>
</body>
</html>
