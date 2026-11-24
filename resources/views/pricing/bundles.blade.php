@php
    $seoTitle = 'Paket Bundling Kasir Yoka POS: Aplikasi + Printer, Tablet, Mesin Kasir';
    $seoDescription = 'Paket kasir siap pakai: aplikasi Yoka POS '.$months.' bulan plus printer struk, tablet, barcode scanner, laci kas, atau mesin kasir Android. Dites dan dipasang tim Yoka.';
    $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
    $solidHeader = true;
    $check = '<svg class="h-5 w-5 text-[#16A34A]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.6"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';
    $featured = collect($bundles)->firstWhere('featured', true) ?? $bundles[0];
    $filters = ['semua' => 'Semua paket', 'hemat' => 'Mulai hemat', 'tablet' => 'Pakai tablet', 'mesin' => 'Mesin kasir', 'lengkap' => 'Paling lengkap'];
    $compareDevices = collect($devices)->except('paper');
@endphp
@extends('layouts.site')

@section('content')
<div x-data="{ f: 'semua' }">

{{-- ===== Pembuka ===== --}}
<section class="px-5 pb-12 pt-[110px] sm:px-6 sm:pt-[130px] lg:pt-[150px]">
    <div class="mx-auto grid max-w-[1200px] items-center gap-10 lg:grid-cols-[1.05fr_1fr] [&>*]:min-w-0">
        <div>
            <p class="text-sm font-bold uppercase tracking-[.14em] text-[#16A34A]">Paket bundling</p>
            <h1 class="mt-3 text-[38px] font-extrabold leading-[1.04] tracking-[-.035em] text-[#0B1B3F] sm:text-[52px] lg:text-[60px]">Kasir lengkap, tinggal pakai</h1>
            <p class="mt-5 max-w-[520px] text-[17px] leading-relaxed text-slate-600">Aplikasi Yoka POS {{ $months }} bulan plus perangkat yang sudah kami tes. Dipasang, diajari, dan bergaransi tukar.</p>
            <ul class="mt-6 flex flex-wrap gap-x-6 gap-y-2 text-[15px] font-semibold text-slate-700">
                @foreach(['Sudah termasuk aplikasi '.$months.' bulan', 'Dites dengan Yoka', 'Garansi tukar '.$warrantyDays.' hari'] as $b)
                    <li class="flex items-center gap-2">{!! $check !!}{{ $b }}</li>
                @endforeach
            </ul>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="#paket" class="rounded-full bg-[#16A34A] px-6 py-3 font-bold text-white transition hover:bg-[#15803D]">Pilih paket</a>
                <a href="{{ $waLink }}" target="_blank" rel="noopener" class="rounded-full border border-slate-300 px-6 py-3 font-bold text-[#0B1B3F] transition hover:border-[#0B1B3F]">Tanya dulu</a>
            </div>
        </div>
        {{-- Kolase produk --}}
        <div class="relative aspect-[5/4] w-full overflow-hidden rounded-[32px] bg-[#F3F6F4]">
            <div class="absolute -right-16 -top-16 h-64 w-64 rounded-full bg-[#16A34A]/10"></div>
            <div class="absolute -bottom-20 -left-10 h-56 w-56 rounded-full bg-[#0B1B3F]/5"></div>
            <div class="absolute left-[6%] top-[14%] w-[58%] drop-shadow-[0_30px_40px_rgba(11,27,63,.22)]"><x-device-art kind="tablet"/></div>
            <div class="absolute bottom-[8%] right-[6%] w-[22%] drop-shadow-[0_30px_40px_rgba(11,27,63,.25)]"><x-device-art kind="mpos"/></div>
            <div class="absolute bottom-[6%] left-[30%] w-[30%] drop-shadow-[0_24px_30px_rgba(11,27,63,.25)]"><x-device-art kind="printer"/></div>
            <div class="absolute right-[24%] top-[10%] w-[18%] drop-shadow-[0_20px_26px_rgba(11,27,63,.2)]"><x-device-art kind="scanner"/></div>
        </div>
    </div>
</section>

