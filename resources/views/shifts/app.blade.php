@extends('layouts.app')
@section('title', 'Shift')
@section('page-title', 'Shift')

{{-- Shift versi aplikasi: status shift kamu di atas, riwayat sebagai daftar tenang per hari. --}}
@section('content')
@php
    $tz  = config('app.timezone');
    $rp  = fn ($v) => 'Rp ' . number_format(abs((float) $v), 0, ',', '.');
    $first = fn ($u) => $u ? \Illuminate\Support\Str::of($u->name)->before(' ')->title() : '-';
    $groups = $shifts->getCollection()->groupBy(fn ($s) => $s->opened_at->timezone($tz)->toDateString());
    $dayLabel = function (string $d) use ($tz) {
        if ($d === now($tz)->toDateString()) return 'Hari ini';
        if ($d === now($tz)->subDay()->toDateString()) return 'Kemarin';
        return \Carbon\Carbon::parse($d)->locale('id')->translatedFormat('l, j F');
    };
@endphp

<div class="max-w-2xl -mx-5 sm:mx-auto text-[#0B1B3F]">

    {{-- Shift kamu --}}
    <section class="px-5 sm:px-0 pt-7 pb-8 border-b border-[#F1F5F9]">
        @if($activeShift)
            <p class="inline-flex items-center gap-2 text-[13px] font-semibold text-[#15803D]">
                <span class="relative flex w-2 h-2"><span class="absolute inset-0 rounded-full bg-[#16A34A] opacity-60 animate-ping"></span><span class="relative w-2 h-2 rounded-full bg-[#16A34A]"></span></span>
                Shift kamu sedang berjalan
            </p>
            <p class="mt-3 text-[28px] leading-tight font-semibold tracking-[-0.02em]">Sejak {{ $activeShift->opened_at->timezone($tz)->format('H:i') }}</p>
            <p class="mt-1 text-[15px] text-[#64748B]">{{ $activeShift->outlet->name }} · kas awal {{ $rp($activeShift->opening_cash) }}</p>
            <div class="mt-6 flex items-center gap-5">
                <a href="{{ route('shifts.close', $activeShift) }}" class="h-12 px-6 inline-flex items-center rounded-2xl bg-[#0B1B3F] active:bg-[#1E293B] text-white text-[15px] font-semibold">Tutup shift</a>
                <a href="{{ route('shifts.show', $activeShift) }}" class="text-[15px] font-semibold text-[#15803D]">Lihat ringkasan</a>
            </div>
        @else
            <p class="text-[13px] font-medium text-[#94A3B8]">Belum ada shift berjalan</p>
            <p class="mt-2 text-[22px] leading-snug font-semibold tracking-[-0.01em]">Buka shift dulu sebelum mulai jualan.</p>
            <a href="{{ route('shifts.open') }}" class="mt-6 h-12 px-6 inline-flex items-center rounded-2xl bg-[#16A34A] active:bg-[#15803D] text-white text-[15px] font-semibold shadow-[0_8px_20px_rgba(22,163,74,.22)]">Buka shift</a>
        @endif
    </section>

    {{-- Riwayat --}}
    @forelse($groups as $day => $list)
        <h2 class="px-5 sm:px-0 pt-7 pb-2 text-[13px] font-semibold text-[#64748B]">{{ $dayLabel($day) }}</h2>
        <ul class="divide-y divide-[#F1F5F9]">
            @foreach($list as $s)
                @php
                    $diff = $s->cash_difference;
                    $open = $s->status === 'open';
                @endphp
                <li>
                    <a href="{{ route('shifts.show', $s) }}" class="flex items-center gap-4 px-5 sm:px-0 py-4 active:bg-[#F8FAFC]">
                        <span class="min-w-0 flex-1">
                            <span class="block text-[16px] font-semibold tabular-nums">
                                {{ $s->opened_at->timezone($tz)->format('H:i') }}
                                <span class="text-[#CBD5E1] font-normal">–</span>
                                {{ $s->closed_at ? $s->closed_at->timezone($tz)->format('H:i') : 'sekarang' }}
                            </span>
                            <span class="block truncate text-[13px] text-[#94A3B8]">{{ $first($s->user) }} · {{ $s->outlet->name ?? '-' }} · kas awal {{ $rp($s->opening_cash) }}</span>
                        </span>
                        <span class="shrink-0 text-right">
                            @if($open)
                                <span class="text-[13px] font-semibold text-[#15803D]">Berjalan</span>
                            @elseif($diff === null || (float) $diff == 0.0)
                                <span class="text-[13px] font-medium text-[#94A3B8]">Kas pas</span>
                            @else
                                <span class="block text-[14px] font-semibold tabular-nums {{ $diff > 0 ? 'text-[#15803D]' : 'text-[#DC2626]' }}">{{ $diff > 0 ? '+' : '−' }}{{ $rp($diff) }}</span>
                                <span class="block text-[12px] text-[#94A3B8]">{{ $diff > 0 ? 'lebih' : 'kurang' }}</span>
                            @endif
                        </span>
                        <svg class="w-4 h-4 shrink-0 text-[#CBD5E1]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </li>
            @endforeach
        </ul>
    @empty
        <p class="px-5 pt-16 text-center text-sm text-[#94A3B8]">Riwayat shift akan muncul di sini.</p>
    @endforelse

    @include('partials.app-pager', ['paginator' => $shifts])
</div>
@endsection
