@extends('layouts.app')
@section('title','Bot Telegram')
@section('page-title','Bot Telegram')

@section('content')
@php
    $linked = !empty($tg['chat_id']);
    $prefs  = [
        'daily'  => ['Laporan harian',  'Ringkasan omzet, transaksi, dan produk terlaris tiap pukul 21.00.', $business->telegramWants('daily')],
        'alerts' => ['Peringatan',      'Stok menipis, shift lupa ditutup, serta order yang di-void atau di-refund.', $business->telegramWants('alerts')],
        'orders' => ['Order baru',      'Kabar tiap transaksi yang masuk. Bisa ramai di toko yang sibuk.', $business->telegramWants('orders')],
    ];
@endphp

<div class="page text-[#0B1B3F] pt-2 max-w-4xl">

    <div class="fade-up flex flex-wrap items-end justify-between gap-2 mb-4" style="--i:0">
        <p class="text-sm text-[#64748B]">Terima laporan dan peringatan toko langsung di Telegram, dan cek angka kapan saja lewat perintah singkat.</p>
        <span class="inline-flex items-center gap-1.5 h-8 px-3 rounded-full text-sm font-semibold {{ $linked ? 'bg-[#ECFDF3] text-[#15803D]' : 'bg-slate-100 text-[#64748B]' }}">
            <span class="w-1.5 h-1.5 rounded-full {{ $linked ? 'bg-[#16A34A]' : 'bg-slate-400' }}"></span>{{ $linked ? 'Terhubung' : 'Belum terhubung' }}
        </span>
    </div>

    @unless($enabled)
    <section class="fade-up mb-4 rounded-xl border border-amber-200 bg-amber-50 p-5" style="--i:1">
        <p class="text-sm font-semibold text-amber-900">Bot belum dikonfigurasi di server</p>
        <p class="mt-1 text-sm text-amber-800">Admin server perlu mengisi <span class="font-mono">TELEGRAM_BOT_TOKEN</span>, <span class="font-mono">TELEGRAM_BOT_USERNAME</span>, dan <span class="font-mono">TELEGRAM_WEBHOOK_SECRET</span> di file .env, lalu menjalankan <span class="font-mono">php artisan telegram:webhook</span>.</p>
    </section>
    @endunless

    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_minmax(0,320px)] gap-5">
        <div class="space-y-5 min-w-0">

            {{-- Koneksi --}}
            <section class="fade-up rounded-xl border border-[#E5E7EB] bg-white p-5 sm:p-6" style="--i:1">
                <div class="flex items-start gap-4">
                    <span class="w-12 h-12 rounded-xl bg-gradient-to-br from-sky-400 to-blue-600 text-white flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6 float" viewBox="0 0 24 24" fill="currentColor"><path d="M21.5 3.2L2.7 10.4c-.8.3-.8 1.200 0 1.500l4.700 1.500 1.800 5.600c.2.600.9.700 1.300.300l2.600-2.400 4.800 3.500c.6.400 1.300.1 1.500-.6L22.700 4.400c.2-.9-.5-1.500-1.200-1.200zM9.200 13.100l9.100-5.700-7.100 6.800-.3 3.100z"/></svg>
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold">{{ $linked ? 'Telegram sudah terhubung' : 'Hubungkan Telegram' }}</h2>
                        @if($linked)
                        <p class="mt-1 text-sm text-[#64748B]">Chat <span class="font-semibold text-[#0B1B3F]">{{ $tg['chat_name'] ?: 'Telegram' }}</span>@if(!empty($tg['linked_at'])) &middot; sejak {{ \Carbon\Carbon::parse($tg['linked_at'])->format('d M Y, H:i') }}@endif</p>
                        @else
                        <p class="mt-1 text-sm text-[#64748B]">Satu bot untuk semua toko. Kamu cukup menautkan chat Telegram milikmu atau grup tokomu.</p>
                        @endif
                    </div>
                </div>

                @if($linked)
                <div class="mt-5 flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('settings.telegram.test') }}">@csrf
                        <button class="h-10 inline-flex items-center gap-2 px-4 rounded-lg bg-[#16A34A] text-white text-sm font-semibold hover:bg-[#15803D] transition-colors shadow-sm">Kirim pesan tes</button>
                    </form>
                    <form method="POST" action="{{ route('settings.telegram.unlink') }}" onsubmit="return confirm('Putuskan Telegram? Notifikasi akan berhenti.')">@csrf
                        <button class="h-10 inline-flex items-center px-4 rounded-lg border border-[#E5E7EB] text-sm font-semibold text-red-600 hover:bg-red-50 transition-colors">Putuskan</button>
                    </form>
                </div>
                @elseif($enabled)
                <ol class="mt-5 space-y-3 text-sm">
                    <li class="flex gap-3"><span class="w-6 h-6 rounded-full bg-[#16A34A] text-white text-xs font-bold flex items-center justify-center shrink-0">1</span><span>Klik <b>Buat tautan</b> di bawah.</span></li>
                    <li class="flex gap-3"><span class="w-6 h-6 rounded-full bg-[#16A34A] text-white text-xs font-bold flex items-center justify-center shrink-0">2</span><span>Tekan <b>Buka Telegram</b>, lalu tekan <b>Start</b> di chat bot.</span></li>
                    <li class="flex gap-3"><span class="w-6 h-6 rounded-full bg-[#16A34A] text-white text-xs font-bold flex items-center justify-center shrink-0">3</span><span>Bot membalas "Terhubung". Muat ulang halaman ini.</span></li>
                </ol>

                @if($link)
                <div class="mt-5 rounded-lg border border-[#BBF7D0] bg-[#ECFDF3] p-4">
                    <a href="{{ $link }}" target="_blank" rel="noopener" class="h-11 w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 rounded-lg bg-[#16A34A] text-white text-sm font-semibold hover:bg-[#15803D] transition-colors shadow-sm">Buka Telegram</a>
                    <p class="mt-3 text-sm text-[#15803D]">Tautan berlaku 15 menit. Kalau tombol tidak membuka Telegram, kirim ke <span class="font-semibold">&#64;{{ ltrim($username, '@') }}</span> pesan: <span class="font-mono font-bold">/start {{ $code }}</span></p>
                </div>
                @else
                <form method="POST" action="{{ route('settings.telegram.link') }}" class="mt-5">@csrf
                    <button class="h-10 inline-flex items-center gap-2 px-4 rounded-lg bg-[#16A34A] text-white text-sm font-semibold hover:bg-[#15803D] transition-colors shadow-sm">Buat tautan</button>
                </form>
                @endif
                @endif
            </section>

            {{-- Preferensi --}}
            @if($linked)
            <section class="fade-up rounded-xl border border-[#E5E7EB] bg-white p-5 sm:p-6" style="--i:2">
                <h2 class="text-lg font-semibold">Notifikasi yang dikirim</h2>
                <form method="POST" action="{{ route('settings.telegram.prefs') }}" class="mt-4 divide-y divide-[#E5E7EB]">
                    @csrf
                    @foreach($prefs as $key => [$label, $desc, $on])
                    <label class="flex items-center gap-4 py-3.5 cursor-pointer" x-data="{ on: {{ $on ? 'true' : 'false' }} }">
                        <span class="flex-1 min-w-0">
                            <span class="block text-sm font-semibold">{{ $label }}</span>
                            <span class="block text-sm text-[#64748B]">{{ $desc }}</span>
                        </span>
                        <input type="checkbox" name="{{ $key }}" value="1" class="sr-only" x-model="on" {{ $on ? 'checked' : '' }}>
                        <span class="relative w-11 h-6 rounded-full transition-colors shrink-0" :class="on ? 'bg-[#16A34A]' : 'bg-slate-300'">
                            <span class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform" :class="on ? 'translate-x-5' : ''"></span>
                        </span>
                    </label>
                    @endforeach
                    <div class="pt-4">
                        <button class="h-10 inline-flex items-center px-5 rounded-lg bg-[#16A34A] text-white text-sm font-semibold hover:bg-[#15803D] transition-colors shadow-sm">Simpan</button>
                    </div>
                </form>
            </section>
            @endif
        </div>

        {{-- Perintah bot --}}
        <aside class="fade-up rounded-xl border border-[#E5E7EB] bg-white p-5 sm:p-6 self-start" style="--i:2">
            <h2 class="text-lg font-semibold">Perintah bot</h2>
            <p class="mt-1 text-sm text-[#64748B]">Ketik di chat Telegram yang sudah terhubung.</p>
            <ul class="mt-4 space-y-3 text-sm">
                @foreach([['/omzet','Ringkasan penjualan hari ini'],['/terlaris','5 produk terlaris'],['/stok','Bahan baku menipis'],['/shift','Shift yang sedang berjalan'],['/help','Daftar perintah'],['/putus','Putuskan koneksi']] as [$c, $d])
                <li class="flex items-start gap-3">
                    <code class="shrink-0 px-2 py-0.5 rounded-md bg-[#ECFDF3] text-[#15803D] font-semibold text-[13px]">{{ $c }}</code>
                    <span class="text-[#64748B]">{{ $d }}</span>
                </li>
                @endforeach
            </ul>
        </aside>
    </div>
</div>
@endsection