{{-- ===== Sorotan paket unggulan ===== --}}
<section class="px-5 pb-16 sm:px-6">
    <div class="mx-auto grid max-w-[1200px] items-center gap-8 overflow-hidden rounded-[32px] bg-[#0B1B3F] p-8 text-white sm:p-12 lg:grid-cols-[1fr_1.1fr] [&>*]:min-w-0">
        <div>
            <span class="inline-flex rounded-full bg-[#16A34A] px-3 py-1 text-xs font-bold">Paling pas untuk warung</span>
            <h2 class="mt-4 text-[32px] font-extrabold leading-[1.08] tracking-[-.03em] sm:text-[44px]">{{ $featured['name'] }}</h2>
            <p class="mt-3 text-[17px] text-slate-300">{{ $featured['for'] }}.</p>
            <div class="mt-6 flex flex-wrap items-end gap-x-4 gap-y-1">
                <p class="text-[44px] font-extrabold leading-none tracking-[-.03em]">{{ $rp($featured['price']) }}</p>
                @if($featured['saving'] > 0)<p class="pb-1 text-sm"><span class="text-slate-400 line-through">{{ $rp($featured['separate']) }}</span> <span class="ml-1 font-bold text-[#4ADE80]">hemat {{ $rp($featured['saving']) }}</span></p>@endif
            </div>
            <ul class="mt-6 grid gap-2.5 text-[15px] text-slate-200 sm:grid-cols-2">
                <li class="flex gap-2.5"><svg class="mt-0.5 h-5 w-5 shrink-0 text-[#4ADE80]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.6"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>Aplikasi YokaBasic {{ $months }} bulan</li>
                @foreach($featured['devices'] as $d)
                    <li class="flex gap-2.5"><svg class="mt-0.5 h-5 w-5 shrink-0 text-[#4ADE80]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.6"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>{{ $d['name'] }}</li>
                @endforeach
            </ul>
            <a href="{{ $featured['wa'] }}" target="_blank" rel="noopener" class="mt-8 inline-flex rounded-full bg-[#16A34A] px-6 py-3 font-bold text-white transition hover:bg-[#15803D]">Pesan paket ini</a>
        </div>
        <div class="relative h-64 sm:h-80">
            <div class="absolute inset-0 m-auto h-56 w-56 rounded-full bg-[#16A34A]/20 blur-2xl sm:h-72 sm:w-72"></div>
            @php $fd = collect($featured['devices'])->pluck('art')->all(); @endphp
            <div class="absolute left-[2%] top-[4%] w-[70%] drop-shadow-[0_30px_40px_rgba(0,0,0,.45)]"><x-device-art :kind="$fd[0] ?? 'tablet'"/></div>
            @if(in_array('stand', $fd, true))<div class="absolute bottom-[2%] left-[10%] w-[26%] drop-shadow-[0_20px_30px_rgba(0,0,0,.4)]"><x-device-art kind="stand"/></div>@endif
            @if(in_array('printer', $fd, true))<div class="absolute bottom-[0%] right-[4%] w-[40%] drop-shadow-[0_30px_40px_rgba(0,0,0,.45)]"><x-device-art kind="printer"/></div>@endif
        </div>
    </div>
</section>

{{-- ===== Semua paket + filter ===== --}}
<section id="paket" class="scroll-mt-24 px-5 pb-20 sm:px-6">
    <div class="mx-auto max-w-[1200px]">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-[32px] font-extrabold tracking-[-.03em] text-[#0B1B3F] sm:text-[44px]">Pilih paketmu</h2>
                <p class="mt-2 text-[17px] text-slate-600">Semua harga sudah termasuk aplikasi {{ $months }} bulan, pemasangan, dan garansi tukar.</p>
            </div>
            <div class="-mx-5 flex shrink-0 gap-2 overflow-x-auto px-5 pb-1 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0 lg:max-w-[560px] lg:justify-end" role="tablist" aria-label="Saring paket">
                @foreach($filters as $key => $label)
                    <button type="button" @click="f = '{{ $key }}'" class="shrink-0 rounded-full border px-4 py-2 text-sm font-bold transition"
                            :class="f === '{{ $key }}' ? 'border-[#0B1B3F] bg-[#0B1B3F] text-white' : 'border-slate-200 text-slate-600 hover:border-slate-400'">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <div class="mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            @foreach($bundles as $b)
                <article x-show="f === 'semua' || @js($b['groups']).includes(f)" x-transition.opacity
                         class="group flex flex-col overflow-hidden rounded-[28px] border border-slate-200 bg-white transition hover:-translate-y-1 hover:border-transparent hover:shadow-[0_30px_60px_-30px_rgba(11,27,63,.35)]">
                    <div class="relative flex h-52 items-end justify-center overflow-hidden bg-[#F3F6F4] px-5 pb-4 pt-8">
                        @if($b['saving'] > 0)<span class="absolute left-4 top-4 z-10 rounded-full bg-white px-2.5 py-1 text-xs font-bold text-[#15803D] shadow-sm">Hemat {{ $rp($b['saving']) }}</span>@endif
                        @php $arts = collect($b['devices'])->pluck('art')->reject(fn ($a) => $a === 'stand')->values(); @endphp
                        @foreach($arts->take(3) as $i => $a)
                            <div class="min-w-0 shrink transition duration-300 group-hover:scale-[1.04] {{ $arts->count() === 1 ? 'h-40 w-44' : ($arts->count() === 2 ? 'h-32 w-32' : 'h-24 w-24') }} {{ $i > 0 ? '-ml-4' : '' }} drop-shadow-[0_18px_22px_rgba(11,27,63,.22)]"><x-device-art :kind="$a"/></div>
                        @endforeach
                    </div>
                    <div class="flex flex-1 flex-col p-6">
                        <h3 class="text-lg font-bold text-[#0B1B3F]">{{ $b['name'] }}</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ $b['for'] }}</p>
                        <p class="mt-5 text-[28px] font-extrabold leading-none tracking-[-.02em] text-[#0B1B3F]">{{ $rp($b['price']) }}</p>
                        @if($b['saving'] > 0)<p class="mt-1.5 text-sm text-slate-400 line-through">{{ $rp($b['separate']) }}</p>@endif
                        <div class="mt-5 flex flex-1 flex-wrap content-start gap-1.5">
                            <span class="rounded-full bg-[#E8F5ED] px-2.5 py-1 text-xs font-semibold text-[#15803D]">Aplikasi {{ $months }} bulan</span>
                            @foreach($b['devices'] as $d)
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $d['name'] }}</span>
                            @endforeach
                        </div>
                        @if(! empty($b['note']))<p class="mt-3 text-xs text-slate-500">{{ $b['note'] }}</p>@endif
                        <a href="{{ $b['wa'] }}" target="_blank" rel="noopener" class="mt-6 block rounded-full bg-[#0B1B3F] px-4 py-3 text-center font-bold text-white transition group-hover:bg-[#16A34A]">Pesan paket ini</a>
                    </div>
                </article>
            @endforeach
        </div>
        <p class="mt-6 text-sm text-slate-500">Mau pakai paket Business? Tambah selisih harganya, tim kami bantu hitungkan. Ongkir ke luar Palembang sesuai tarif kurir.</p>
    </div>
