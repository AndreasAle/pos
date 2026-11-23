@php
    $seoTitle = 'Harga Yoka POS: Mulai Rp'.number_format(collect($plans)->min('monthly'), 0, ',', '.').' per Bulan';
    $seoDescription = 'Harga aplikasi kasir Yoka POS untuk warung, kafe, dan resto. Bayar bulanan atau hemat dengan paket 3, 6, dan 12 bulan. Coba gratis 7 hari.';
    $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
    $maxSave = collect($plans)->filter(fn ($p) => count($p['periods']) > 1)->map(fn ($p) => (int) round((1 - $p['periods'][max(array_keys($p['periods']))] / ($p['monthly'] * max(array_keys($p['periods'])))) * 100))->max();
@endphp
@extends('layouts.site')

@section('content')
{{-- Pembuka --}}
<section class="bg-emerald-600 pt-[60px] text-white sm:pt-[68px] lg:pt-[76px]">
    <div class="mx-auto max-w-[1200px] px-5 py-12 sm:px-6 sm:py-16 lg:py-20">
        <p class="text-xs font-bold uppercase tracking-[.16em] text-emerald-100 sm:text-sm">Harga</p>
        <h1 class="mt-3 max-w-[760px] text-[32px] font-extrabold leading-[1.08] tracking-[-.03em] sm:text-[42px] lg:text-[52px]">Harga jelas. Tanpa biaya pemasangan, tanpa kontrak.</h1>
        <p class="mt-4 max-w-[620px] text-[16px] leading-relaxed text-emerald-50 sm:text-[18px]">Pilih paket sesuai jumlah outlet. Bayar bulanan, atau bayar di muka untuk 3, 6, atau 12 bulan supaya lebih hemat. Semua paket bisa dicoba gratis 7 hari.</p>
    </div>
</section>

<section class="bg-slate-50 px-5 py-12 sm:px-6 sm:py-16" x-data="{ m: 12 }">
    <div class="mx-auto max-w-[1200px]">
        {{-- Pilih durasi --}}
        <div class="flex flex-col items-center gap-3">
            <div class="inline-flex rounded-full border border-slate-200 bg-white p-1 shadow-sm">
                @foreach($durations as $d)
                    <button type="button" @click="m = {{ $d }}" class="rounded-full px-4 py-2 text-sm font-bold transition sm:px-5"
                            :class="m === {{ $d }} ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:text-slate-900'">{{ $d }} bulan</button>
                @endforeach
            </div>
            @if($maxSave > 0)<p class="text-sm text-slate-500">Bayar 12 bulan sekaligus, hemat sampai <span class="font-bold text-emerald-700">{{ $maxSave }}%</span>.</p>@endif
        </div>

        {{-- Kartu paket --}}
        <div class="mt-10 grid gap-5 lg:grid-cols-3">
            @foreach($plans as $p)
                @php $featured = ! empty($p['featured']); @endphp
                <article class="relative flex flex-col rounded-3xl bg-white p-7 sm:p-8 {{ $featured ? 'border-2 border-emerald-500 shadow-[0_24px_60px_-28px_rgba(5,150,105,.45)]' : 'border border-slate-200' }}"
                         x-data="{ periods: @js($p['periods']), monthly: {{ $p['monthly'] }} }">
                    @if($featured)<span class="absolute -top-3 left-7 rounded-full bg-emerald-600 px-3 py-1 text-xs font-bold text-white">Paling banyak dipilih</span>@endif
                    <h2 class="text-xl font-extrabold text-slate-900">{{ $p['name'] }}</h2>
                    <p class="mt-1 min-h-[40px] text-sm text-slate-500">{{ $p['tag'] }}</p>

                    <div class="mt-6">
                        @if(! empty($p['custom']))
                            <p class="text-sm text-slate-500">Mulai dari</p>
                            <p class="text-[34px] font-extrabold leading-none tracking-tight text-slate-900">{{ $rp($p['monthly']) }}<span class="text-base font-semibold text-slate-500"> /bulan</span></p>
                            <p class="mt-2 text-sm text-slate-500">Harga akhir sesuai kebutuhan.</p>
                        @else
                            <template x-if="periods[m]">
                                <div>
                                    <p class="text-[34px] font-extrabold leading-none tracking-tight text-slate-900"><span x-text="'Rp' + new Intl.NumberFormat('id-ID').format(Math.round(periods[m] / m))"></span><span class="text-base font-semibold text-slate-500"> /bulan</span></p>
                                    <p class="mt-2 text-sm text-slate-500" x-show="m > 1">Dibayar <span class="font-semibold text-slate-700" x-text="'Rp' + new Intl.NumberFormat('id-ID').format(periods[m])"></span> untuk <span x-text="m"></span> bulan
                                        <span class="ml-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-bold text-emerald-700" x-text="'hemat ' + Math.round((1 - periods[m] / (monthly * m)) * 100) + '%'"></span></p>
                                    <p class="mt-2 text-sm text-slate-500" x-show="m === 1">Ditagih tiap bulan. Bisa berhenti kapan saja.</p>
                                </div>
                            </template>
                        @endif
                    </div>

                    <p class="mt-6 text-sm font-semibold text-slate-700">{{ $p['outlets'] }} &middot; {{ $p['users'] }}</p>
                    <ul class="mt-4 flex-1 space-y-3">
                        @foreach($p['features'] as $f)
                            <li class="flex gap-3 text-[15px] text-slate-600">
                                <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                {{ $f }}
                            </li>
                        @endforeach
                    </ul>

                    @if(! empty($p['custom']))
                        <a href="{{ \App\Http\Controllers\PricingController::WA.rawurlencode('Halo, saya mau konsultasi paket Custom Yoka POS') }}" target="_blank" rel="noopener" class="mt-8 block rounded-xl border border-slate-300 px-4 py-3 text-center font-bold text-slate-800 transition hover:bg-slate-50">Konsultasi via WhatsApp</a>
                    @else
                        <a href="{{ route('register') }}" class="mt-8 block rounded-xl px-4 py-3 text-center font-bold transition {{ $featured ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'border border-slate-300 text-slate-800 hover:bg-slate-50' }}">Coba gratis 7 hari</a>
                    @endif
                </article>
            @endforeach
        </div>
        <p class="mt-6 text-center text-sm text-slate-500">Harga dalam Rupiah per bisnis. Pembayaran aman lewat Midtrans: QRIS, transfer bank, dan e-wallet.</p>
    </div>
