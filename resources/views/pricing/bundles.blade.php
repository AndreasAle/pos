@php
    $seoTitle = 'Paket Bundling Kasir Yoka POS: Aplikasi + Printer, Tablet, Mesin Kasir';
    $seoDescription = 'Paket kasir siap pakai: aplikasi Yoka POS '.$months.' bulan plus printer struk, tablet, barcode scanner, laci kas, atau mesin kasir Android. Dites dan dipasang tim Yoka.';
    $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
@endphp
@extends('layouts.site')

@section('content')
{{-- Pembuka --}}
<section class="bg-emerald-600 pt-[96px] text-white sm:pt-[100px] lg:pt-[104px]">
    <div class="mx-auto grid max-w-[1200px] items-center gap-8 px-5 py-12 sm:px-6 sm:py-16 lg:grid-cols-[1.1fr_.9fr] lg:py-20 [&>*]:min-w-0">
        <div>
            <p class="text-xs font-bold uppercase tracking-[.16em] text-emerald-100 sm:text-sm">Paket bundling</p>
            <h1 class="mt-3 text-[32px] font-extrabold leading-[1.08] tracking-[-.03em] sm:text-[42px] lg:text-[52px]">Kasir lengkap. Datang, dipasang, langsung jualan.</h1>
            <p class="mt-4 max-w-[560px] text-[16px] leading-relaxed text-emerald-50 sm:text-[18px]">Aplikasi Yoka POS {{ $months }} bulan plus perangkat yang sudah kami tes. Kamu tidak perlu pusing cari printer yang cocok.</p>
            <a href="#paket" class="mt-7 inline-flex rounded-full bg-white px-7 py-3.5 font-extrabold text-emerald-900 transition hover:bg-emerald-50">Lihat paket</a>
        </div>
        <div class="relative mx-auto flex h-52 w-full min-w-0 max-w-[460px] items-end justify-center overflow-hidden sm:h-64">
            <div class="absolute inset-x-6 bottom-0 h-24 rounded-[50%] bg-white/10 blur-2xl"></div>
            <div class="min-w-0 shrink relative h-44 w-52 sm:h-56 sm:w-64"><x-device-art kind="tablet"/></div>
            <div class="min-w-0 shrink relative -ml-10 h-28 w-32 sm:h-36 sm:w-40"><x-device-art kind="printer"/></div>
            <div class="min-w-0 shrink relative -ml-6 h-36 w-20 sm:h-44 sm:w-24"><x-device-art kind="mpos"/></div>
        </div>
    </div>
</section>

{{-- Termasuk di setiap paket --}}
<section class="border-b border-slate-100 px-5 py-10 sm:px-6">
    <div class="mx-auto grid max-w-[1200px] gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-4">
        @foreach([
            ['Aplikasi YokaBasic '.$months.' bulan', 'Senilai '.$rp($basic12).'. Bisa naik ke paket Business.'],
            ['Dipasang dan diajari', 'Kunjungan setup di Palembang, Google Meet untuk luar kota.'],
            ['Perangkat sudah dites', 'Kami cek dengan Yoka sebelum sampai ke tanganmu.'],
            ['Garansi tukar '.$warrantyDays.' hari', 'Unit bermasalah kami ganti, tanpa ribet.'],
        ] as [$t, $d])
            <div class="flex gap-3">
                <svg class="mt-0.5 h-6 w-6 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                <div><p class="font-bold text-slate-900">{{ $t }}</p><p class="mt-0.5 text-sm leading-relaxed text-slate-600">{{ $d }}</p></div>
            </div>
        @endforeach
    </div>
</section>

{{-- Daftar paket --}}
<section id="paket" class="scroll-mt-24 bg-slate-50 px-5 py-14 sm:px-6 sm:py-20">
    <div class="mx-auto max-w-[1200px]">
        <h2 class="text-[26px] font-extrabold tracking-tight text-slate-900 sm:text-[32px]">Pilih paketmu</h2>
        <p class="mt-2 text-slate-600">Semua harga sudah termasuk aplikasi {{ $months }} bulan, setup, dan garansi tukar.</p>

        <div class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
            @foreach($bundles as $b)
                @php $featured = ! empty($b['featured']); @endphp
                <article class="relative flex flex-col overflow-hidden rounded-3xl bg-white {{ $featured ? 'border-2 border-emerald-500 shadow-[0_24px_60px_-28px_rgba(5,150,105,.45)]' : 'border border-slate-200' }}">
                    @if($featured)<span class="absolute left-5 top-4 z-10 rounded-full bg-emerald-600 px-3 py-1 text-xs font-bold text-white">Paling pas untuk warung</span>@endif
                    <div class="flex h-44 items-end justify-center gap-1 bg-gradient-to-b from-emerald-50 to-white px-4 pb-3 pt-10">
                        @foreach(collect($b['devices'])->reject(fn ($d) => $d['key'] === 'stand')->take(4) as $d)
                            <div class="min-w-0 shrink {{ $loop->count > 2 ? 'h-20 w-20' : 'h-28 w-28' }} {{ ! $loop->first ? '-ml-3' : '' }}"><x-device-art :kind="$d['art']"/></div>
                        @endforeach
                    </div>
                    <div class="flex flex-1 flex-col p-6">
                        <h3 class="text-lg font-extrabold text-slate-900">{{ $b['name'] }}</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ $b['for'] }}</p>
                        <p class="mt-4 text-[28px] font-extrabold leading-none tracking-tight text-slate-900">{{ $rp($b['price']) }}</p>
                        @if($b['saving'] > 0)
                            <p class="mt-2 text-sm"><span class="text-slate-400 line-through">{{ $rp($b['separate']) }}</span> <span class="ml-1 font-bold text-emerald-700">hemat {{ $rp($b['saving']) }}</span></p>
                        @endif
                        <ul class="mt-5 flex-1 space-y-2 text-sm text-slate-600">
                            <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-500"></span>Aplikasi YokaBasic {{ $months }} bulan</li>
                            @foreach($b['devices'] as $d)
                                <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-500"></span>{{ $d['name'] }}</li>
                            @endforeach
                        </ul>
                        @if(! empty($b['note']))<p class="mt-3 text-xs text-slate-500">{{ $b['note'] }}</p>@endif
                        <a href="{{ $b['wa'] }}" target="_blank" rel="noopener" class="mt-6 block rounded-xl px-4 py-3 text-center font-bold transition {{ $featured ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'border border-slate-300 text-slate-800 hover:bg-slate-50' }}">Pesan paket ini</a>
                    </div>
                </article>
            @endforeach
        </div>
        <p class="mt-6 text-sm text-slate-500">Mau pakai paket Business? Tambah selisih harganya, tim kami bantu hitungkan. Ongkir ke luar Palembang sesuai tarif kurir.</p>
    </div>