</section>

{{-- ===== Bandingkan isi paket ===== --}}
<section class="bg-[#F3F6F4] px-5 py-20 sm:px-6">
    <div class="mx-auto max-w-[1200px]">
        <h2 class="text-center text-[32px] font-extrabold tracking-[-.03em] text-[#0B1B3F] sm:text-[44px]">Bandingkan isi paket</h2>
        <p class="mt-3 text-center text-[17px] text-slate-600">Semua paket sudah termasuk aplikasi YokaBasic {{ $months }} bulan.</p>
        <div class="mt-10 overflow-x-auto rounded-[24px] bg-white">
            <table class="w-full min-w-[980px] border-separate border-spacing-0 text-left text-[14px]">
                <thead>
                    <tr>
                        <th class="sticky left-0 z-10 w-56 border-b border-slate-100 bg-white px-5 py-5"></th>
                        @foreach($bundles as $b)
                            <th class="border-b border-slate-100 px-3 py-5 align-bottom">
                                <p class="font-bold leading-tight text-[#0B1B3F]">{{ str_replace('Paket ', '', $b['name']) }}</p>
                                <p class="mt-1 text-[13px] font-semibold text-[#15803D]">{{ $rp($b['price']) }}</p>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($compareDevices as $key => $d)
                        <tr>
                            <td class="sticky left-0 z-10 border-b border-slate-100 bg-white px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#F3F6F4] p-1"><x-device-art :kind="$d['art']"/></span>
                                    <span class="font-semibold text-[#0B1B3F]">{{ $d['name'] }}</span>
                                </div>
                            </td>
                            @foreach($bundles as $b)
                                <td class="border-b border-slate-100 px-3 py-3">@if(in_array($key, $b['items'], true)){!! $check !!}@else<span class="text-slate-300">&mdash;</span>@endif</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>

