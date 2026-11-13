@extends('layouts.app')
@section('title', 'Kategori Menu')
@section('page-title', 'Kategori Menu')

@section('content')
@php
    $flame = 'M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z M9.879 16.121A3 3 0 1012.015 11L11 14H9c0 .768.293 1.536.879 2.121z';
    $trash = 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16';
@endphp

<div class="page text-[#0B1B3F] pt-2">

    <div class="fade-up flex flex-wrap items-end justify-between gap-2 mb-4" style="--i:0">
        <p class="text-sm text-[#64748B]">Kelompokkan menu dan atur kategori mana yang dicetak ke dapur.</p>
        <span class="inline-flex items-center gap-1.5 h-8 px-3 rounded-full bg-[#ECFDF3] text-[#15803D] text-sm font-semibold">
            <span class="w-1.5 h-1.5 rounded-full bg-[#16A34A]"></span>{{ count($categories) }} kategori
        </span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[360px_minmax(0,1fr)] gap-4 items-start">

        {{-- Form tambah --}}
        <x-panel title="Tambah Kategori" sub="Kategori baru langsung tampil di kasir." class="fade-up lg:sticky lg:top-20" style="--i:1">
            <form method="POST" action="{{ route('categories.store') }}" class="space-y-5">
                @csrf
                <x-field label="Nama Kategori" name="name" :required="true">
                    <x-input name="name" :value="old('name')" required placeholder="Contoh: Minuman, Makanan Utama" />
                </x-field>

                <x-field label="Warna Label" name="color" hint="Warna penanda kategori di kasir.">
                    <div class="flex items-center gap-3">
                        <input type="color" id="color" name="color" value="{{ old('color', '#16A34A') }}"
                               class="h-11 w-16 cursor-pointer rounded-lg border border-[#E5E7EB] bg-white p-1">
                        <span class="text-sm font-mono text-[#64748B]">{{ old('color', '#16A34A') }}</span>
                    </div>
                </x-field>

                <x-switch name="send_to_kitchen" label="Kirim ke dapur" desc="Produk kategori ini ikut dicetak di tiket dapur. Aktifkan untuk makanan, kosongkan untuk minuman." :hidden="false" :checked="(bool) old('send_to_kitchen')" />

                <x-btn type="submit" size="lg" class="w-full">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Tambah Kategori
                </x-btn>
            </form>
        </x-panel>

        {{-- Daftar --}}
        <section class="fade-up min-w-0 overflow-hidden rounded-xl border border-[#E5E7EB] bg-white" style="--i:2">
            <div class="border-b border-[#E5E7EB] px-5 py-4">
                <h2 class="text-base font-semibold">Daftar Kategori</h2>
                <p class="mt-0.5 text-sm text-[#64748B]">Ubah nama dan warna langsung dari baris.</p>
            </div>

            @forelse($categories as $category)
            <div class="group border-b border-[#E5E7EB] last:border-0 px-5 py-4 transition-colors hover:bg-[#ECFDF3]/40" x-data="{ editing: false }">

                {{-- Tampilan baca --}}
                <div x-show="!editing" class="flex flex-wrap items-center gap-3">
                    <span class="h-9 w-9 shrink-0 rounded-lg ring-1 ring-inset ring-black/5" style="background-color: {{ $category->color ?? '#16A34A' }}"></span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold">{{ $category->name }}</p>
                        <p class="text-xs text-[#64748B]">{{ number_format($category->products_count) }} produk</p>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <form method="POST" action="{{ route('categories.kitchen', $category) }}">
                            @csrf @method('PATCH')
                            <button type="submit"
                                    title="{{ $category->send_to_kitchen ? 'Dicetak ke dapur, ketuk untuk berhenti' : 'Tidak dicetak ke dapur, ketuk untuk mengaktifkan' }}"
                                    class="inline-flex h-8 items-center gap-1.5 rounded-full px-3 text-xs font-semibold ring-1 ring-inset transition-colors
                                           {{ $category->send_to_kitchen ? 'bg-amber-50 text-amber-800 ring-amber-200 hover:bg-amber-100' : 'bg-slate-100 text-slate-500 ring-slate-200 hover:bg-slate-200' }}">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $flame }}"/></svg>
                                {{ $category->send_to_kitchen ? 'Dapur' : 'Bukan dapur' }}
                            </button>
                        </form>
                        <x-pill :tone="$category->is_active ? 'green' : 'gray'">{{ $category->is_active ? 'Aktif' : 'Nonaktif' }}</x-pill>
                        <x-btn size="sm" variant="secondary" @click="editing = true">Edit</x-btn>
                        @if($category->products_count == 0)
                        <form method="POST" action="{{ route('categories.destroy', $category) }}" onsubmit="return confirm('Hapus kategori ini?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="flex h-8 w-8 items-center justify-center rounded-lg text-[#64748B] transition-colors hover:bg-red-50 hover:text-red-600" title="Hapus kategori" aria-label="Hapus kategori">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $trash }}"/></svg>
                            </button>
                        </form>
                        @endif
                    </div>
                </div>

                {{-- Edit di tempat --}}
                <form x-show="editing" x-cloak method="POST" action="{{ route('categories.update', $category) }}" class="flex flex-wrap items-center gap-2.5">
                    @csrf @method('PUT')
                    <input type="color" name="color" value="{{ $category->color ?? '#16A34A' }}" aria-label="Warna"
                           class="h-10 w-12 shrink-0 cursor-pointer rounded-lg border border-[#E5E7EB] bg-white p-1">
                    <input type="text" name="name" value="{{ $category->name }}" required aria-label="Nama kategori"
                           class="h-10 min-w-0 flex-1 rounded-lg border border-[#E5E7EB] bg-white px-3.5 text-sm focus:outline-none focus:border-[#16A34A] focus:ring-2 focus:ring-[#16A34A]/30">
                    <input type="hidden" name="is_active" value="{{ $category->is_active ? '1' : '0' }}">
                    <x-btn type="submit" size="md">Simpan</x-btn>
                    <x-btn variant="ghost" @click="editing = false">Batal</x-btn>
                </form>
            </div>
            @empty
            <x-empty title="Belum ada kategori" text="Tambahkan kategori pertama lewat form di samping." icon="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
            @endforelse
        </section>
    </div>
</div>
@endsection
