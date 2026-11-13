@extends('layouts.app')
@section('title','Integrasi')
@section('page-title','Integrasi')

@section('content')
@php
    $statusTone  = ['pending' => 'amber', 'connected' => 'green', 'disconnected' => 'gray'];
    $statusLabel = ['pending' => 'Menunggu aktivasi', 'connected' => 'Tersambung', 'disconnected' => 'Terputus'];

    $all       = $links->flatten(1);
    $cntAll    = $all->count();
    $cntPend   = $all->where('status', 'pending')->count();
    $cntOk     = $all->where('status', 'connected')->count();

    // Logo asli di public/images/integrations/. Untuk kotak kecil dipakai {key}-icon
    // (ikon saja, tanpa tulisan) bila ada, kalau tidak {key}.
    $logo = function (string $key) {
        foreach (["{$key}-icon", $key] as $name) {
            foreach (['svg', 'png', 'webp', 'jpg'] as $ext) {
                if (file_exists(public_path("images/integrations/{$name}.{$ext}"))) {
                    return asset("images/integrations/{$name}.{$ext}");
                }
            }
        }
        return null;
    };

    $benefits = [
        'Menu dan harga tersinkron otomatis',
        'Pesanan masuk langsung ke kasir dan dapur',
        'Laporan penjualan online dan offline satu tempat',
    ];

    $steps = [
        ['Pilih platform', 'GoFood atau ShopeeFood.'],
        ['Isi ID dan setujui', 'Pilih outlet, isi ID merchant, lalu setujui.'],
        ['Aktivasi oleh Yoka', 'Tim Yoka menyambungkan, status berubah jadi Tersambung.'],
    ];
    $arrow = 'M13 7l5 5m0 0l-5 5m5-5H6';
@endphp