{{-- ===== Perangkat satuan ===== --}}
<section class="px-5 py-20 sm:px-6">
    <div class="mx-auto max-w-[1200px]">
        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
            <div>
                <h2 class="text-[32px] font-extrabold tracking-[-.03em] text-[#0B1B3F] sm:text-[44px]">Perangkat satuan</h2>
                <p class="mt-2 text-[17px] text-slate-600">Sudah langganan dan cuma butuh tambahan? Bisa beli satuan.</p>
            </div>
        </div>
        <div class="mt-10 grid grid-cols-2 gap-4 sm:gap-5 lg:grid-cols-4">
            @foreach($devices as $key => $d)
                <a href="{{ \App\Http\Controllers\PricingController::WA.rawurlencode('Halo, saya mau pesan '.$d['name'].' ('.$rp($d['price']).') dari yokapos.com/bundling') }}" target="_blank" rel="noopener"
                   class="group flex flex-col overflow-hidden rounded-[24px] border border-slate-200 bg-white transition hover:-translate-y-1 hover:shadow-[0_24px_50px_-28px_rgba(11,27,63,.35)]">
                    <div class="flex aspect-[4/3] items-center justify-center bg-[#F3F6F4] p-5">
                        <div class="h-full w-full transition duration-300 group-hover:scale-[1.05]"><x-device-art :kind="$d['art']"/></div>
                    </div>
                    <div class="flex flex-1 flex-col p-4 sm:p-5">
                        <p class="font-bold text-[#0B1B3F]">{{ $d['name'] }}</p>
                        <p class="mt-1 flex-1 text-[13px] leading-relaxed text-slate-500 sm:text-sm">{{ $d['spec'] }}</p>
                        <div class="mt-4 flex items-center justify-between gap-2">
                            <p class="font-extrabold text-[#0B1B3F]">{{ $rp($d['price']) }}</p>
                            <span class="text-sm font-bold text-[#16A34A]" aria-hidden="true">Pesan &rarr;</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- ===== Cara pesan ===== --}}
<section class="px-5 pb-20 sm:px-6">
    <div class="mx-auto max-w-[1200px] rounded-[32px] bg-[#0B1B3F] p-8 text-white sm:p-12">
        <h2 class="text-[30px] font-extrabold tracking-[-.03em] sm:text-[40px]">Dari pesan sampai jualan, kami temani</h2>
        <ol class="mt-10 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
            @foreach([
                ['Pilih paket', 'Tekan Pesan paket ini, pesanmu langsung masuk ke WhatsApp tim Yoka.'],
                ['Konfirmasi', 'Kami cek kebutuhan tokomu dan kirim rincian pembayaran.'],
                ['Kami siapkan', 'Perangkat dicek dan disambungkan dengan akun Yoka-mu.'],
                ['Mulai jualan', 'Kami datang untuk setup di Palembang, atau kirim paket dan dampingi lewat Google Meet.'],
            ] as $i => [$t, $d])
                <li class="relative border-t border-white/15 pt-6">
                    <span class="text-sm font-bold text-[#4ADE80]">0{{ $i + 1 }}</span>
                    <p class="mt-2 text-lg font-bold">{{ $t }}</p>
                    <p class="mt-1.5 text-[15px] leading-relaxed text-slate-300">{{ $d }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>

{{-- ===== Tanya jawab ===== --}}
<section class="px-5 pb-20 sm:px-6" x-data="{ open: 0 }">
    <div class="mx-auto max-w-[1200px] lg:grid lg:grid-cols-[1fr_2fr] lg:gap-16">
        <h2 class="text-[32px] font-extrabold leading-[1.08] tracking-[-.03em] text-[#0B1B3F] sm:text-[44px]">Pertanyaan soal bundling</h2>
        <div class="mt-8 divide-y divide-slate-200 border-y border-slate-200 lg:mt-0">
            @foreach([
                ['Saya sudah punya HP Android. Perlu tablet?', 'Tidak wajib. Yoka jalan di browser HP. Kalau butuh struk, Paket Printer sudah cukup.'],
                ['Perangkatnya jadi milik saya?', 'Ya. Perangkat jadi milikmu sepenuhnya, termasuk setelah masa langganan habis.'],
                ['Kalau perangkat rusak?', 'Dalam '.$warrantyDays.' hari pertama, unit bermasalah kami tukar. Setelah itu berlaku garansi dari produsen.'],
                ['Bisa dikirim ke luar Palembang?', 'Bisa. Ongkir sesuai tarif kurir, lalu setup dan pelatihan lewat Google Meet.'],
                ['Kenapa lebih mahal sedikit dari harga di marketplace?', 'Karena sudah termasuk aplikasi '.$months.' bulan, pemasangan, pengecekan dengan Yoka, dan garansi tukar dari kami. Kamu tidak perlu coba-coba sendiri.'],
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
            <h2 class="text-[30px] font-extrabold leading-tight tracking-[-.03em] sm:text-[40px]">Belum yakin paket mana?</h2>
            <p class="mt-2 text-[17px] text-green-50">Ceritakan usahamu, kami sarankan paket yang pas. Atau lihat <a href="{{ route('pricing') }}" class="font-bold underline underline-offset-4">harga langganan</a>.</p>
        </div>
        <a href="{{ $waLink }}" target="_blank" rel="noopener" class="rounded-full bg-white px-6 py-3 font-bold text-[#0B1B3F] transition hover:bg-green-50">Konsultasi gratis</a>
    </div>
</section>

</div>
@endsection
