@extends('layouts.app')
@section('title', 'Pesanan')
@section('page-title', 'Pesanan')

{{--
    Riwayat order versi aplikasi Android: daftar tenang tanpa kartu, dikelompokkan
    per hari. Jam dan total jadi fokus; nomor order hanya keterangan kecil.
--}}
@section('content')
@php
    $tz       = config('app.timezone');
    $today    = now($tz)->toDateString();
    $status   = (string) request('status', '');
    $from     = request('date_from');
    $to       = request('date_to');
    $statuses = ['' => 'Semua', 'paid' => 'Lunas', 'draft' => 'Draft', 'cancelled' => 'Batal', 'refunded' => 'Refund'];
    $ranges   = [
        'all'       => ['Semua tanggal', null, null],
        'today'     => ['Hari ini', $today, $today],
        'yesterday' => ['Kemarin', now($tz)->subDay()->toDateString(), now($tz)->subDay()->toDateString()],
        'week'      => ['7 hari', now($tz)->subDays(6)->toDateString(), $today],
        'month'     => ['Bulan ini', now($tz)->startOfMonth()->toDateString(), $today],
    ];
    $rangeKey = collect($ranges)->search(fn ($r) => $r[1] === $from && $r[2] === $to) ?: ($from ? 'custom' : 'all');
    $link     = fn (array $over) => route('orders.index', array_filter(array_merge(request()->only('status', 'date_from', 'date_to'), $over), fn ($v) => $v !== null && $v !== ''));
    $rp       = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $payLabel = ['cash' => 'Tunai', 'qris' => 'QRIS', 'debit' => 'Debit', 'credit' => 'Kartu kredit', 'transfer' => 'Transfer', 'ewallet' => 'E-wallet', 'split' => 'Gabungan'];
    $dot      = ['paid' => null, 'draft' => '#94A3B8', 'pending' => '#F59E0B', 'cancelled' => '#EF4444', 'refunded' => '#A855F7'];
    $word     = ['draft' => 'Draft', 'pending' => 'Menunggu', 'cancelled' => 'Dibatalkan', 'refunded' => 'Refund'];
    $groups   = $orders->getCollection()->groupBy(fn ($o) => $o->created_at->timezone($tz)->toDateString());
    $dayLabel = function (string $d) use ($today, $tz) {
        if ($d === $today) return 'Hari ini';
        if ($d === now($tz)->subDay()->toDateString()) return 'Kemarin';
        return \Carbon\Carbon::parse($d)->locale('id')->translatedFormat('l, j F');
    };
    $canExport = in_array(auth()->user()->role, ['owner', 'admin'], true);
    $exportQuery = array_filter(['date_from' => $from, 'date_to' => $to]);
@endphp

