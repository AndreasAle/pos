@extends('layouts.app')
@section('title','Pelanggan')
@section('page-title','Manajemen Pelanggan')

@section('content')
@php
    $rp     = fn($v) => 'Rp ' . number_format($v, 0, ',', '.');
    $star   = 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z';
    $trash  = 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16';
    $search = request()->filled('search');
@endphp

<div class="page text-[#0B1B3F] pt-2">

    <div class="fade-up flex flex-wrap items-end justify-between gap-2 mb-4" style="--i:0">
        <p class="text-sm text-[#64748B]">Data pelanggan, riwayat belanja, dan poin loyalitas.</p>
        <span class="inline-flex items-center gap-1.5 h-8 px-3 rounded-full bg-[#ECFDF3] text-[#15803D] text-sm font-semibold">
            <span class="w-1.5 h-1.5 rounded-full bg-[#16A34A]"></span>{{ number_format($customers->total()) }} pelanggan
        </span>
    </div>

    <section class="fade-up rounded-xl border border-[#E5E7EB] bg-white p-3 sm:p-4 mb-4" style="--i:1">
        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
            <form method="GET" class="flex flex-1 sm:max-w-md gap-2">
                <div class="relative min-w-0 flex-1">
                    <svg class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-[#64748B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama atau no. HP..."
                           class="h-10 w-full rounded-lg border border-[#E5E7EB] bg-white pl-10 pr-3 text-sm placeholder:text-slate-400 focus:outline-none focus:border-[#16A34A] focus:ring-2 focus:ring-[#16A34A]/30 transition">
                </div>
                <x-btn type="submit" variant="dark">Cari</x-btn>
                @if($search)
                <a href="{{ route('customers.index') }}" class="h-10 inline-flex items-center px-3 rounded-lg text-sm font-semibold text-[#64748B] hover:bg-slate-100 transition-colors">Reset</a>
                @endif
            </form>
            <x-btn :href="route('customers.create')" class="sm:ml-auto">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Tambah Pelanggan
            </x-btn>
        </div>
    </section>

    <section class="fade-up rounded-xl border border-[#E5E7EB] bg-white overflow-hidden" style="--i:2">
        @if($customers->count())
        {{-- Tabel (tablet & desktop) --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap">
                <thead>
                    <tr class="bg-slate-50 border-b border-[#E5E7EB]">
                        <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Pelanggan</th>
                        <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">No. HP</th>
                        <th class="text-right px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Transaksi</th>
                        <th class="text-right px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Total Belanja</th>
                        <th class="text-right px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Poin</th>
                        <th class="px-6 py-3.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E5E7EB]">
                    @foreach($customers as $c)
                    <tr class="group hover:bg-[#ECFDF3]/50 transition-colors cursor-pointer" onclick="window.location='{{ route('customers.show', $c) }}'">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-[#ECFDF3] text-sm font-bold text-[#16A34A]">{{ strtoupper(mb_substr($c->name, 0, 1)) }}</span>
                                <div class="min-w-0">
                                    <p class="font-semibold">{{ $c->name }}</p>
                                    @if($c->email)<p class="text-xs text-[#64748B]">{{ $c->email }}</p>@endif
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-slate-700">{{ $c->phone ?: '-' }}</td>
                        <td class="px-6 py-4 text-right font-semibold tabular-nums">{{ number_format($c->total_transactions) }}</td>
                        <td class="px-6 py-4 text-right font-bold tabular-nums text-[#15803D]">{{ $rp($c->total_spending) }}</td>
                        <td class="px-6 py-4 text-right">
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-200">
                                <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 24 24"><path d="{{ $star }}"/></svg>{{ number_format($c->loyalty_points) }}
                            </span>
                        </td>
                        <td class="px-6 py-4" onclick="event.stopPropagation()">
                            <div class="flex items-center justify-end gap-1.5">
                                <x-btn :href="route('customers.show', $c)" size="sm" variant="soft">Detail</x-btn>
                                <x-btn :href="route('customers.edit', $c)" size="sm" variant="secondary">Edit</x-btn>
                                <form method="POST" action="{{ route('customers.destroy', $c) }}" onsubmit="return confirm('Hapus pelanggan ini?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="flex h-8 w-8 items-center justify-center rounded-lg text-[#64748B] transition-colors hover:bg-red-50 hover:text-red-600" title="Hapus" aria-label="Hapus pelanggan">
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

        {{-- Daftar kartu (mobile) --}}
        <ul class="md:hidden divide-y divide-[#E5E7EB]">
            @foreach($customers as $c)
            <li>
                <a href="{{ route('customers.show', $c) }}" class="flex items-center gap-3 px-5 py-4 active:bg-[#ECFDF3]/60">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#ECFDF3] text-sm font-bold text-[#16A34A]">{{ strtoupper(mb_substr($c->name, 0, 1)) }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ $c->name }}</p>
                        <p class="text-xs text-[#64748B]">{{ $c->phone ?: 'Tanpa no. HP' }} &middot; {{ number_format($c->total_transactions) }} transaksi</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="font-bold tabular-nums text-[#15803D]">{{ $rp($c->total_spending) }}</p>
                        <p class="text-xs text-amber-700">{{ number_format($c->loyalty_points) }} poin</p>
                    </div>
                </a>
            </li>
            @endforeach
        </ul>
        @else
        <x-empty :title="$search ? 'Pelanggan tidak ditemukan' : 'Belum ada pelanggan'"
                 :text="$search ? 'Coba kata kunci lain.' : 'Simpan data pelanggan untuk melacak riwayat belanja dan poin loyalitas.'"
                 icon="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
            @if($search)<x-btn :href="route('customers.index')" variant="secondary">Reset pencarian</x-btn>
            @else<x-btn :href="route('customers.create')">Tambah pelanggan</x-btn>@endif
        </x-empty>
        @endif
    </section>

    <div class="mt-5">{{ $customers->links() }}</div>
</div>
@endsection
