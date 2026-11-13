@extends('layouts.app')
@section('title', 'Outlet')
@section('page-title', 'Manajemen Outlet')

@section('content')
@php
    $trash = 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16';
@endphp

<div class="page text-[#0B1B3F] pt-2">

    <div class="fade-up flex flex-wrap items-end justify-between gap-3 mb-4" style="--i:0">
        <div class="flex flex-wrap items-center gap-3">
            <p class="text-sm text-[#64748B]">Kelola semua outlet bisnis Anda.</p>
            <span class="inline-flex items-center gap-1.5 h-8 px-3 rounded-full bg-[#ECFDF3] text-[#15803D] text-sm font-semibold">
                <span class="w-1.5 h-1.5 rounded-full bg-[#16A34A]"></span>{{ number_format($outlets->total()) }} outlet
            </span>
        </div>
        <x-btn :href="route('outlets.create')">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Tambah Outlet
        </x-btn>
    </div>

    @if($outlets->count())
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach($outlets as $outlet)
        <article class="fade-up flex flex-col rounded-xl border border-[#E5E7EB] bg-white p-5 transition-shadow hover:shadow-md" style="--i:{{ min($loop->index, 6) + 1 }}">
            <div class="flex items-start justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-[#ECFDF3] text-[#16A34A]">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2zM9 22V12h6v10"/></svg>
                    </span>
                    <div class="min-w-0">
                        <p class="truncate font-semibold">{{ $outlet->name }}</p>
                        @if($outlet->code)<p class="font-mono text-xs text-[#64748B]">{{ $outlet->code }}</p>@endif
                    </div>
                </div>
                <x-pill :tone="$outlet->is_active ? 'green' : 'gray'">{{ $outlet->is_active ? 'Aktif' : 'Nonaktif' }}</x-pill>
            </div>

            <dl class="mt-4 flex-1 space-y-2 text-sm">
                @if($outlet->address)
                <div class="flex items-start gap-2 text-[#64748B]">
                    <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>{{ $outlet->address }}</span>
                </div>
                @endif
                @if($outlet->phone)
                <div class="flex items-center gap-2 text-[#64748B]">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h2.3a1 1 0 01.95.68l1.1 3.3a1 1 0 01-.5 1.2l-1.5.75a11 11 0 005.2 5.2l.75-1.5a1 1 0 011.2-.5l3.3 1.1a1 1 0 01.68.95V19a2 2 0 01-2 2A16 16 0 013 5z"/></svg>
                    {{ $outlet->phone }}
                </div>
                @endif
            </dl>

            <p class="mt-4 text-xs text-[#64748B]"><span class="font-bold tabular-nums text-[#0B1B3F]">{{ number_format($outlet->orders_count) }}</span> total transaksi</p>

            <div class="mt-4 flex items-center gap-2 border-t border-[#E5E7EB] pt-4">
                <x-btn :href="route('outlets.edit', $outlet)" variant="soft" class="flex-1">Edit</x-btn>
                @if($outlet->orders_count == 0)
                <form method="POST" action="{{ route('outlets.destroy', $outlet) }}" onsubmit="return confirm('Hapus outlet ini?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="flex h-10 w-10 items-center justify-center rounded-lg border border-red-200 text-red-600 transition-colors hover:bg-red-50" title="Hapus outlet" aria-label="Hapus outlet">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $trash }}"/></svg>
                    </button>
                </form>
                @endif
            </div>
        </article>
        @endforeach
    </div>
    <div class="mt-5">{{ $outlets->links() }}</div>
    @else
    <section class="fade-up rounded-xl border border-[#E5E7EB] bg-white" style="--i:1">
        <x-empty title="Belum ada outlet" text="Tambahkan outlet untuk mulai beroperasi." icon="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2zM9 22V12h6v10">
            <x-btn :href="route('outlets.create')">Tambah outlet</x-btn>
        </x-empty>
    </section>
    @endif
</div>
@endsection
