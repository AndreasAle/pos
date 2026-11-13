@extends('layouts.app')
@section('title', 'Bundling Produk')
@section('page-title', 'Bundling Produk')

@section('content')
@php $rp = fn($v) => 'Rp ' . number_format($v, 0, ',', '.'); @endphp

<div class="page text-[#0B1B3F] pt-2">

    <div class="fade-up flex flex-wrap items-end justify-between gap-3 mb-4" style="--i:0">
        <div class="flex flex-wrap items-center gap-3">
            <p class="text-sm text-[#64748B]">Paket bundling yang tampil di kasir sebagai satu item.</p>
            <span class="inline-flex items-center gap-1.5 h-8 px-3 rounded-full bg-[#ECFDF3] text-[#15803D] text-sm font-semibold">
                <span class="w-1.5 h-1.5 rounded-full bg-[#16A34A]"></span>{{ number_format($bundles->count()) }} bundle
            </span>
        </div>
        <x-btn :href="route('bundles.create')">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Tambah Bundle
        </x-btn>
    </div>

    @if($bundles->isEmpty())
    <section class="fade-up rounded-xl border border-[#E5E7EB] bg-white" style="--i:1">
        <x-empty title="Belum ada bundle" text="Buat paket bundling untuk promosi atau set menu, misalnya Paket Hemat." icon="M12 6v6m0 0v6m0-6h6m-6 0H6M4 7l8-4 8 4v10l-8 4-8-4V7z">
            <x-btn :href="route('bundles.create')">Buat bundle pertama</x-btn>
        </x-empty>
    </section>
    @else
    <div class="grid grid-cols-1 md:grid-cols-2 2xl:grid-cols-3 gap-4">
        @foreach($bundles as $bundle)
        @php
            $normal = $bundle->items->sum(fn($i) => ($i->product->price ?? 0) * $i->qty);
            $save   = $normal > 0 && $normal > $bundle->price ? round(($normal - $bundle->price) / $normal * 100) : 0;
        @endphp
        <article class="fade-up flex flex-col overflow-hidden rounded-xl border border-[#E5E7EB] bg-white transition-shadow hover:shadow-md" style="--i:{{ min($loop->index, 6) + 1 }}">
            <div class="flex items-start justify-between gap-3 border-b border-[#E5E7EB] p-5">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="truncate text-base font-bold">{{ $bundle->name }}</h3>
                        <x-pill :tone="$bundle->is_active ? 'green' : 'gray'">{{ $bundle->is_active ? 'Aktif' : 'Nonaktif' }}</x-pill>
                    </div>
                    @if($bundle->description)<p class="mt-1 line-clamp-1 text-sm text-[#64748B]">{{ $bundle->description }}</p>@endif
                </div>
                <div class="shrink-0 text-right">
                    <p class="text-lg font-extrabold tabular-nums text-[#15803D]">{{ $rp($bundle->price) }}</p>
                    @if($save > 0)<p class="mt-0.5 text-xs font-semibold text-amber-600">Hemat {{ $save }}%</p>@endif
                </div>
            </div>

            <div class="flex-1 p-5">
                <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-[#64748B]">Isi Paket</p>
                <ul class="space-y-2">
                    @foreach($bundle->items as $item)
                    <li class="flex items-center justify-between gap-3 text-sm">
                        <span class="flex min-w-0 items-center gap-2">
                            <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-[#16A34A]"></span>
                            <span class="truncate">{{ optional($item->product)->name ?? '(produk dihapus)' }}</span>
                        </span>
                        <span class="shrink-0 rounded-md bg-slate-100 px-2 py-0.5 text-xs font-bold tabular-nums">&times; {{ rtrim(rtrim(number_format($item->qty, 3, '.', ''), '0'), '.') }}</span>
                    </li>
                    @endforeach
                </ul>
            </div>

            <div class="flex items-center justify-between gap-2 border-t border-[#E5E7EB] bg-slate-50/70 px-5 py-3">
                <form method="POST" action="{{ route('bundles.toggle', $bundle) }}">
                    @csrf @method('PATCH')
                    <x-btn type="submit" size="sm" :variant="$bundle->is_active ? 'secondary' : 'soft'">{{ $bundle->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</x-btn>
                </form>
                <div class="flex items-center gap-2">
                    <x-btn :href="route('bundles.edit', $bundle)" size="sm" variant="secondary">Edit</x-btn>
                    <form method="POST" action="{{ route('bundles.destroy', $bundle) }}" onsubmit="return confirm('Hapus bundle {{ addslashes($bundle->name) }}?')">
                        @csrf @method('DELETE')
                        <x-btn type="submit" size="sm" variant="danger-outline">Hapus</x-btn>
                    </form>
                </div>
            </div>
        </article>
        @endforeach
    </div>
    @endif
</div>
@endsection
