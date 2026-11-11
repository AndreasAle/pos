@extends('layouts.app')
@section('title','Shift Kasir')
@section('page-title','Shift Kasir')

@push('styles')
<style>
    .pg .fade-up { opacity:0; transform:translateY(12px); animation:pg-up .55s cubic-bezier(.2,.7,.2,1) forwards; animation-delay:calc(var(--i,0) * 70ms); }
    @keyframes pg-up { to { opacity:1; transform:none } }
    .pg .float { animation:pg-float 4.5s ease-in-out infinite }
    .pg .twinkle { animation:pg-tw 2.4s ease-in-out infinite; transform-box:fill-box; transform-origin:center }
    .pg .tick { transform-origin:56px 56px; animation:pg-tick 8s linear infinite }
    .pg .ring { animation:pg-ring 2.2s ease-out infinite }
    @keyframes pg-float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-6px)} }
    @keyframes pg-tw { 0%,100%{opacity:.3;transform:scale(.7)} 50%{opacity:1;transform:scale(1.1)} }
    @keyframes pg-tick { to { transform:rotate(360deg) } }
    @keyframes pg-ring { 0%{box-shadow:0 0 0 0 rgba(22,163,74,.45)} 70%,100%{box-shadow:0 0 0 9px rgba(22,163,74,0)} }
    @media (prefers-reduced-motion: reduce) { .pg *, .pg svg * { animation:none !important } .pg .fade-up { opacity:1; transform:none } }
</style>
@endpush

@section('content')
@php $rp = fn($v) => 'Rp ' . number_format($v, 0, ',', '.'); @endphp

