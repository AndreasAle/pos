@extends('layouts.app')
@section('title','Promo & Diskon')
@section('page-title','Promo & Diskon')

@section('content')
@php
    $rp    = fn($v) => 'Rp ' . number_format($v, 0, ',', '.');
    $trash = 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16';
    $typeTone  = ['percent' => 'blue', 'nominal' => 'green', 'buy_get' => 'amber'];
    $typeText  = fn($p) => match($p->type) {
        'percent' => 'Diskon persen',
        'buy_get' => $p->category ? 'Kategori ' . $p->category->name : 'Semua produk',
        default   => 'Diskon nominal',
    };
@endphp

<div class="page text-[#0B1B3F] pt-2">

    <div class="fade-up flex flex-wrap items-end justify-between gap-3 mb-4" style="--i:0">
        <div class="flex flex-wrap items-center gap-3">
            <p class="text-sm text-[#64748B]">Diskon persen, nominal, dan promo beli gratis untuk kasir.</p>
            <span class="inline-flex items-center gap-1.5 h-8 px-3 rounded-full bg-[#ECFDF3] text-[#15803D] text-sm font-semibold">
                <span class="w-1.5 h-1.5 rounded-full bg-[#16A34A]"></span>{{ number_format($promotions->total()) }} promo
            </span>
        </div>
        <x-btn :href="route('promotions.create')">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Tambah Promo
        </x-btn>
    </div>

    <section class="fade-up rounded-xl border border-[#E5E7EB] bg-white overflow-hidden" style="--i:1">
        @if($promotions->count())
        {{-- Tabel (desktop) --}}
        <div class="hidden lg:block overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap">
                <thead>
                    <tr class="bg-slate-50 border-b border-[#E5E7EB]">
                        <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Promo</th>
                        <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Nilai</th>
                        <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Min. Order</th>
                        <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Periode</th>
                        <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Outlet</th>
                        <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E5E7EB]">
                    @foreach($promotions as $promo)
                    <tr class="hover:bg-[#ECFDF3]/50 transition-colors">
                        <td class="px-6 py-4">
                            <p class="font-semibold">{{ $promo->name }}</p>
                            @if($promo->code)<p class="mt-0.5 font-mono text-xs text-[#64748B]">Kode: {{ $promo->code }}</p>@endif
                        </td>
                        <td class="px-6 py-4">
                            <x-pill :tone="$typeTone[$promo->type] ?? 'gray'">{{ $promo->label() }}</x-pill>
                            <p class="mt-1 text-xs text-[#64748B]">{{ $typeText($promo) }}</p>
                        </td>
                        <td class="px-6 py-4 tabular-nums text-slate-700">{{ $promo->min_order > 0 ? $rp($promo->min_order) : '-' }}</td>
                        <td class="px-6 py-4 text-xs text-slate-700">
                            @if($promo->starts_at || $promo->ends_at)
                                {{ $promo->starts_at?->format('d/m/Y') ?? '...' }} &ndash; {{ $promo->ends_at?->format('d/m/Y') ?? '...' }}
                            @else
                                <span class="text-[#64748B]">Tanpa batas</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-slate-700">{{ $promo->outlet?->name ?? 'Semua outlet' }}</td>
                        <td class="px-6 py-4"><x-pill :tone="$promo->is_active ? 'green' : 'gray'">{{ $promo->is_active ? 'Aktif' : 'Nonaktif' }}</x-pill></td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-end gap-1.5">
                                <form method="POST" action="{{ route('promotions.toggle', $promo) }}">
                                    @csrf @method('PATCH')
                                    <x-btn type="submit" size="sm" :variant="$promo->is_active ? 'secondary' : 'soft'">{{ $promo->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</x-btn>
                                </form>
                                <x-btn :href="route('promotions.edit', $promo)" size="sm" variant="secondary">Edit</x-btn>
                                <form method="POST" action="{{ route('promotions.destroy', $promo) }}" onsubmit="return confirm('Hapus promo ini?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="flex h-8 w-8 items-center justify-center rounded-lg text-[#64748B] transition-colors hover:bg-red-50 hover:text-red-600" title="Hapus" aria-label="Hapus promo">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $trash }}"/></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Daftar kartu (mobile & tablet) --}}
        <ul class="lg:hidden divide-y divide-[#E5E7EB]">
            @foreach($promotions as $promo)
            <li class="p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-semibold">{{ $promo->name }}</p>
                        @if($promo->code)<p class="mt-0.5 font-mono text-xs text-[#64748B]">Kode: {{ $promo->code }}</p>@endif
                    </div>
                    <x-pill :tone="$promo->is_active ? 'green' : 'gray'">{{ $promo->is_active ? 'Aktif' : 'Nonaktif' }}</x-pill>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-[#64748B]">
                    <x-pill :tone="$typeTone[$promo->type] ?? 'gray'">{{ $promo->label() }}</x-pill>
                    <span>{{ $typeText($promo) }}</span>
                    <span>&middot; {{ $promo->outlet?->name ?? 'Semua outlet' }}</span>
                    @if($promo->min_order > 0)<span>&middot; min. {{ $rp($promo->min_order) }}</span>@endif
                </div>
                <div class="mt-4 flex items-center gap-2">
                    <form method="POST" action="{{ route('promotions.toggle', $promo) }}">
                        @csrf @method('PATCH')
                        <x-btn type="submit" size="sm" :variant="$promo->is_active ? 'secondary' : 'soft'">{{ $promo->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</x-btn>
                    </form>
                    <x-btn :href="route('promotions.edit', $promo)" size="sm" variant="secondary">Edit</x-btn>
                    <form method="POST" action="{{ route('promotions.destroy', $promo) }}" onsubmit="return confirm('Hapus promo ini?')">
                        @csrf @method('DELETE')
                        <x-btn type="submit" size="sm" variant="danger-outline">Hapus</x-btn>
                    </form>
                </div>
            </li>
            @endforeach
        </ul>
        @else
        <x-empty title="Belum ada promo" text="Buat promo pertama untuk menarik pelanggan, misalnya diskon weekend atau beli 1 gratis 1." icon="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z">
            <x-btn :href="route('promotions.create')">Buat promo pertama</x-btn>
        </x-empty>
        @endif
    </section>

    <div class="mt-5">{{ $promotions->links() }}</div>
</div>
@endsection