<div class="page text-[#0B1B3F] pt-2" x-data="{ open: null }">

    {{-- Hero --}}
    <section class="fade-up relative overflow-hidden rounded-xl border border-[#BBF7D0] bg-gradient-to-br from-[#ECFDF3] via-white to-white p-5 sm:p-8" style="--i:0">
        <div class="grid grid-cols-1 items-center gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="min-w-0">
                <span class="inline-flex h-8 items-center gap-1.5 rounded-full border border-[#E5E7EB] bg-white px-3 text-sm font-semibold text-[#64748B]">
                    <span class="h-1.5 w-1.5 rounded-full {{ $cntOk ? 'bg-[#16A34A]' : 'bg-slate-400' }}"></span>
                    {{ $cntOk ? $cntOk . ' outlet tersambung' : 'Belum ada yang tersambung' }}
                </span>
                <h2 class="mt-4 text-2xl font-semibold sm:text-[28px] sm:leading-9">Terima pesanan online langsung di kasir</h2>
                <p class="mt-2 max-w-xl text-[15px] text-[#64748B]">Hubungkan outlet ke GoFood dan ShopeeFood. Menu tersinkron, pesanan masuk ke Yoka POS tanpa input ulang, dan semua penjualan tercatat di satu laporan.</p>

                <ol class="mt-6 grid max-w-2xl grid-cols-1 gap-3 sm:grid-cols-3">
                    @foreach($steps as $i => [$t, $d])
                    <li class="flex items-start gap-3 rounded-lg border border-[#E5E7EB] bg-white/80 p-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#16A34A] text-xs font-bold text-white">{{ $i + 1 }}</span>
                        <span class="min-w-0"><span class="block text-sm font-semibold leading-tight">{{ $t }}</span><span class="mt-0.5 block text-[13px] text-[#64748B]">{{ $d }}</span></span>
                    </li>
                    @endforeach
                </ol>
            </div>

            {{-- Ilustrasi alur: Yoka -> platform --}}
            <div class="hidden justify-center lg:flex" aria-hidden="true">
                <div class="float w-full max-w-[300px] rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-lg">
                    <div class="flex items-center justify-between">
                        <img src="{{ asset('images/brand/yoka-icon.png') }}" alt="" class="h-11 w-11 rounded-xl border border-[#E5E7EB] object-contain p-1.5">
                        <svg class="h-5 w-5 text-[#16A34A]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4M16 17H4m0 0l4-4m-4 4l4 4"/></svg>
                        <div class="flex -space-x-2">
                            @foreach($providers as $key => $p)
                            <span class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-xl border border-[#E5E7EB] bg-white">
                                @if($src = $logo($key))<img src="{{ $src }}" alt="" class="h-full w-full object-contain p-1.5">@else<span class="text-sm font-bold text-[#64748B]">{{ substr($p['name'], 0, 1) }}</span>@endif
                            </span>
                            @endforeach
                        </div>
                    </div>
                    <div class="mt-4 space-y-2 text-[13px]">
                        <div class="pop-in rounded-lg bg-[#ECFDF3] px-3 py-2" style="--d:0"><span class="font-semibold text-[#15803D]">Pesanan baru</span><span class="block text-[#64748B]">2x Es Kopi Susu, 1x Roti Bakar</span></div>
                        <div class="pop-in rounded-lg bg-slate-50 px-3 py-2" style="--d:1.2"><span class="font-semibold">Menu tersinkron</span><span class="block text-[#64748B]">24 item, harga per platform</span></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Ringkasan --}}
    <div class="fade-up mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3" style="--i:1">
        <x-kpi label="Outlet terdaftar" :value="number_format($cntAll)" sub="di semua platform" />
        <x-kpi label="Menunggu aktivasi" :value="number_format($cntPend)" sub="diproses tim Yoka" :tone="$cntPend ? 'warn' : 'default'" />
        <x-kpi label="Tersambung" :value="number_format($cntOk)" sub="pesanan masuk otomatis" :tone="$cntOk ? 'brand' : 'default'" />
    </div>

    @if($outlets->isEmpty())
    <x-panel class="fade-up mt-5" style="--i:2">
        <x-empty title="Belum ada outlet" text="Tambahkan outlet dulu sebelum menghubungkan GoFood atau ShopeeFood.">
            <x-btn :href="route('outlets.create')">Tambah Outlet</x-btn>
        </x-empty>
    </x-panel>
    @else

    {{-- Platform --}}
    <section class="mt-5 grid grid-cols-1 gap-4 lg:grid-cols-2">
        @foreach($providers as $key => $p)
        @php $rows = $links->get($key, collect()); $src = $logo($key); @endphp
        <article class="fade-up flex min-w-0 flex-col rounded-xl border border-[#E5E7EB] bg-white transition-shadow hover:shadow-md" style="--i:{{ $loop->index + 2 }}">
            <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-3 p-5">
                <div class="flex items-center gap-3.5">
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-[#E5E7EB] bg-white">
                        @if($src)<img src="{{ $src }}" alt="{{ $p['name'] }}" class="h-full w-full object-contain p-2">@else<span class="text-lg font-bold text-[#64748B]">{{ substr($p['name'], 0, 1) }}</span>@endif
                    </span>
                    <div>
                        <h3 class="whitespace-nowrap text-lg font-semibold">{{ $p['name'] }}</h3>
                        <p class="whitespace-nowrap text-sm text-[#64748B]">Pesan antar online</p>
                    </div>
                </div>
                <x-pill class="whitespace-nowrap" :tone="$rows->count() ? 'green' : 'gray'">{{ $rows->count() ? $rows->count() . ' outlet' : 'Belum dihubungkan' }}</x-pill>
            </div>

            <ul class="space-y-2 px-5 pb-5">
                @foreach($benefits as $b)
                <li class="flex items-start gap-2 text-sm text-[#64748B]">
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-[#16A34A]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>{{ $b }}
                </li>
                @endforeach
            </ul>

            @if($rows->count())
            <div class="divide-y divide-[#E5E7EB] border-t border-[#E5E7EB]">
                @foreach($rows as $row)
                <div class="flex flex-wrap items-center justify-between gap-2 px-5 py-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold">{{ $row->outlet->name ?? '-' }}</p>
                        <p class="truncate text-[13px] text-[#64748B]">{{ $row->merchant_name ? $row->merchant_name . ' · ' : '' }}<span class="font-mono">{{ $row->merchant_id }}</span></p>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-pill :tone="$statusTone[$row->status] ?? 'gray'">{{ $statusLabel[$row->status] ?? $row->status }}</x-pill>
                        <form method="POST" action="{{ route('integrations.disconnect', $row) }}" onsubmit="return confirm('Putuskan integrasi ini?')">
                            @csrf @method('DELETE')
                            <x-btn type="submit" size="sm" variant="danger-outline">Putuskan</x-btn>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
            @endif

            <div class="mt-auto border-t border-[#E5E7EB] p-4">
                <button type="button" @click="open = '{{ $key }}'"
                        class="group inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-[#16A34A] text-sm font-semibold text-white shadow-sm transition-colors hover:bg-[#15803D]">
                    {{ $rows->count() ? 'Hubungkan outlet lain' : 'Hubungkan ' . $p['name'] }}
                    <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $arrow }}"/></svg>
                </button>
            </div>
        </article>
        @endforeach
    </section>

    <p class="fade-up mt-4 text-[13px] text-[#64748B]" style="--i:4">Sambungan data langsung ke platform diaktifkan oleh tim Yoka setelah izin partner dari GoFood dan ShopeeFood keluar. Sampai saat itu, outlet yang didaftarkan berstatus menunggu aktivasi.</p>

    {{-- Modal persetujuan dan penautan outlet --}}
    @foreach($providers as $key => $p)
    <div x-show="open === '{{ $key }}'" x-cloak x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B1B3F]/50 p-4"
         @keydown.escape.window="open = null" @click.self="open = null">
        <form method="POST" action="{{ route('integrations.connect', $key) }}"
              class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-xl border border-[#E5E7EB] bg-white shadow-xl">
            @csrf
            <div class="flex items-center gap-3 border-b border-[#E5E7EB] px-5 py-4">
                <span class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-lg border border-[#E5E7EB]">
                    @if($src = $logo($key))<img src="{{ $src }}" alt="" class="h-full w-full object-contain p-1.5">@else<span class="text-sm font-bold text-[#64748B]">{{ substr($p['name'], 0, 1) }}</span>@endif
                </span>
                <h3 class="text-base font-semibold">Hubungkan Yoka dengan {{ $p['name'] }}</h3>
            </div>

            <div class="space-y-4 p-5">
                <div>
                    <label class="mb-1 block text-sm font-semibold">Outlet Yoka</label>
                    <select name="outlet_id" required class="h-10 w-full rounded-lg border border-[#E5E7EB] bg-white px-3 text-sm focus:border-[#16A34A] focus:outline-none focus:ring-2 focus:ring-[#16A34A]/20">
                        <option value="">Pilih outlet</option>
                        @foreach($outlets as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold">{{ $p['id_label'] }}</label>
                    <input name="merchant_id" required maxlength="100" placeholder="{{ $p['id_hint'] }}" class="h-10 w-full rounded-lg border border-[#E5E7EB] px-3 text-sm focus:border-[#16A34A] focus:outline-none focus:ring-2 focus:ring-[#16A34A]/20">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold">Nama toko di {{ $p['name'] }} <span class="font-normal text-[#64748B]">(opsional)</span></label>
                    <input name="merchant_name" maxlength="150" class="h-10 w-full rounded-lg border border-[#E5E7EB] px-3 text-sm focus:border-[#16A34A] focus:outline-none focus:ring-2 focus:ring-[#16A34A]/20">
                </div>

                <div class="rounded-lg border border-[#E5E7EB] bg-slate-50 p-3">
                    <p class="text-sm font-semibold">Saya mengizinkan Yoka mengirim dan menerima:</p>
                    <ul class="mt-2 space-y-1.5 text-[13px]">
                        <li><span class="font-semibold">Library menu.</span> <span class="text-[#64748B]">Item, kategori, diskon, modifier, dan pajak.</span></li>
                        <li><span class="font-semibold">Akses pesanan.</span> <span class="text-[#64748B]">Pesanan platform dibuat, dibaca, dan diperbarui di Yoka POS.</span></li>
                        <li><span class="font-semibold">Harga per tipe penjualan.</span> <span class="text-[#64748B]">Harga di platform boleh berbeda dari kasir.</span></li>
                    </ul>
                </div>

                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="consent" value="1" required class="mt-0.5 h-4 w-4 rounded border-[#E5E7EB] accent-[#16A34A]">
                    <span>Saya setuju data di atas dibagikan ke {{ $p['name'] }}.</span>
                </label>
            </div>

            <div class="flex justify-end gap-2 border-t border-[#E5E7EB] px-5 py-3">
                <x-btn variant="secondary" @click="open = null">Batalkan</x-btn>
                <x-btn type="submit">Setuju dan hubungkan</x-btn>
            </div>
        </form>
    </div>
    @endforeach
    @endif
</div>
@endsection
