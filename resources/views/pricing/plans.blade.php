@php
    $seoTitle = 'Harga Yoka POS: Mulai Rp'.number_format(collect($plans)->min('monthly'), 0, ',', '.').' per Bulan';
    $seoDescription = 'Harga aplikasi kasir Yoka POS untuk warung, kafe, dan resto. Bayar bulanan atau hemat dengan paket 3, 6, dan 12 bulan. Coba gratis 7 hari.';
    $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
    $bySlug = collect($plans)->keyBy('slug');
    $wa = fn (string $text) => \App\Http\Controllers\PricingController::WA.rawurlencode($text);
    $check = '<svg class="h-5 w-5 text-[#16A34A]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.6"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';
    $no = '<span class="text-sm text-slate-400">Tidak termasuk</span>';

    // Tabel perbandingan: [label, keterangan, YokaBasic, Business, Custom]. true = centang, false = tidak termasuk, string = teks.
    $outlet = fn ($s) => $bySlug[$s]['outlets'] ?? '';
    $user = fn ($s) => $bySlug[$s]['users'] ?? '';
    $groups = [
        'Perangkat' => [
            ['Pakai HP, tablet, atau laptop sendiri', 'Yoka jalan di browser, tanpa perlu beli mesin khusus', true, true, true],
            ['Paket bundling perangkat', 'Printer, tablet, scanner, laci, atau mesin kasir', 'Opsional', 'Opsional', 'Opsional'],
        ],
        'Kapasitas' => [
            ['Outlet', null, $outlet('starter'), $outlet('business'), $outlet('enterprise')],
            ['Pengguna (pemilik, admin, kasir)', null, $user('starter'), $user('business'), $user('enterprise')],
        ],
        'Kasir & pembayaran' => [
            ['Kasir, struk, dan riwayat order', 'Cetak struk lewat printer Bluetooth atau USB', true, true, true],
            ['QRIS, tunai, dan pembayaran campuran', 'Termasuk bayar sebagian dan split bill', true, true, true],
            ['Void dan refund dengan alasan', 'Hanya pemilik dan admin', true, true, true],
            ['Shift kasir dan setoran', null, true, true, true],
        ],
        'Operasional resto' => [
            ['Produk, varian, add-on, bundling menu, promo', null, true, true, true],
            ['Stok bahan dan resep', 'Stok terpotong otomatis saat menu terjual', true, true, true],
            ['QR meja dan pesan mandiri', null, true, true, true],
            ['Layar dapur', null, true, true, true],
            ['Poin pelanggan', null, true, true, true],
        ],
        'Laporan' => [
            ['Laporan penjualan, produk terlaris, dan estimasi profit', null, true, true, true],
            ['Ekspor laporan ke Excel dan PDF', null, false, true, true],
            ['Integrasi API', null, false, false, true],
        ],
        'Yoka AI' => [
            ['Kredit Tanya Yoka gratis per bulan', 'Tanya omzet, menu terlaris, ide promo', '30 kredit', '100 kredit', '100 kredit'],
        ],
        'Bantuan' => [
            ['Onboarding Google Meet', '2 sesi x 60 menit', true, true, true],
            ['Kunjungan setup di Palembang', 'Gratis untuk paket 6 dan 12 bulan', 'Paket 6/12 bulan', 'Paket 6/12 bulan', true],
            ['Dukungan prioritas', null, false, false, true],
        ],
    ];
    $cols = ['starter', 'business', 'enterprise'];
    $solidHeader = true;
@endphp
@extends('layouts.site')

@section('content')
<div x-data="{ m: 12, price(p, m) { return p[m] ?? p[1] }, fmt(v) { return 'Rp' + new Intl.NumberFormat('id-ID').format(Math.round(v)) } }">

{{-- ===== Pembuka ===== --}}
<section class="px-5 pb-10 pt-[100px] sm:px-6 sm:pt-[120px] lg:pt-[140px]">
    <div class="mx-auto max-w-[1200px]">
        <p class="text-sm font-bold uppercase tracking-[.14em] text-[#16A34A]">Harga Yoka POS</p>
        <h1 class="mt-3 max-w-[860px] text-[38px] font-extrabold leading-[1.04] tracking-[-.035em] text-[#0B1B3F] sm:text-[52px] lg:text-[64px]">Harga yang pas untuk setiap usaha kuliner</h1>
        <ul class="mt-6 flex flex-wrap gap-x-6 gap-y-2 text-[15px] font-semibold text-slate-700">
            @foreach(array_filter(['Coba gratis 7 hari', 'Dibantu sampai jalan', $offline ? 'Kasir tetap jalan saat offline' : null, 'Tanpa kontrak']) as $b)
                <li class="flex items-center gap-2">{!! $check !!}{{ $b }}</li>
            @endforeach
        </ul>
    </div>