<div class="max-w-2xl -mx-5 sm:mx-auto text-[#0B1B3F]" x-data="{ sheet: false }">

    {{-- Status: teks dengan garis hijau, tanpa kotak --}}
    <nav class="sticky top-14 z-20 bg-white border-b border-[#EEF0F3] px-5 sm:px-0 flex gap-6 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        @foreach($statuses as $v => $l)
            <a href="{{ $link(['status' => $v, 'page' => null]) }}"
               class="relative shrink-0 h-12 inline-flex items-center text-[15px] {{ $status === $v ? 'font-semibold text-[#0B1B3F]' : 'font-medium text-[#94A3B8]' }}">
                {{ $l }}
                @if($status === $v)<span class="absolute inset-x-0 bottom-0 h-[2.5px] rounded-full bg-[#16A34A]"></span>@endif
            </a>
        @endforeach
    </nav>

    {{-- Rentang tanggal + unduh --}}
    <div class="px-5 sm:px-0 pt-4 pb-1 flex items-center gap-2">
        <div class="flex gap-2 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            @foreach($ranges as $k => [$label, $f, $t])
                <a href="{{ $link(['date_from' => $f, 'date_to' => $t, 'page' => null]) }}"
                   class="shrink-0 h-9 px-3.5 inline-flex items-center rounded-full text-[13px] font-semibold transition-colors
                          {{ $rangeKey === $k ? 'bg-[#0B1B3F] text-white' : 'bg-[#F1F5F9] text-[#475569]' }}">{{ $label }}</a>
            @endforeach
        </div>
        @if($canExport)
            <button type="button" @click="sheet = true"
                    class="ml-auto shrink-0 h-9 pl-3 pr-3.5 inline-flex items-center gap-1.5 rounded-full text-[13px] font-semibold text-[#15803D] active:bg-[#ECFDF3]">
                <svg class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v11m0 0l-4-4m4 4l4-4M5 19h14"/></svg>
                Unduh
            </button>
        @endif
    </div>

    <p class="px-5 sm:px-0 pt-2 text-[13px] text-[#94A3B8]">{{ number_format($orders->total(), 0, ',', '.') }} pesanan</p>

    {{-- Daftar per hari --}}
    @forelse($groups as $day => $list)
        <h2 class="px-5 sm:px-0 pt-7 pb-2 text-[13px] font-semibold text-[#64748B]">
            {{ $dayLabel($day) }}
            <span class="font-normal text-[#94A3B8]"> · {{ $rp($list->where('status', 'paid')->sum('grand_total')) }}</span>
        </h2>
        <ul class="divide-y divide-[#F1F5F9]">
            @foreach($list as $o)
                <li>
                    <a href="{{ route('orders.show', $o) }}" class="flex items-center gap-4 px-5 sm:px-0 py-3.5 active:bg-[#F8FAFC]">
                        <span class="w-12 shrink-0 text-[15px] font-medium tabular-nums text-[#64748B]">{{ $o->created_at->timezone($tz)->format('H:i') }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[17px] font-semibold tabular-nums {{ in_array($o->status, ['cancelled', 'refunded']) ? 'text-[#94A3B8] line-through decoration-1' : '' }}">{{ $rp($o->grand_total) }}</span>
                            <span class="block truncate text-[13px] text-[#94A3B8]">
                                {{ $payLabel[$o->payment_method] ?? ucfirst((string) $o->payment_method) }}
                                · {{ \Illuminate\Support\Str::of($o->user?->name ?? 'Self order')->before(' ')->title() }}
                                · {{ $o->order_number }}
                            </span>
                        </span>
                        @if($dot[$o->status] ?? null)
                            <span class="shrink-0 inline-flex items-center gap-1.5 text-[13px] font-medium" style="color: {{ $dot[$o->status] }}">
                                <span class="w-1.5 h-1.5 rounded-full" style="background: {{ $dot[$o->status] }}"></span>{{ $word[$o->status] ?? ucfirst($o->status) }}
                            </span>
                        @endif
                        <svg class="w-4 h-4 shrink-0 text-[#CBD5E1]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </li>
            @endforeach
        </ul>
    @empty
        <div class="px-5 pt-20 pb-10 text-center">
            <p class="text-[17px] font-semibold">Belum ada pesanan</p>
            <p class="mt-1 text-sm text-[#64748B]">Coba ganti status atau rentang tanggal.</p>
        </div>
    @endforelse

    {{-- Halaman: cukup sebelumnya / berikutnya --}}
    @if($orders->lastPage() > 1)
        <div class="px-5 sm:px-0 pt-8 pb-4 flex items-center justify-between">
            @if($orders->onFirstPage())
                <span class="h-11 px-4 inline-flex items-center text-[15px] font-semibold text-[#CBD5E1]">Sebelumnya</span>
            @else
                <a href="{{ $orders->previousPageUrl() }}" class="h-11 px-4 -ml-4 inline-flex items-center gap-1 text-[15px] font-semibold text-[#0B1B3F] active:bg-[#F1F5F9] rounded-full">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>Sebelumnya</a>
            @endif
            <span class="text-[13px] text-[#94A3B8] tabular-nums">{{ $orders->currentPage() }} / {{ $orders->lastPage() }}</span>
            @if($orders->hasMorePages())
                <a href="{{ $orders->nextPageUrl() }}" class="h-11 px-4 -mr-4 inline-flex items-center gap-1 text-[15px] font-semibold text-[#0B1B3F] active:bg-[#F1F5F9] rounded-full">
                    Berikutnya<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></a>
            @else
                <span class="h-11 px-4 inline-flex items-center text-[15px] font-semibold text-[#CBD5E1]">Berikutnya</span>
            @endif
        </div>
    @endif

    {{-- Lembar unduh --}}
    @if($canExport)
        <div x-show="sheet" x-cloak class="fixed inset-0 z-50" @keydown.escape.window="sheet = false">
            <div class="absolute inset-0 bg-[#0B1B3F]/30" @click="sheet = false" x-show="sheet" x-transition.opacity></div>
            <div class="absolute inset-x-0 bottom-0 mx-auto max-w-xl bg-white rounded-t-3xl px-6 pt-3 pb-[calc(24px+env(safe-area-inset-bottom))]"
                 x-show="sheet" x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-full" x-transition:leave="transition duration-150 ease-in" x-transition:leave-end="translate-y-full">
                <div class="mx-auto w-10 h-1 rounded-full bg-[#E2E8F0]"></div>
                <p class="mt-5 text-[17px] font-semibold">Unduh laporan penjualan</p>
                <p class="mt-1 text-sm text-[#64748B]">
                    {{ $rangeKey === 'all' ? 'Bulan ini' : ($ranges[$rangeKey][0] ?? (\Carbon\Carbon::parse($from)->translatedFormat('j M') . ' – ' . \Carbon\Carbon::parse($to ?: $from)->translatedFormat('j M'))) }}.
                    Isinya sama dengan menu Laporan di web.
                </p>
                <div class="mt-5 divide-y divide-[#F1F5F9]">
                    <a href="{{ route('orders.export', ['format' => 'xlsx'] + $exportQuery) }}" @click="setTimeout(() => sheet = false, 300)" class="flex items-center gap-4 py-4 active:bg-[#F8FAFC]">
                        <span class="w-10 h-10 rounded-full bg-[#ECFDF3] text-[#15803D] flex items-center justify-center text-[11px] font-bold">XLS</span>
                        <span class="flex-1"><span class="block text-[15px] font-semibold">Excel</span><span class="block text-[13px] text-[#94A3B8]">Untuk diolah di spreadsheet</span></span>
                    </a>
                    <a href="{{ route('orders.export', ['format' => 'pdf'] + $exportQuery) }}" @click="setTimeout(() => sheet = false, 300)" class="flex items-center gap-4 py-4 active:bg-[#F8FAFC]">
                        <span class="w-10 h-10 rounded-full bg-[#FEF2F2] text-[#DC2626] flex items-center justify-center text-[11px] font-bold">PDF</span>
                        <span class="flex-1"><span class="block text-[15px] font-semibold">PDF</span><span class="block text-[13px] text-[#94A3B8]">Siap dicetak atau dikirim</span></span>
                    </a>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
