@extends('layouts.app')
@section('title','Bot Telegram')
@section('page-title','Bot Telegram')

@section('content')
@php
    $linked  = !empty($tg['chat_id']);
    $step    = $linked ? 3 : ($code ? 2 : 1);
    $feature = [
        'daily'  => ['Laporan harian', 'Ringkasan omzet, transaksi, dan produk terlaris tiap pukul 21.00.',
                     'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', 'from-emerald-500 to-teal-500'],
        'alerts' => ['Peringatan', 'Stok menipis, shift lupa ditutup, serta order yang di-void atau di-refund.',
                     'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z', 'from-amber-400 to-orange-500'],
        'orders' => ['Order baru', 'Kabar tiap transaksi yang masuk. Bisa ramai di toko yang sibuk.',
                     'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 012-2h2a2 2 0 012 2M9 5h6m-6 7l2 2 4-4', 'from-sky-500 to-blue-500'],
    ];
@endphp

<div class="page text-[#0B1B3F] pt-2">

    {{-- Hero --}}
    <section class="fade-up relative overflow-hidden rounded-xl border border-[#BBF7D0] bg-gradient-to-br from-[#ECFDF3] via-white to-white p-5 sm:p-8" style="--i:0">
        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_340px] gap-8 items-center">

            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="w-12 h-12 rounded-xl bg-gradient-to-br from-sky-400 to-blue-600 text-white flex items-center justify-center shadow-sm">
                        <svg class="w-6 h-6 float" viewBox="0 0 24 24" fill="currentColor"><path d="M21.5 3.2L2.7 10.4c-.8.3-.8 1.2 0 1.5l4.7 1.5 1.8 5.6c.2.6.9.7 1.3.3l2.6-2.4 4.8 3.5c.6.4 1.3.1 1.5-.6L22.7 4.4c.2-.9-.5-1.5-1.2-1.2zM9.2 13.1l9.1-5.7-7.1 6.8-.3 3.100z"/></svg>
                    </span>
                    <span class="inline-flex items-center gap-1.5 h-8 px-3 rounded-full text-sm font-semibold {{ $linked ? 'bg-[#16A34A] text-white' : 'bg-white border border-[#E5E7EB] text-[#64748B]' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $linked ? 'bg-white' : 'bg-slate-400' }}"></span>{{ $linked ? 'Terhubung' : 'Belum terhubung' }}
                    </span>
                </div>

                <h2 class="mt-4 text-2xl sm:text-[28px] sm:leading-9 font-semibold">Laporan toko, langsung di Telegram</h2>
                <p class="mt-2 text-[15px] text-[#64748B] max-w-xl">Dapatkan ringkasan penjualan dan peringatan penting tanpa membuka aplikasi. Tanya angka kapan saja dengan bahasa sehari-hari, misalnya <span class="px-1.5 py-0.5 rounded bg-white border border-[#E5E7EB] text-[#15803D] font-semibold text-[13px]">"omzet hari ini berapa?"</span>, tanpa perintah rumit.</p>

                {{-- Aksi --}}
                <div class="mt-6">
                    @if($linked)
                    <p class="text-sm text-[#64748B] mb-3">Chat <span class="font-semibold text-[#0B1B3F]">{{ $tg['chat_name'] ?: 'Telegram' }}</span>@if(!empty($tg['linked_at'])) terhubung sejak {{ \Carbon\Carbon::parse($tg['linked_at'])->format('d M Y, H:i') }}@endif.</p>
                    <div class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('settings.telegram.test') }}">@csrf
                            <button class="h-10 inline-flex items-center gap-2 px-5 rounded-lg bg-[#16A34A] text-white text-sm font-semibold hover:bg-[#15803D] transition-colors shadow-sm">Kirim pesan tes</button>
                        </form>
                        <form method="POST" action="{{ route('settings.telegram.unlink') }}" onsubmit="return confirm('Putuskan Telegram? Notifikasi akan berhenti.')">@csrf
                            <button class="h-10 inline-flex items-center px-5 rounded-lg border border-[#E5E7EB] bg-white text-sm font-semibold text-red-600 hover:bg-red-50 transition-colors">Putuskan</button>
                        </form>
                    </div>
                    @elseif(!$enabled)
                    <div class="inline-flex items-start gap-3 rounded-lg border border-[#E5E7EB] bg-white p-4 max-w-xl">
                        <span class="w-9 h-9 rounded-lg bg-slate-100 text-slate-500 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold">Fitur ini belum diaktifkan</p>
                            <p class="text-sm text-[#64748B]">Bot Telegram sedang disiapkan oleh tim Yoka. Hubungi support agar bisa segera dipakai di tokomu.</p>
                        </div>
                    </div>
                    @elseif($link)
                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ $link }}" target="_blank" rel="noopener" class="group h-11 inline-flex items-center gap-2 px-6 rounded-lg bg-[#16A34A] text-white text-sm font-semibold hover:bg-[#15803D] transition-colors shadow-sm">
                            Buka Telegram
                            <svg class="w-4 h-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        </a>
                        <a href="{{ route('settings.telegram') }}" class="h-11 inline-flex items-center px-5 rounded-lg border border-[#E5E7EB] bg-white text-sm font-semibold hover:bg-slate-50 transition-colors">Saya sudah menekan Start</a>
                    </div>
                    <p class="mt-3 text-sm text-[#64748B]">Tautan berlaku 15 menit. Kalau Telegram tidak terbuka, kirim pesan <span class="font-mono font-bold text-[#0B1B3F]">/start {{ $code }}</span> ke <span class="font-semibold text-[#0B1B3F]">&#64;{{ ltrim($username, '@') }}</span>.</p>
                    @else
                    <form method="POST" action="{{ route('settings.telegram.link') }}">@csrf
                        <button class="group h-11 inline-flex items-center gap-2 px-6 rounded-lg bg-[#16A34A] text-white text-sm font-semibold hover:bg-[#15803D] transition-colors shadow-sm">
                            Hubungkan Telegram
                            <svg class="w-4 h-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        </button>
                    </form>
                    @endif
                </div>

                {{-- Langkah --}}
                <ol class="mt-7 grid grid-cols-1 sm:grid-cols-3 gap-3 max-w-2xl">
                    @foreach([['Buat tautan', 'Klik tombol hubungkan.'], ['Tekan Start', 'Di chat bot Telegram.'], ['Selesai', 'Bot membalas "Terhubung".']] as $i => [$t, $d])
                    @php $n = $i + 1; $state = $step > $n || ($linked && $n <= 3) ? 'done' : ($step === $n ? 'now' : 'todo'); @endphp
                    <li class="flex items-start gap-3 rounded-lg border p-3 transition-colors {{ $state === 'now' ? 'border-[#16A34A] bg-white shadow-sm' : 'border-[#E5E7EB] bg-white/70' }}">
                        <span class="w-7 h-7 rounded-full text-xs font-bold flex items-center justify-center shrink-0
                                     {{ $state === 'todo' ? 'bg-slate-100 text-slate-400' : 'bg-[#16A34A] text-white' }}">
                            @if($state === 'done')
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            @else {{ $n }} @endif
                        </span>
                        <span class="min-w-0"><span class="block text-sm font-semibold leading-tight">{{ $t }}</span><span class="block text-[13px] text-[#64748B] mt-0.5">{{ $d }}</span></span>
                    </li>
                    @endforeach
                </ol>
            </div>

            {{-- Mockup ponsel --}}
            <div class="hidden lg:flex justify-center" aria-hidden="true">
                <div class="float w-[300px] rounded-[28px] border-[6px] border-[#0B1B3F] bg-[#E8F0F7] shadow-xl overflow-hidden">
                    <div class="flex items-center gap-2.5 bg-white px-3.5 py-2.5 border-b border-[#E5E7EB]">
                        <span class="w-8 h-8 rounded-full bg-[#16A34A] flex items-center justify-center"><img src="{{ asset('images/brand/yoka-icon.png') }}" alt="" class="w-5 h-5 object-contain bg-white rounded-full p-0.5"></span>
                        <div class="leading-tight"><p class="text-[13px] font-bold">Yoka POS Bot</p><p class="text-[11px] text-[#16A34A]">bot</p></div>
                    </div>
                    <div class="h-[330px] px-3 py-4 space-y-2.5 text-[12.5px] leading-snug">
                        <div class="pop-in max-w-[88%] rounded-2xl rounded-bl-md bg-white px-3 py-2 shadow-sm" style="--d:0">
                            <p class="font-bold">Ringkasan hari ini</p>
                            <p class="text-[#64748B]">Toko Kopi Senja</p>
                            <p class="mt-1.5">Omzet: <b>Rp 2.850.000</b></p>
                            <p>Transaksi: <b>64</b></p>
                            <p class="mt-1.5 font-semibold">Terlaris</p>
                            <p class="text-[#64748B]">1. Es Kopi Susu, 28 terjual</p>
                        </div>
                        <div class="pop-in max-w-[88%] rounded-2xl rounded-bl-md bg-white px-3 py-2 shadow-sm" style="--d:1.2">
                            <p class="font-bold text-amber-600">Stok menipis</p>
                            <p class="text-[#64748B]">Susu UHT: 2,0 liter</p>
                            <p class="text-[#64748B]">Gula pasir: 1,5 kg</p>
                        </div>
                        <div class="pop-in ml-auto max-w-[60%] rounded-2xl rounded-br-md bg-[#DCF8C6] px-3 py-2 shadow-sm" style="--d:2.4; transform-origin:right bottom">Omzet kemarin berapa?</div>
                        <div class="pop-in max-w-[50%] rounded-2xl rounded-bl-md bg-white px-3.5 py-2.5 shadow-sm" style="--d:3.2"><span class="typing"><i></i><i></i><i></i></span></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Notifikasi --}}
    <section class="fade-up mt-5" style="--i:1">
        <div class="flex items-end justify-between gap-2 mb-3">
            <div>
                <h3 class="text-lg font-semibold">Notifikasi yang dikirim</h3>
                <p class="text-sm text-[#64748B]">{{ $linked ? 'Aktifkan yang kamu butuhkan, lalu simpan.' : 'Pilihan ini bisa diatur setelah Telegram terhubung.' }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('settings.telegram.prefs') }}">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach($feature as $key => [$label, $desc, $icon, $grad])
                @php $on = $business->telegramWants($key); @endphp
                <label class="group relative rounded-xl border bg-white p-5 transition-all {{ $linked ? 'cursor-pointer hover:shadow-md border-[#E5E7EB]' : 'border-[#E5E7EB] opacity-80' }}"
                       x-data="{ on: {{ $on ? 'true' : 'false' }} }" :class="on && {{ $linked ? 'true' : 'false' }} ? '!border-[#16A34A] ring-1 ring-[#16A34A]/30' : ''">
                    <div class="flex items-start justify-between gap-3">
                        <span class="w-11 h-11 rounded-xl bg-gradient-to-br {{ $grad }} text-white flex items-center justify-center shadow-sm">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                        </span>
                        <input type="checkbox" name="{{ $key }}" value="1" class="sr-only" x-model="on" {{ $on ? 'checked' : '' }} {{ $linked ? '' : 'disabled' }}>
                        <span class="relative w-11 h-6 rounded-full transition-colors shrink-0" :class="on ? 'bg-[#16A34A]' : 'bg-slate-300'">
                            <span class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform" :class="on ? 'translate-x-5' : ''"></span>
                        </span>
                    </div>
                    <p class="mt-4 text-base font-semibold">{{ $label }}</p>
                    <p class="mt-1 text-sm text-[#64748B]">{{ $desc }}</p>
                </label>
                @endforeach
            </div>
            @if($linked)
            <div class="mt-4">
                <button class="h-10 inline-flex items-center px-6 rounded-lg bg-[#16A34A] text-white text-sm font-semibold hover:bg-[#15803D] transition-colors shadow-sm">Simpan pengaturan</button>
            </div>
            @endif
        </form>
    </section>

    {{-- Contoh pertanyaan --}}
    <section class="fade-up mt-5 rounded-xl border border-[#E5E7EB] bg-white p-5 sm:p-6" style="--i:2">
        <h3 class="text-lg font-semibold">Contoh yang bisa kamu tanyakan</h3>
        <p class="text-sm text-[#64748B]">Ketik bebas seperti chat biasa, atau tekan tombol cepat yang muncul di Telegram. Sebut waktunya kalau perlu: hari ini, kemarin, minggu ini, bulan lalu.</p>
        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach([
                ['Penjualan', 'Omzet hari ini berapa?'],
                ['Transaksi', 'Kemarin ada berapa transaksi?'],
                ['Laba', 'Untung minggu ini berapa?'],
                ['Terlaris', 'Produk apa yang paling laris bulan ini?'],
                ['Per produk', 'Es kopi susu terjual berapa hari ini?'],
                ['Stok', 'Stok apa yang mau habis?'],
                ['Pembayaran', 'Pembayaran QRIS bulan ini berapa?'],
                ['Kasir', 'Siapa yang lagi jaga kasir?'],
                ['Ringkasan', 'Gimana toko hari ini?'],
            ] as [$tag, $q])
            <div class="flex items-start gap-3 rounded-lg bg-slate-50 p-3.5">
                <span class="shrink-0 mt-0.5 px-2 py-0.5 rounded-md bg-[#ECFDF3] text-[#15803D] text-xs font-semibold">{{ $tag }}</span>
                <span class="text-sm font-medium">"{{ $q }}"</span>
            </div>
            @endforeach
        </div>
        <p class="mt-4 text-sm text-[#64748B]">Untuk memutus koneksi dari Telegram, ketik <span class="font-mono font-semibold text-[#0B1B3F]">/putus</span>.</p>
    </section>
</div>
@endsection