<div class="pg text-[#0B1B3F] pt-2">

    {{-- Kartu status shift --}}
    <section class="fade-up mb-4 rounded-xl border p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center gap-4
                    {{ $activeShift ? 'border-[#BBF7D0] bg-gradient-to-r from-[#ECFDF3] to-white' : 'border-[#E5E7EB] bg-white' }}" style="--i:0">
        <span class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0 {{ $activeShift ? 'bg-[#16A34A] text-white ring' : 'bg-slate-100 text-slate-500' }}">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="stroke-width:1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </span>
        <div class="flex-1 min-w-0">
            @if($activeShift)
            <p class="text-base font-semibold">Shift sedang berjalan</p>
            <p class="text-sm text-[#64748B]">{{ $activeShift->outlet->name }} &middot; dibuka {{ $activeShift->opened_at->format('d M, H:i') }} &middot; modal awal {{ $rp($activeShift->opening_cash) }}</p>
            @else
            <p class="text-base font-semibold">Belum ada shift aktif</p>
            <p class="text-sm text-[#64748B]">Buka shift untuk mulai mencatat penjualan dan kas kasir.</p>
            @endif
        </div>
        <div class="flex flex-wrap gap-2 shrink-0">
            @if($activeShift)
            <a href="{{ route('shifts.show', $activeShift) }}" class="h-10 inline-flex items-center px-4 rounded-lg border border-[#E5E7EB] bg-white text-sm font-semibold hover:bg-slate-50 transition-colors">Lihat Detail</a>
            <a href="{{ route('shifts.close', $activeShift) }}" class="h-10 inline-flex items-center px-4 rounded-lg bg-red-600 text-white text-sm font-semibold hover:bg-red-700 transition-colors shadow-sm">Tutup Shift</a>
            @else
            <a href="{{ route('shifts.open') }}" class="h-10 inline-flex items-center gap-2 px-4 rounded-lg bg-[#16A34A] text-white text-sm font-semibold hover:bg-[#15803D] transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m7-7H5"/></svg>Buka Shift Baru
            </a>
            @endif
        </div>
    </section>

    {{-- Riwayat --}}
    <section class="fade-up rounded-xl border border-[#E5E7EB] bg-white overflow-hidden" style="--i:1">
        <div class="px-5 sm:px-6 py-4 flex items-center justify-between border-b border-[#E5E7EB]">
            <div>
                <h3 class="text-lg font-semibold">Riwayat Shift</h3>
                <p class="text-sm text-[#64748B]">{{ number_format($shifts->total()) }} shift tercatat</p>
            </div>
        </div>

        @if($shifts->count())
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap">
                <thead>
                    <tr class="bg-slate-50 border-b border-[#E5E7EB]">
                        <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Kasir</th>
                        <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Outlet</th>
                        <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Buka</th>
                        <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Tutup</th>
                        <th class="text-right px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Modal Awal</th>
                        <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E5E7EB]">
                    @foreach($shifts as $shift)
                    <tr class="group hover:bg-[#ECFDF3]/50 transition-colors cursor-pointer" onclick="window.location='{{ route('shifts.show', $shift) }}'">
                        <td class="px-6 py-4">
                            <span class="flex items-center gap-2.5 font-medium">
                                <span class="w-7 h-7 rounded-full bg-[#ECFDF3] text-[#16A34A] text-xs font-bold flex items-center justify-center">{{ strtoupper(substr($shift->user->name ?? '-', 0, 1)) }}</span>
                                {{ $shift->user->name ?? '-' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-slate-600">{{ $shift->outlet->name ?? '-' }}</td>
                        <td class="px-6 py-4 text-slate-600">{{ $shift->opened_at->format('d M Y, H:i') }}</td>
                        <td class="px-6 py-4 text-slate-600">{{ $shift->closed_at?->format('d M Y, H:i') ?? '—' }}</td>
                        <td class="px-6 py-4 text-right font-semibold">{{ $rp($shift->opening_cash) }}</td>
                        <td class="px-6 py-4"><x-badge :color="$shift->status === 'open' ? 'green' : 'gray'">{{ $shift->status === 'open' ? 'Aktif' : 'Tutup' }}</x-badge></td>
                        <td class="px-6 py-4 text-right">
                            <span class="inline-flex items-center gap-1 text-[13px] font-semibold text-[#16A34A] opacity-70 group-hover:opacity-100">Detail
                                <svg class="w-3.5 h-3.5 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="md:hidden divide-y divide-[#E5E7EB]">
            @foreach($shifts as $shift)
            <a href="{{ route('shifts.show', $shift) }}" class="block px-4 py-3.5 active:bg-[#ECFDF3]/60">
                <div class="flex items-center justify-between gap-2">
                    <span class="font-semibold truncate">{{ $shift->user->name ?? '-' }}</span>
                    <x-badge :color="$shift->status === 'open' ? 'green' : 'gray'">{{ $shift->status === 'open' ? 'Aktif' : 'Tutup' }}</x-badge>
                </div>
                <p class="mt-0.5 text-sm text-[#64748B] truncate">{{ $shift->outlet->name ?? '-' }}</p>
                <div class="mt-1.5 flex items-end justify-between gap-2 text-xs text-[#64748B]">
                    <div>
                        <p>Buka: {{ $shift->opened_at->format('d M, H:i') }}</p>
                        <p>Tutup: {{ $shift->closed_at?->format('d M, H:i') ?? '—' }}</p>
                    </div>
                    <p class="text-sm font-bold text-[#0B1B3F]">{{ $rp($shift->opening_cash) }}</p>
                </div>
            </a>
            @endforeach
        </div>
        @else
        <div class="flex flex-col items-center text-center px-6 py-12">
            <svg class="w-28 h-28 float" viewBox="0 0 112 112" fill="none" aria-hidden="true">
                <ellipse cx="56" cy="100" rx="30" ry="5" fill="#16A34A" opacity=".12"/>
                <circle cx="56" cy="54" r="38" fill="#ECFDF3" stroke="#BBF7D0" stroke-width="2"/>
                <circle cx="56" cy="54" r="30" fill="#fff" stroke="#D1FAE5" stroke-width="2"/>
                <path d="M56 28v4M56 76v4M30 54h4M78 54h4" stroke="#86EFAC" stroke-width="3" stroke-linecap="round"/>
                <g class="tick"><path d="M56 54V36" stroke="#16A34A" stroke-width="3.5" stroke-linecap="round"/></g>
                <path d="M56 54l11 7" stroke="#0B1B3F" stroke-width="3.5" stroke-linecap="round"/>
                <circle cx="56" cy="54" r="3.5" fill="#16A34A"/>
                <g class="twinkle"><path d="M94 22l2 4.6 4.6 2-4.6 2-2 4.6-2-4.6-4.6-2 4.6-2z" fill="#4ADE80"/></g>
                <g class="twinkle" style="animation-delay:1s"><path d="M16 36l1.5 3.5 3.5 1.5-3.5 1.5L16 46l-1.5-3.5L11 41l3.5-1.5z" fill="#BBF7D0"/></g>
            </svg>
            <p class="mt-3 text-base font-semibold">Belum ada riwayat shift</p>
            <p class="mt-1 text-sm text-[#64748B] max-w-xs">Riwayat muncul di sini setelah shift pertama dibuka dan ditutup.</p>
        </div>
        @endif
    </section>

    @if($shifts->hasPages())
    <div class="mt-4">{{ $shifts->links() }}</div>
    @endif
</div>
@endsection