</section>

{{-- ===== Kartu paket ===== --}}
<section class="px-5 pb-16 sm:px-6">
    <div class="mx-auto max-w-[1200px]">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <p class="text-[15px] text-slate-600">Bayar di muka lebih hemat. Harga di bawah dihitung per bulan.</p>
            <div class="inline-flex rounded-full bg-slate-100 p-1" role="tablist" aria-label="Lama berlangganan">
                @foreach($durations as $d)
                    <button type="button" @click="m = {{ $d }}" class="rounded-full px-3 py-2 text-[13px] font-bold transition sm:px-4 sm:text-sm"
                            :class="m === {{ $d }} ? 'bg-[#0B1B3F] text-white shadow' : 'text-slate-600 hover:text-[#0B1B3F]'">{{ $d }} bulan</button>
                @endforeach
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3 [&>*]:min-w-0">
            @foreach($plans as $p)
                @php $custom = ! empty($p['custom']); @endphp
                <article class="flex flex-col rounded-[28px] bg-[#F3F6F4] p-7 sm:p-8" x-data="{ p: @js($p['periods']), base: {{ $p['monthly'] }} }">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-lg font-bold text-[#0B1B3F]">{{ $p['name'] }}</h2>
                        @if(! empty($p['featured']))<span class="rounded-full bg-[#16A34A] px-2.5 py-1 text-xs font-bold text-white">Paling banyak dipilih</span>@endif
                    </div>

                    <div class="mt-6 min-h-[92px]">
                        @if($custom)
                            <p class="text-[32px] font-extrabold leading-none tracking-[-.03em] text-[#0B1B3F] sm:text-[40px]">Harga khusus</p>
                            <p class="mt-3 text-sm text-slate-600">Mulai {{ $rp($p['monthly']) }} per bulan, disesuaikan dengan kebutuhanmu.</p>
                        @else
                            <p class="text-[32px] font-extrabold leading-none tracking-[-.03em] text-[#0B1B3F] sm:text-[40px]">
                                <span class="text-lg font-bold text-slate-500">Mulai</span>
                                <span x-text="fmt(price(p, m) / (p[m] ? m : 1))"></span><span class="text-lg font-bold text-slate-500">/bln</span>
                            </p>
                            <p class="mt-3 text-sm text-slate-600" x-show="m > 1 && p[m]">Dibayar <b class="text-[#0B1B3F]" x-text="fmt(p[m])"></b> untuk <span x-text="m"></span> bulan,
                                <span class="font-bold text-[#16A34A]" x-text="'hemat ' + Math.round((1 - p[m] / (base * m)) * 100) + '%'"></span></p>
                            <p class="mt-3 text-sm text-slate-600" x-show="m === 1">Ditagih tiap bulan. Bisa berhenti kapan saja.</p>
                        @endif
                    </div>

                    @if($custom)
                        <a href="{{ $wa('Halo, saya mau minta penawaran paket Custom Yoka POS') }}" target="_blank" rel="noopener" class="mt-6 inline-flex w-fit rounded-full bg-[#0B1B3F] px-6 py-3 font-bold text-white transition hover:bg-[#13285a]">Minta penawaran</a>
                    @else
                        <a href="{{ route('register') }}" class="mt-6 inline-flex w-fit rounded-full bg-[#16A34A] px-6 py-3 font-bold text-white transition hover:bg-[#15803D]">Coba gratis 7 hari</a>
                    @endif

                    <p class="mt-6 border-t border-slate-200 pt-6 text-[15px] leading-relaxed text-slate-600">{{ $p['tag'] }}. {{ $p['outlets'] }} dan {{ $p['users'] }}.</p>
                    <ul class="mt-5 flex-1 space-y-3">
                        @foreach($p['features'] as $f)
                            <li class="flex gap-3 text-[15px] text-[#0B1B3F]"><span class="mt-0.5 shrink-0">{!! $check !!}</span>{{ $f }}</li>
                        @endforeach
                    </ul>
                    <a href="#bandingkan" class="mt-7 inline-flex items-center gap-1.5 text-sm font-bold text-[#0B1B3F] underline decoration-[#16A34A] decoration-2 underline-offset-4 hover:text-[#16A34A]">Bandingkan paket dan fitur</a>
                </article>
            @endforeach
        </div>
        <p class="mt-5 text-sm text-slate-500">Harga dalam Rupiah per bisnis. Pembayaran lewat Midtrans: QRIS, transfer bank, dan e-wallet.</p>
    </div>
</section>

{{-- ===== Penawaran bundling ===== --}}
<section class="px-5 pb-20 sm:px-6">
    <div class="mx-auto grid max-w-[1200px] items-center gap-8 overflow-hidden rounded-[28px] bg-[#0B1B3F] p-8 text-white sm:p-12 lg:grid-cols-[1.2fr_1fr] [&>*]:min-w-0">
        <div>
            <p class="text-sm font-bold uppercase tracking-[.14em] text-[#4ADE80]">Paket bundling, lebih hemat</p>
            <h2 class="mt-3 text-[32px] font-extrabold leading-[1.08] tracking-[-.03em] sm:text-[44px]">Aplikasi + perangkat kasir</h2>
            <p class="mt-3 text-[17px] text-slate-300">Mulai <span class="font-bold text-white">{{ $rp($bundleFrom) }}</span> untuk YokaBasic 12 bulan plus perangkat yang sudah kami tes.</p>
            <ul class="mt-6 space-y-2.5 text-[15px] text-slate-200">
                @foreach(['Printer struk, tablet, barcode scanner, laci kas, atau mesin kasir', 'Dipasang dan diajari sampai bisa jualan', 'Garansi tukar unit 30 hari'] as $f)
                    <li class="flex gap-3"><svg class="mt-0.5 h-5 w-5 shrink-0 text-[#4ADE80]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.6"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>{{ $f }}</li>
                @endforeach
            </ul>
            <a href="{{ route('pricing.bundles') }}" class="mt-8 inline-flex rounded-full bg-[#16A34A] px-6 py-3 font-bold text-white transition hover:bg-[#15803D]">Lihat paket bundling</a>
        </div>
        <div class="flex h-48 items-end justify-center gap-2 sm:h-56">
            <div class="min-w-0 shrink h-40 w-48 sm:h-52 sm:w-60"><x-device-art kind="tablet"/></div>
            <div class="min-w-0 shrink -ml-8 h-28 w-32 sm:h-36 sm:w-40"><x-device-art kind="printer"/></div>
            <div class="min-w-0 shrink -ml-4 h-36 w-20 sm:h-44 sm:w-24"><x-device-art kind="mpos"/></div>
        </div>
    </div>
</section>

{{-- ===== Bandingkan paket ===== --}}
<section id="bandingkan" class="scroll-mt-20 px-5 pb-20 sm:px-6">
    <div class="mx-auto max-w-[1200px]">
        <h2 class="text-center text-[32px] font-extrabold tracking-[-.03em] text-[#0B1B3F] sm:text-[44px]">Bandingkan paket</h2>

        <div class="mt-10 overflow-x-auto">
            <table class="w-full min-w-[760px] border-separate border-spacing-0 text-left">
                <thead class="bg-white">
                    <tr>
                        <th class="w-[34%] border-b border-slate-200 py-5 pr-4 align-bottom text-sm font-semibold text-slate-500"></th>
                        @foreach($cols as $s)
                            @php $p = $bySlug[$s] ?? null; @endphp
                            @if($p)
                            <th class="border-b border-slate-200 px-4 py-5 align-bottom" x-data="{ p: @js($p['periods']) }">
                                <p class="text-base font-bold text-[#0B1B3F]">{{ $p['name'] }}</p>
                                @if(! empty($p['custom']))
                                    <p class="mt-1 text-sm font-normal text-slate-600">Harga khusus</p>
                                    <a href="{{ $wa('Halo, saya mau minta penawaran paket Custom Yoka POS') }}" target="_blank" rel="noopener" class="mt-3 inline-flex rounded-full bg-[#0B1B3F] px-4 py-2 text-sm font-bold text-white">Minta penawaran</a>
                                @else
                                    <p class="mt-1 text-sm font-normal text-slate-600">Mulai <span x-text="fmt(price(p, m) / (p[m] ? m : 1))"></span>/bln</p>
                                    <a href="{{ route('register') }}" class="mt-3 inline-flex rounded-full bg-[#16A34A] px-4 py-2 text-sm font-bold text-white">Coba gratis</a>
                                @endif
                            </th>
                            @endif
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($groups as $group => $rows)
                        <tr><td colspan="4" class="bg-[#F3F6F4] px-4 py-3 text-sm font-extrabold uppercase tracking-[.08em] text-[#0B1B3F]">{{ $group }}</td></tr>
                        @foreach($rows as [$label, $hint, $a, $b, $c])
                            <tr>
                                <td class="border-b border-slate-100 py-4 pr-4">
                                    <p class="text-[15px] font-semibold text-[#0B1B3F]">{{ $label }}</p>
                                    @if($hint)<p class="mt-0.5 text-sm text-slate-500">{{ $hint }}</p>@endif
                                </td>
                                @foreach([$a, $b, $c] as $v)
                                    <td class="border-b border-slate-100 px-4 py-4 text-[15px] text-[#0B1B3F]">
                                        @if($v === true){!! $check !!}@elseif($v === false){!! $no !!}@else{{ $v }}@endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>

{{-- ===== Satu aplikasi untuk seluruh usaha ===== --}}
<section class="bg-[#F3F6F4] px-5 py-20 sm:px-6">
    <div class="mx-auto max-w-[1200px]">
        <h2 class="max-w-[720px] text-[32px] font-extrabold leading-[1.08] tracking-[-.03em] text-[#0B1B3F] sm:text-[44px]">Satu aplikasi untuk menjalankan seluruh usahamu</h2>
        <p class="mt-4 max-w-[640px] text-[17px] text-slate-600">Dari kasir sampai laporan harian. Semua fitur saling terhubung, jadi tidak perlu banyak aplikasi.</p>
        <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach([
                ['Kasir', 'Transaksi cepat, QRIS, dan struk.', route('features.show', 'transaksi-kasir')],
                ['Tanya Yoka', 'Asisten AI yang membaca data tokomu.', route('landing').'#yoka-intelligence'],
                ['Stok & resep', 'Bahan terpotong otomatis setiap menu terjual.', route('features.show', 'stok-resep')],
                ['Layar dapur', 'Pesanan langsung muncul di dapur.', route('features.show', 'kitchen-display')],
            ] as [$t, $d, $href])
                <a href="{{ $href }}" class="group flex flex-col rounded-[24px] bg-white p-6 transition hover:shadow-[0_20px_45px_-25px_rgba(11,27,63,.35)]">
                    <p class="text-lg font-bold text-[#0B1B3F]">{{ $t }}</p>
                    <p class="mt-2 flex-1 text-[15px] text-slate-600">{{ $d }}</p>
                    <span class="mt-5 text-sm font-bold text-[#16A34A] transition group-hover:translate-x-1">Pelajari &rarr;</span>
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- ===== Tambahan ===== --}}
<section class="px-5 py-20 sm:px-6">
    <div class="mx-auto max-w-[1200px]">
        <h2 class="text-[32px] font-extrabold tracking-[-.03em] text-[#0B1B3F] sm:text-[44px]">Tambahan sesuai kebutuhan</h2>
        <p class="mt-3 text-[17px] text-slate-600">Tidak ada biaya wajib di luar paket. Ambil hanya yang kamu perlukan.</p>
        <div class="mt-10 grid gap-x-8 gap-y-10 sm:grid-cols-2 lg:grid-cols-4">
            @foreach(array_filter([
                ['Perangkat kasir', 'Printer, tablet, scanner, laci kas, atau mesin kasir yang sudah dites.', 'Lihat bundling', route('pricing.bundles')],
                $credits ? ['Kredit Yoka AI', 'Mulai '.$rp(collect($creditPacks)->min('price')).' untuk '.collect($creditPacks)->min('credits').' kredit. Berlaku 12 bulan.', null, null] : null,
                ['Kunjungan setup', 'Rp150.000 untuk paket 1 dan 3 bulan. Gratis untuk paket 6 dan 12 bulan.', null, null],
                ['Input menu', 'Kami bantu input menu di atas 50 item, Rp50.000 per 50 item.', null, null],
                ['Sesi pelatihan tambahan', 'Google Meet Rp100.000 per jam untuk tim barumu.', null, null],
            ]) as [$t, $d, $cta, $href])
                <div class="border-t-2 border-[#0B1B3F] pt-5">
                    <p class="text-lg font-bold text-[#0B1B3F]">{{ $t }}</p>
                    <p class="mt-2 text-[15px] leading-relaxed text-slate-600">{{ $d }}</p>
                    @if($cta)<a href="{{ $href }}" class="mt-4 inline-block text-sm font-bold text-[#0B1B3F] underline decoration-[#16A34A] decoration-2 underline-offset-4">{{ $cta }}</a>@endif
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ===== Tanya jawab ===== --}}
<section class="px-5 pb-20 sm:px-6" x-data="{ open: 0 }">
    <div class="mx-auto max-w-[1200px] lg:grid lg:grid-cols-[1fr_2fr] lg:gap-16">
        <h2 class="text-[32px] font-extrabold leading-[1.08] tracking-[-.03em] text-[#0B1B3F] sm:text-[44px]">Pertanyaan soal harga</h2>
        <div class="mt-8 divide-y divide-slate-200 border-y border-slate-200 lg:mt-0">
            @foreach([
                ['Apakah ada biaya di awal?', 'Tidak ada biaya pemasangan. Daftar, isi menu, dan langsung bisa transaksi. Biaya hanya paket yang kamu pilih, plus perangkat kalau kamu butuh.'],
                ['Bisa pakai HP atau tablet sendiri?', 'Bisa. Yoka jalan di browser HP, tablet, dan laptop. Untuk cetak struk, printer Bluetooth atau USB biasa sudah cukup.'],
                ['Kalau bayar 12 bulan, paketnya aktif sampai kapan?', 'Aktif 12 bulan sejak pembayaran. Kalau masih ada sisa masa aktif, perpanjangan ditambahkan setelahnya.'],
                ['Bisa ganti paket di tengah jalan?', 'Bisa. Pilih paket baru di halaman Langganan, data dan transaksi tetap tersimpan.'],
                ['Apakah bantuan termasuk di harga?', 'Ya. Onboarding Google Meet gratis untuk semua paket, dan tim Yoka bisa dihubungi lewat WhatsApp.'],
                ['Metode pembayarannya apa saja?', 'Lewat Midtrans: QRIS, transfer bank (virtual account), dan e-wallet yang tersedia di halaman pembayaran.'],
            ] as $i => [$q, $a])
                <div>
                    <button type="button" @click="open = open === {{ $i }} ? null : {{ $i }}" class="flex w-full items-center justify-between gap-4 py-5 text-left text-[17px] font-bold text-[#0B1B3F]">
                        <span>{{ $q }}</span>
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-slate-300 text-lg leading-none" x-text="open === {{ $i }} ? '−' : '+'"></span>
                    </button>
                    <div x-show="open === {{ $i }}" x-cloak class="pb-5 pr-12 text-[15px] leading-relaxed text-slate-600">{{ $a }}</div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ===== Penutup ===== --}}
<section class="bg-[#16A34A] px-5 py-16 text-white sm:px-6">
    <div class="mx-auto flex max-w-[1200px] flex-col items-start justify-between gap-6 md:flex-row md:items-center">
        <div>
            <h2 class="text-[30px] font-extrabold leading-tight tracking-[-.03em] sm:text-[40px]">Lihat Yoka bekerja di tokomu</h2>
            <p class="mt-2 text-[17px] text-green-50">Coba gratis 7 hari, atau minta demo langsung dari tim kami.</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('register') }}" class="rounded-full bg-white px-6 py-3 font-bold text-[#0B1B3F] transition hover:bg-green-50">Coba gratis</a>
            <a href="{{ $wa('Halo, saya mau jadwalkan demo Yoka POS') }}" target="_blank" rel="noopener" class="rounded-full border-2 border-white px-6 py-3 font-bold transition hover:bg-white/10">Jadwalkan demo</a>
        </div>
    </div>
</section>

</div>
@endsection
