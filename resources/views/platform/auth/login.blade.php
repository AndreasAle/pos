<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Tim Yoka</title>
    @include('layouts._fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-emerald-950 text-slate-900">
    <main class="grid min-h-screen lg:grid-cols-2">
        <section class="hidden border-r border-white/10 p-14 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="flex items-center gap-3"><img src="{{ asset('images/brand/yoka-logo-white.png') }}" class="h-10 w-auto" alt="Yoka"><span class="border-l border-white/30 pl-3 text-sm font-bold uppercase tracking-widest">Platform</span></div>
            <div class="max-w-lg">
                <p class="mb-4 text-xs font-bold uppercase tracking-[0.24em] text-emerald-300">Internal access only</p>
                <h1 class="text-5xl font-black leading-tight">Satu kendali untuk seluruh ekosistem Yoka.</h1>
                <p class="mt-6 text-base leading-relaxed text-emerald-100">Pantau tenant, langganan, trial, bantuan teknis, dan transaksi sensitif dari ruang kerja yang terpisah.</p>
            </div>
            <p class="text-xs text-emerald-300">Akses dicatat dan dilindungi verifikasi dua langkah.</p>
        </section>

        <section class="flex items-center justify-center bg-slate-50 px-5 py-12">
            <div class="w-full max-w-md border border-slate-200 bg-white p-7 shadow-xl sm:p-10">
                <div class="mb-8 lg:hidden"><span class="text-xl font-black text-emerald-800">YOKA PLATFORM</span></div>
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-700">Akun Tim Yoka</p>
                <h2 class="mt-2 text-3xl font-black">Masuk ke backoffice</h2>
                <p class="mt-2 text-sm text-slate-500">Setelah password benar, kode keamanan akan dikirim ke email Anda.</p>

                @if($errors->any())<div class="mt-5 border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif

                <form method="POST" action="{{ route('platform.login.store') }}" class="mt-7 space-y-5">
                    @csrf
                    <div><label class="mb-1.5 block text-sm font-bold">Email platform</label><input type="email" name="email" value="{{ old('email') }}" required autofocus class="w-full border border-slate-300 px-4 py-3 focus:border-emerald-600 focus:outline-none" placeholder="nama@yokapos.com"></div>
                    <div><label class="mb-1.5 block text-sm font-bold">Password</label><input type="password" name="password" required class="w-full border border-slate-300 px-4 py-3 focus:border-emerald-600 focus:outline-none"></div>
                    <button class="w-full bg-emerald-700 px-4 py-3.5 text-sm font-black text-white hover:bg-emerald-800">Lanjutkan dengan 2FA</button>
                </form>
                <a href="{{ route('login') }}" class="mt-6 block text-center text-xs font-semibold text-slate-500 hover:text-emerald-700">Bukan tim Yoka? Masuk ke akun toko</a>
            </div>
        </section>
    </main>
</body>
</html>