</section>

{{-- Termasuk di semua paket --}}
<section class="px-5 py-16 sm:px-6 sm:py-20">
    <div class="mx-auto max-w-[1200px]">
        <h2 class="text-[26px] font-extrabold tracking-tight text-slate-900 sm:text-[32px]">Sudah termasuk di semua paket</h2>
        <div class="mt-8 grid gap-x-8 gap-y-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach([
                ['Trial 7 hari', 'Semua fitur terbuka. Tanpa kartu kredit.'],
                ['Dibantu sampai jalan', 'Onboarding lewat Google Meet. Di Palembang, kami datang ke tempatmu untuk paket 6 dan 12 bulan.'],
                ['Update gratis', 'Fitur baru langsung masuk ke akunmu tanpa biaya tambahan.'],
                ['Data milikmu', 'Ekspor data kapan saja. Data tetap tersimpan saat ganti paket.'],
            ] as [$t, $d])
                <div class="border-t-2 border-emerald-500 pt-4">
                    <p class="font-bold text-slate-900">{{ $t }}</p>
                    <p class="mt-1.5 text-[15px] leading-relaxed text-slate-600">{{ $d }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Ajakan ke bundling --}}
<section class="px-5 pb-16 sm:px-6 sm:pb-20">
    <a href="{{ route('pricing.bundles') }}" class="group mx-auto flex max-w-[1200px] flex-col items-center gap-6 overflow-hidden rounded-3xl bg-[#0B1B3F] p-7 text-white sm:p-10 md:flex-row">
        <div class="flex-1">
            <p class="text-xs font-bold uppercase tracking-[.16em] text-emerald-300">Paket bundling</p>
            <p class="mt-2 text-[24px] font-extrabold leading-tight sm:text-[30px]">Belum punya perangkat kasir?</p>
            <p class="mt-2 max-w-[520px] text-slate-300">Aplikasi 12 bulan plus printer, tablet, atau mesin kasir yang sudah kami tes dengan Yoka. Mulai {{ $rp($bundleFrom) }}.</p>
            <span class="mt-5 inline-flex items-center gap-2 font-bold text-emerald-300 transition group-hover:gap-3">Lihat paket bundling <span aria-hidden="true">&rarr;</span></span>
        </div>
        <div class="flex h-32 w-full min-w-0 max-w-[340px] items-end justify-center gap-2 sm:h-40">
            <div class="min-w-0 shrink h-24 w-28 sm:h-32 sm:w-36"><x-device-art kind="tablet"/></div>
            <div class="min-w-0 shrink h-20 w-24 sm:h-28 sm:w-28"><x-device-art kind="printer"/></div>
            <div class="min-w-0 shrink h-24 w-16 sm:h-32 sm:w-20"><x-device-art kind="mpos"/></div>
        </div>
    </a>
</section>