</section>

{{-- Perangkat satuan --}}
<section class="px-5 py-14 sm:px-6 sm:py-20">
    <div class="mx-auto max-w-[1200px]">
        <h2 class="text-[26px] font-extrabold tracking-tight text-slate-900 sm:text-[32px]">Perangkat satuan</h2>
        <p class="mt-2 text-slate-600">Sudah langganan dan cuma butuh tambahan perangkat? Bisa beli satuan.</p>
        <div class="mt-8 grid grid-cols-2 gap-4 sm:gap-5 md:grid-cols-4">
            @foreach($devices as $key => $d)
                <div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
                    <div class="min-w-0 shrink mx-auto h-24 w-28 sm:h-28 sm:w-32"><x-device-art :kind="$d['art']"/></div>
                    <p class="mt-3 font-bold text-slate-900">{{ $d['name'] }}</p>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500 sm:text-sm">{{ $d['spec'] }}</p>
                    <p class="mt-3 font-extrabold text-slate-900">{{ $rp($d['price']) }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Cara pesan --}}
<section class="bg-[#0B1B3F] px-5 py-14 text-white sm:px-6 sm:py-20">
    <div class="mx-auto max-w-[1200px]">
        <h2 class="text-[26px] font-extrabold tracking-tight sm:text-[32px]">Cara pesan</h2>
        <ol class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach([
                ['Pilih paket', 'Tekan Pesan paket ini, pesanmu langsung masuk ke WhatsApp tim Yoka.'],
                ['Konfirmasi', 'Kami cek kebutuhan tokomu dan kirim rincian pembayaran.'],
                ['Kami siapkan', 'Perangkat dicek dan disambungkan dengan akun Yoka-mu.'],
                ['Mulai jualan', 'Kami datang untuk setup di Palembang, atau kirim paket dan dampingi lewat Google Meet.'],
            ] as $i => [$t, $d])
                <li>
                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500 font-extrabold">{{ $i + 1 }}</span>
                    <p class="mt-4 font-bold">{{ $t }}</p>
                    <p class="mt-1 text-sm leading-relaxed text-slate-300">{{ $d }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>

{{-- Tanya jawab --}}
<section class="bg-slate-50 px-5 py-14 sm:px-6 sm:py-20" x-data="{ open: 0 }">
    <div class="mx-auto max-w-[820px]">
        <h2 class="text-[26px] font-extrabold tracking-tight text-slate-900 sm:text-[32px]">Pertanyaan soal bundling</h2>
        <div class="mt-8 divide-y divide-slate-200 rounded-2xl border border-slate-200 bg-white">
            @foreach([
                ['Saya sudah punya HP Android. Perlu tablet?', 'Tidak wajib. Yoka jalan di browser HP. Kalau butuh struk, Paket Printer sudah cukup.'],
                ['Perangkatnya jadi milik saya?', 'Ya. Perangkat jadi milikmu sepenuhnya, termasuk setelah masa langganan habis.'],
                ['Kalau perangkat rusak?', 'Dalam '.$warrantyDays.' hari pertama, unit bermasalah kami tukar. Setelah itu berlaku garansi dari produsen.'],
                ['Bisa dikirim ke luar Palembang?', 'Bisa. Ongkir sesuai tarif kurir, lalu setup dan pelatihan lewat Google Meet.'],
                ['Kenapa lebih mahal sedikit dari harga di marketplace?', 'Karena sudah termasuk aplikasi '.$months.' bulan, pemasangan, pengecekan dengan Yoka, dan garansi tukar dari kami. Kamu tidak perlu coba-coba sendiri.'],
            ] as $i => [$q, $a])
                <div>
                    <button type="button" @click="open = open === {{ $i }} ? null : {{ $i }}" class="flex w-full items-center justify-between gap-4 px-6 py-4 text-left font-semibold text-slate-800 hover:bg-slate-50">
                        <span>{{ $q }}</span>
                        <svg class="h-5 w-5 shrink-0 text-emerald-600 transition-transform" :class="open === {{ $i }} ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open === {{ $i }}" x-cloak class="px-6 pb-4 text-[15px] leading-relaxed text-slate-600">{{ $a }}</div>
                </div>
            @endforeach
        </div>
        <p class="mt-6 text-center text-sm text-slate-500">Lihat juga <a href="{{ route('pricing') }}" class="font-semibold text-emerald-700 hover:underline">harga paket langganan</a>.</p>
    </div>
</section>
@endsection
