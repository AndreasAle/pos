<!DOCTYPE html>
<html lang="id" class="h-full"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Verifikasi Tim Yoka</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="flex min-h-full items-center justify-center bg-emerald-950 px-5 py-12">
    <main class="w-full max-w-md border border-slate-200 bg-white p-8 shadow-2xl sm:p-10">
        <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-700">Verifikasi dua langkah</p>
        <h1 class="mt-2 text-3xl font-black text-slate-900">Periksa email Anda</h1>
        <p class="mt-3 text-sm leading-relaxed text-slate-500">Kode 6 digit telah dikirim ke <strong class="text-slate-800">{{ $admin->email }}</strong>. Kode berlaku 10 menit.</p>
        @if(session('success'))<div class="mt-5 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="mt-5 border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('platform.two-factor.verify') }}" class="mt-7">
            @csrf
            <label class="mb-2 block text-sm font-bold text-slate-800">Kode keamanan</label>
            <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus autocomplete="one-time-code" class="w-full border border-slate-300 px-4 py-4 text-center font-mono text-3xl font-black tracking-[0.35em] focus:border-emerald-600 focus:outline-none" placeholder="000000">
            <button class="mt-5 w-full bg-emerald-700 px-4 py-3.5 text-sm font-black text-white hover:bg-emerald-800">Verifikasi &amp; Masuk</button>
        </form>
        <div class="mt-5 flex items-center justify-between text-xs">
            <form method="POST" action="{{ route('platform.two-factor.resend') }}">@csrf<button class="font-bold text-emerald-700 hover:underline">Kirim ulang kode</button></form>
            <form method="POST" action="{{ route('platform.logout') }}">@csrf<button class="text-slate-500 hover:text-slate-800">Batalkan</button></form>
        </div>
    </main>
</body></html>