@if($credits)
{{-- Kredit Yoka AI --}}
<section class="bg-slate-50 px-5 py-16 sm:px-6 sm:py-20">
    <div class="mx-auto grid max-w-[1200px] gap-10 lg:grid-cols-2">
        <div>
            <p class="text-xs font-bold uppercase tracking-[.16em] text-emerald-700">Tanya Yoka</p>
            <h2 class="mt-2 text-[26px] font-extrabold tracking-tight text-slate-900 sm:text-[32px]">Asisten AI dengan kredit terpisah</h2>
            <p class="mt-3 text-[16px] leading-relaxed text-slate-600">Tanya omzet, menu terlaris, atau minta ide promo. Setiap paket dapat jatah kredit gratis tiap bulan: masa coba {{ $credits['jatah_gratis_per_bulan']['masa_coba'] }}, YokaBasic {{ $credits['jatah_gratis_per_bulan']['YokaBasic'] }}, Business {{ $credits['jatah_gratis_per_bulan']['Business'] }}. Pertanyaan biasa memakai 1 kredit.</p>
        </div>
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            @foreach($creditPacks as $pack)
                <div class="flex items-center justify-between gap-4 px-6 py-4 {{ ! $loop->first ? 'border-t border-slate-100' : '' }}">
                    <p class="font-bold text-slate-900">{{ number_format($pack['credits'], 0, ',', '.') }} kredit</p>
                    <p class="text-sm text-slate-500">{{ $rp($pack['price'] / $pack['credits']) }} per kredit</p>
                    <p class="font-extrabold text-slate-900">{{ $rp($pack['price']) }}</p>
                </div>
            @endforeach
            <p class="border-t border-slate-100 bg-slate-50 px-6 py-3 text-xs text-slate-500">Kredit yang dibeli berlaku 12 bulan. Kasir dan laporan tetap jalan walau kredit habis.</p>
        </div>
    </div>
</section>
@endif

{{-- Layanan tambahan --}}
<section class="px-5 py-16 sm:px-6 sm:py-20">
    <div class="mx-auto max-w-[1200px]">
        <h2 class="text-[26px] font-extrabold tracking-tight text-slate-900 sm:text-[32px]">Layanan tambahan</h2>
        <p class="mt-2 text-slate-600">Hanya kalau kamu butuh. Tidak ada biaya wajib di luar paket.</p>
        <div class="mt-8 overflow-x-auto rounded-2xl border border-slate-200">
            <table class="w-full min-w-[560px] text-left text-[15px]">
                <thead class="bg-slate-50 text-sm text-slate-500">
                    <tr><th class="px-5 py-3 font-semibold sm:px-6">Layanan</th><th class="px-5 py-3 font-semibold sm:px-6">Paket 1 dan 3 bulan</th><th class="px-5 py-3 font-semibold sm:px-6">Paket 6, 12 bulan, dan bundling</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach([
                        ['Onboarding Google Meet (2 x 60 menit)', 'Gratis', 'Gratis'],
                        ['Kunjungan setup di Palembang (maks 2 jam)', 'Rp150.000', 'Gratis'],
                        ['Sesi Google Meet tambahan', 'Rp100.000 /jam', 'Rp100.000 /jam'],
                        ['Input menu di atas 50 item', 'Rp50.000 /50 item', 'Rp50.000 /50 item'],
                        ['Kunjungan ulang', 'Rp150.000', 'Rp150.000'],
                    ] as [$a, $b, $c])
                        <tr><td class="px-5 py-3.5 text-slate-800 sm:px-6">{{ $a }}</td><td class="px-5 py-3.5 text-slate-600 sm:px-6">{{ $b }}</td><td class="px-5 py-3.5 font-semibold sm:px-6 {{ $c === 'Gratis' ? 'text-emerald-700' : 'text-slate-600' }}">{{ $c }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-sm text-slate-500">Kunjungan ulang gratis kalau masalahnya dari sisi Yoka.</p>
    </div>
</section>

{{-- Tanya jawab --}}
<section class="bg-slate-50 px-5 py-16 sm:px-6 sm:py-20" x-data="{ open: 0 }">
    <div class="mx-auto max-w-[820px]">
        <h2 class="text-[26px] font-extrabold tracking-tight text-slate-900 sm:text-[32px]">Pertanyaan soal harga</h2>
        <div class="mt-8 divide-y divide-slate-200 rounded-2xl border border-slate-200 bg-white">
            @foreach([
                ['Apakah ada biaya pemasangan?', 'Tidak. Daftar, isi menu, dan langsung bisa transaksi. Bantuan onboarding lewat Google Meet gratis untuk semua paket.'],
                ['Kalau bayar 12 bulan lalu berhenti di tengah jalan?', 'Paket tetap aktif sampai masa berlakunya habis. Supaya yakin sebelum bayar di muka, mulai dengan trial gratis 7 hari dulu.'],
                ['Bisa ganti paket di tengah jalan?', 'Bisa. Pilih paket baru di halaman Langganan, data dan transaksi tetap tersimpan.'],
                ['Bisa dipakai di HP atau tablet yang sudah ada?', 'Bisa. Yoka jalan di browser HP, tablet, dan laptop. Perangkat tambahan hanya kalau kamu butuh, misalnya printer struk.'],
                ['Metode pembayaran apa saja?', 'Lewat Midtrans: QRIS, transfer bank (virtual account), dan e-wallet yang tersedia di halaman pembayaran.'],
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
    </div>
</section>
@endsection
