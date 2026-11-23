<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, follow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Platform') — Yoka</title>
    @include('layouts._fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @vite('resources/js/alpine.js')
    <link rel="icon" type="image/png" href="{{ asset('images/brand/yoka-icon.png') }}">
</head>
<body class="min-h-full bg-slate-100 text-slate-900 antialiased" x-data="{ menu: false }" @keydown.escape.window="menu = false">
    @php
        $platformAdmin = auth('platform')->user();
        $platformAiUnread = 0;
        if ($platformAdmin?->isSuperAdmin() && \Illuminate\Support\Facades\Schema::hasTable('platform_ai_messages')) {
            $platformAiUnread = \App\Models\PlatformAiMessage::where('role', 'assistant')->whereNull('read_at')->get()
                ->filter(fn ($message) => (bool) data_get($message->meta, 'proactive'))->count();
        }
    @endphp

    <header class="fixed inset-x-0 top-0 z-40 h-16 border-b border-emerald-950/30 bg-emerald-950 text-white">
        <div class="flex h-full items-center px-4 lg:px-7">
            <button type="button" class="mr-3 p-2 lg:hidden" @click="menu = !menu" aria-label="Buka menu">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <a href="{{ route('platform.dashboard') }}" class="flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center bg-white"><img src="{{ asset('images/brand/yoka-icon.png') }}" class="h-7 w-7" alt=""></span>
                <span>
                    <span class="block text-sm font-black tracking-wide">YOKA PLATFORM</span>
                    <span class="block text-[12px] uppercase tracking-[0.18em] text-emerald-200">Internal Operations</span>
                </span>
            </a>
            <div class="ml-auto flex items-center gap-4">
                <div class="hidden text-right sm:block">
                    <p class="text-sm font-semibold">{{ $platformAdmin->name }}</p>
                    <p class="text-[12px] uppercase tracking-wider text-emerald-200">{{ str_replace('_', ' ', $platformAdmin->role) }}</p>
                </div>
                <form method="POST" action="{{ route('platform.logout') }}">
                    @csrf
                    <button class="border border-white/25 px-3 py-2 text-xs font-bold hover:bg-white/10">Keluar</button>
                </form>
            </div>
        </div>
    </header>

    <div x-show="menu" x-cloak class="fixed inset-0 z-20 bg-black/40 lg:hidden" @click="menu = false"></div>
    <aside class="fixed bottom-0 left-0 top-16 z-30 flex w-[19rem] -translate-x-full flex-col border-r border-slate-200 bg-white transition-transform duration-200 lg:translate-x-0" :class="menu && 'translate-x-0'" aria-label="Navigasi platform">
        <div class="border-b border-slate-100 px-5 py-4">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700">Pusat Operasional</p>
            <p class="mt-1 text-xs leading-relaxed text-slate-500">Kelola bisnis, keuangan, dan tim Yoka dari satu tempat.</p>
        </div>

        @php
            $navBase = 'group flex items-center gap-3 rounded-xl px-3 py-2.5 transition';
            $navIdle = 'text-slate-600 hover:bg-slate-100 hover:text-slate-950';
            $navActive = 'bg-emerald-50 text-emerald-950 ring-1 ring-inset ring-emerald-200';
            $iconBase = 'flex h-9 w-9 shrink-0 items-center justify-center rounded-lg';
        @endphp

        <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4 text-sm" @click="menu = false">
            <div>
                <p class="mb-2 px-3 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Gambaran Umum</p>
                <div class="space-y-1">
                    <a href="{{ route('platform.dashboard') }}" class="{{ $navBase }} {{ request()->routeIs('platform.dashboard') ? $navActive : $navIdle }}">
                        <span class="{{ $iconBase }} {{ request()->routeIs('platform.dashboard') ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-500 group-hover:bg-white' }}"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 13h6V4H4v9Zm0 7h6v-3H4v3Zm10 0h6v-9h-6v9Zm0-13h6V4h-6v3Z"/></svg></span>
                        <span><span class="block font-bold">Beranda</span><span class="block text-[11px] font-normal text-slate-500">Kondisi Yoka hari ini</span></span>
                    </a>
                    <a href="{{ route('platform.businesses.index') }}" class="{{ $navBase }} {{ request()->routeIs('platform.businesses.*') ? $navActive : $navIdle }}">
                        <span class="{{ $iconBase }} {{ request()->routeIs('platform.businesses.*') ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-500 group-hover:bg-white' }}"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 10h18M5 10v9h14v-9M4 10l2-5h12l2 5M9 19v-5h6v5"/></svg></span>
                        <span><span class="block font-bold">Toko Pelanggan</span><span class="block text-[11px] font-normal text-slate-500">Cari dan bantu semua toko</span></span>
                    </a>
                </div>
            </div>

            <div>
                <p class="mb-2 px-3 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Uang &amp; Penggunaan</p>
                <div class="space-y-1">
                    @if($platformAdmin->canManageWithdrawals())
                        <a href="{{ route('platform.withdrawals.index') }}" class="{{ $navBase }} {{ request()->routeIs('platform.withdrawals.*') ? $navActive : $navIdle }}">
                            <span class="{{ $iconBase }} {{ request()->routeIs('platform.withdrawals.*') ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-500 group-hover:bg-white' }}"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16v11H4V7Zm0 4h16M16 15h1"/></svg></span>
                            <span><span class="block font-bold">Pencairan Dana</span><span class="block text-[11px] font-normal text-slate-500">Periksa permintaan penarikan</span></span>
                        </a>
                    @endif
                    @if($platformAdmin->canViewYokaFinance())
                        <a href="{{ route('platform.finance') }}" class="{{ $navBase }} {{ request()->routeIs('platform.finance*') ? $navActive : $navIdle }}">
                            <span class="{{ $iconBase }} {{ request()->routeIs('platform.finance*') ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-500 group-hover:bg-white' }}"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 19V9m5 10V5m6 14v-7m5 7V3"/></svg></span>
                            <span><span class="block font-bold">Keuangan Perusahaan</span><span class="block text-[11px] font-normal text-slate-500">Pendapatan, biaya, dan laporan</span></span>
                        </a>
                    @endif
                    @if($platformAdmin->canViewFinancials())
                        <a href="{{ route('platform.ai-credits') }}" class="{{ $navBase }} {{ request()->routeIs('platform.ai-credits*') ? $navActive : $navIdle }}">
                            <span class="{{ $iconBase }} {{ request()->routeIs('platform.ai-credits*') ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-500 group-hover:bg-white' }}"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m13 2-8 12h7l-1 8 8-12h-7l1-8Z"/></svg></span>
                            <span><span class="block font-bold">Kredit AI Pelanggan</span><span class="block text-[11px] font-normal text-slate-500">Saldo dan pemakaian tiap toko</span></span>
                        </a>
                    @endif
                </div>
            </div>

            <div>
                <p class="mb-2 px-3 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Tim &amp; Pekerjaan</p>
                <div class="space-y-1">
                    @if($platformAdmin->isSuperAdmin())
                        <a href="{{ route('platform.ai-team.index') }}" class="{{ $navBase }} {{ request()->routeIs('platform.ai-team.*') ? $navActive : $navIdle }}">
                            <span class="{{ $iconBase }} {{ request()->routeIs('platform.ai-team.*') ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-500 group-hover:bg-white' }}"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 9h.01M15 9h.01M8 13h8m-9 6h10a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3H7a3 3 0 0 0-3 3v9a3 3 0 0 0 3 3Z"/></svg></span>
                            <span class="min-w-0 flex-1"><span class="block font-bold">Ruang Kerja Tim AI</span><span class="block text-[11px] font-normal text-slate-500">Obrolan, tugas, dan persetujuan</span></span>
                            @if($platformAiUnread > 0)<span class="flex h-6 min-w-6 items-center justify-center rounded-full bg-emerald-600 px-1.5 text-[10px] font-black text-white">{{ $platformAiUnread > 99 ? '99+' : $platformAiUnread }}</span>@endif
                        </a>
                        <a href="{{ route('platform.prospects.index') }}" class="{{ $navBase }} {{ request()->routeIs('platform.prospects.*') ? $navActive : $navIdle }}">
                            <span class="{{ $iconBase }} {{ request()->routeIs('platform.prospects.*') ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-500 group-hover:bg-white' }}"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8-3v6m-3-3h6"/></svg></span>
                            <span><span class="block font-bold">Calon Pelanggan</span><span class="block text-[11px] font-normal text-slate-500">Daftar kontak untuk Adit</span></span>
                        </a>
                        <a href="{{ route('platform.team') }}" class="{{ $navBase }} {{ request()->routeIs('platform.team*') ? $navActive : $navIdle }}">
                            <span class="{{ $iconBase }} {{ request()->routeIs('platform.team*') ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-500 group-hover:bg-white' }}"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2m19 0v-2a4 4 0 0 0-3-3.87M8.5 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8-7.87a4 4 0 0 1 0 7.75"/></svg></span>
                            <span><span class="block font-bold">Anggota Tim Yoka</span><span class="block text-[11px] font-normal text-slate-500">Akun dan akses staf</span></span>
                        </a>
                    @endif
                    <a href="{{ route('platform.activity') }}" class="{{ $navBase }} {{ request()->routeIs('platform.activity') ? $navActive : $navIdle }}">
                        <span class="{{ $iconBase }} {{ request()->routeIs('platform.activity') ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-500 group-hover:bg-white' }}"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 2m6-2a9 9 0 1 1-3-6.7M21 3v6h-6"/></svg></span>
                        <span><span class="block font-bold">Riwayat Aktivitas</span><span class="block text-[11px] font-normal text-slate-500">Catatan perubahan dan akses</span></span>
                    </a>
                </div>
            </div>

            <div>
                <p class="mb-2 px-3 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Pengaturan</p>
                <div class="space-y-1">
                    <a href="{{ route('platform.account') }}" class="{{ $navBase }} {{ request()->routeIs('platform.account*') ? $navActive : $navIdle }}">
                        <span class="{{ $iconBase }} {{ request()->routeIs('platform.account*') ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-500 group-hover:bg-white' }}"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 21a8 8 0 0 0-16 0m8-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/></svg></span>
                        <span><span class="block font-bold">Profil &amp; Keamanan</span><span class="block text-[11px] font-normal text-slate-500">Akun, sandi, dan verifikasi</span></span>
                    </a>
                    @if($platformAdmin->isSuperAdmin())
                        <a href="{{ route('platform.system') }}" class="{{ $navBase }} {{ request()->routeIs('platform.system*') ? $navActive : $navIdle }}">
                            <span class="{{ $iconBase }} {{ request()->routeIs('platform.system*') ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-500 group-hover:bg-white' }}"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm7.4-3.5a7.4 7.4 0 0 0-.1-1l2-1.5-2-3.5-2.5 1a8.6 8.6 0 0 0-1.7-1L14.8 3h-4l-.4 3a8.6 8.6 0 0 0-1.7 1L6.2 6l-2 3.5 2 1.5a7.4 7.4 0 0 0 0 2l-2 1.5 2 3.5 2.5-1a8.6 8.6 0 0 0 1.7 1l.4 3h4l.4-3a8.6 8.6 0 0 0 1.7-1l2.5 1 2-3.5-2-1.5a7.4 7.4 0 0 0 .1-1Z"/></svg></span>
                            <span><span class="block font-bold">Sistem &amp; Integrasi</span><span class="block text-[11px] font-normal text-slate-500">Kesehatan layanan dan koneksi</span></span>
                        </a>
                    @endif
                </div>
            </div>
        </nav>

        <div class="border-t border-slate-100 p-3">
            <div class="rounded-xl bg-slate-50 px-3 py-2.5 text-[11px] leading-relaxed text-slate-500">
                Data pelanggan bersifat internal. Buka hanya saat diperlukan untuk membantu pelanggan.
            </div>
        </div>
    </aside>

    <main class="min-h-screen pt-16 lg:pl-[19rem]">
        <div class="p-4 sm:p-6 lg:p-8">
            @if(session('success'))
                <div class="mb-5 border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-5 border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
            @endif
            @yield('content')
        </div>
    </main>
</body>
</html>
