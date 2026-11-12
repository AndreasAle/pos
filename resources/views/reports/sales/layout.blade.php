@extends('layouts.app')
@section('title', 'Laporan Penjualan')
@section('page-title', 'Laporan Penjualan')
@section('page-subtitle', 'Omzet, transaksi, metode bayar, dan produk pada periode yang kamu pilih.')

@push('styles')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endpush

@php
    $q = $queryString ?? [];
    $tabs = [
        'summary'   => ['Ringkasan', 'reports.sales'],
        'analytics' => ['Analitik', 'reports.sales.analytics'],
        'breakdown' => ['Breakdown', 'reports.sales.breakdown'],
        'products'  => ['Produk', 'reports.sales.products'],
        'orders'    => ['Riwayat Order', 'reports.sales.orders'],
    ];
    $presetLabels = ['today' => 'Hari ini', 'yesterday' => 'Kemarin', 'this_week' => 'Minggu ini', 'last_week' => 'Minggu lalu', 'this_month' => 'Bulan ini', 'last_month' => 'Bulan lalu', 'custom' => 'Pilih tanggal'];
@endphp

@section('content')
<div class="y-report text-[14px] text-[#0B1B3F]" x-data="{ custom: {{ $f->preset === 'custom' ? 'true' : 'false' }}, outletsOpen: false, exportOpen: false }">

    @if(session('report_error') || $errors->any())
    <div role="alert" class="mb-3 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-800">
        {{ session('report_error') ?? $errors->first() }} Filter dikembalikan ke bawaan.
    </div>
    @endif

    {{-- Tabs --}}
    <nav aria-label="Bagian laporan penjualan" class="mb-4 flex gap-1 overflow-x-auto rounded-lg border border-[#E5E7EB] bg-slate-50 p-1 y-scroll sm:inline-flex">
        @foreach($tabs as $key => [$label, $route])
            <a href="{{ route($route, $q) }}" @if($tab === $key) aria-current="page" @endif
               class="shrink-0 rounded-md px-4 py-2 text-[13px] font-semibold transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-[#16A34A]
                      {{ $tab === $key ? 'bg-white text-[#16A34A] shadow-sm ring-1 ring-[#E5E7EB]' : 'text-[#64748B] hover:text-[#0B1B3F]' }}">{{ $label }}</a>
        @endforeach
    </nav>

    {{-- Filter toolbar: all state lives in the URL --}}
    <form method="GET" action="{{ url()->current() }}" class="mb-4 rounded-xl border border-[#E5E7EB] bg-white p-4">
        @foreach(['row','col','metric','status','q','sort','dir'] as $keep)
            @if(request()->filled($keep) && $tab !== 'summary')<input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}">@endif
        @endforeach
        <div class="flex flex-wrap items-end gap-3">
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold text-[#64748B]">Periode</span>
                <select name="preset" x-on:change="custom = $event.target.value === 'custom'" class="h-10 rounded-lg text-sm">
                    @foreach($presetLabels as $k => $l)<option value="{{ $k }}" @selected($f->preset === $k)>{{ $l }}</option>@endforeach
                </select>
            </label>
            <div x-show="custom" x-cloak class="flex items-end gap-2">
                <label class="block"><span class="mb-1.5 block text-xs font-semibold text-[#64748B]">Dari</span>
                    <input type="date" name="date_from" value="{{ $f->dateFrom() }}" class="h-10 rounded-lg text-sm"></label>
                <label class="block"><span class="mb-1.5 block text-xs font-semibold text-[#64748B]">Sampai</span>
                    <input type="date" name="date_to" value="{{ $f->dateTo() }}" class="h-10 rounded-lg text-sm"></label>
            </div>

            @if(count($outlets) > 1)
            <div class="relative">
                <span class="mb-1.5 block text-xs font-semibold text-[#64748B]">Outlet</span>
                <button type="button" x-on:click="outletsOpen = !outletsOpen" aria-haspopup="true" :aria-expanded="outletsOpen"
                        class="h-10 min-w-[10rem] rounded-lg border border-[#E5E7EB] bg-white px-3 text-left text-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-[#16A34A]">
                    {{ count($f->outletIds) === count($f->allowedOutletIds) ? 'Semua outlet' : count($f->outletIds).' outlet dipilih' }}
                </button>
                <div x-show="outletsOpen" x-cloak x-on:click.outside="outletsOpen = false" class="absolute z-20 mt-1 w-64 rounded-xl border border-[#E5E7EB] bg-white p-2 shadow-lg">
                    @foreach($outlets as $o)
                    <label class="flex min-h-[40px] cursor-pointer items-center gap-2 rounded px-2 hover:bg-gray-50">
                        <input type="checkbox" name="outlet_ids[]" value="{{ $o->id }}" @checked(in_array($o->id, $f->outletIds) && count($f->outletIds) !== count($f->allowedOutletIds)) class="rounded border-[#E5E7EB] text-[#16A34A] focus:ring-[#16A34A]">
                        <span class="text-sm">{{ $o->name }}</span>
                    </label>
                    @endforeach
                    <p class="px-2 pt-1 text-xs text-[#94A3B8]">Kosongkan semua = semua outlet.</p>
                </div>
            </div>
            @endif

            <label class="block"><span class="mb-1.5 block text-xs font-semibold text-[#64748B]">Kasir</span>
                <select name="cashier_ids[]" class="h-10 rounded-lg text-sm">
                    <option value="">Semua kasir</option>
                    @foreach($cashiers as $c)<option value="{{ $c->id }}" @selected(in_array($c->id, $f->cashierIds))>{{ $c->name }}</option>@endforeach
                </select></label>
            <label class="block"><span class="mb-1.5 block text-xs font-semibold text-[#64748B]">Metode bayar</span>
                <select name="payment_methods[]" class="h-10 rounded-lg text-sm">
                    <option value="">Semua metode</option>
                    @foreach(\App\Services\Reporting\SalesReportFilter::PAYMENT_METHODS as $m)<option value="{{ $m }}" @selected(in_array($m, $f->paymentMethods))>{{ \App\Support\Money::method($m) }}</option>@endforeach
                </select></label>
            @if(in_array($tab, ['breakdown','products']))
            <label class="block"><span class="mb-1.5 block text-xs font-semibold text-[#64748B]">Kategori</span>
                <select name="category_ids[]" class="h-10 rounded-lg text-sm">
                    <option value="">Semua kategori</option>
                    @foreach($categoriesAll as $c)<option value="{{ $c->id }}" @selected(in_array($c->id, $f->categoryIds))>{{ $c->name }}</option>@endforeach
                </select></label>
            @endif
            @if($tab === 'summary')
            <label class="block"><span class="mb-1.5 block text-xs font-semibold text-[#64748B]">Tren per</span>
                <select name="grouping" class="h-10 rounded-lg text-sm">
                    @foreach(['day' => 'Hari', 'weekday' => 'Hari dalam minggu', 'hour' => 'Jam', 'week' => 'Minggu', 'month' => 'Bulan'] as $k => $l)<option value="{{ $k }}" @selected($f->grouping === $k)>{{ $l }}</option>@endforeach
                </select></label>
            @endif
            @if($tab === 'analytics')
            <label class="block"><span class="mb-1.5 block text-xs font-semibold text-[#64748B]">Bandingkan</span>
                <select name="comparison" x-data x-on:change="$el.form.querySelectorAll('[data-cmp]').forEach(e => e.hidden = e.dataset.cmp !== $el.value)" class="h-10 rounded-lg text-sm">
                    @foreach(['none' => 'Tanpa pembanding', 'previous' => 'Periode sebelumnya', 'custom' => 'Rentang tanggal lain', 'outlets' => 'Outlet lain'] as $k => $l)<option value="{{ $k }}" @selected($f->comparison === $k)>{{ $l }}</option>@endforeach
                </select></label>
            <div data-cmp="custom" @if($f->comparison !== 'custom') hidden @endif class="flex items-end gap-2">
                <label class="block"><span class="mb-1.5 block text-xs font-semibold text-[#64748B]">Pembanding dari</span>
                    <input type="date" name="compare_from" value="{{ $f->comparison === 'custom' ? $f->compareStart?->toDateString() : '' }}" class="h-10 rounded-lg text-sm"></label>
                <label class="block"><span class="mb-1.5 block text-xs font-semibold text-[#64748B]">sampai</span>
                    <input type="date" name="compare_to" value="{{ $f->comparison === 'custom' ? $f->compareEnd?->subSecond()->toDateString() : '' }}" class="h-10 rounded-lg text-sm"></label>
            </div>
            <label data-cmp="outlets" @if($f->comparison !== 'outlets') hidden @endif class="block"><span class="mb-1.5 block text-xs font-semibold text-[#64748B]">Outlet pembanding</span>
                <select name="compare_outlet_ids[]" class="h-10 rounded-lg text-sm">
                    @foreach($outlets as $o)<option value="{{ $o->id }}" @selected(in_array($o->id, $f->compareOutletIds))>{{ $o->name }}</option>@endforeach
                </select></label>
            @endif

            <div class="flex items-center gap-2">
                <button type="submit" class="h-10 rounded-lg bg-[#16A34A] px-4 text-sm font-semibold text-white hover:bg-[#15803D] focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#16A34A]">Terapkan</button>
                <a href="{{ url()->current() }}" class="inline-flex h-10 items-center rounded-lg px-3 text-sm text-[#64748B] hover:bg-slate-100">Reset</a>
            </div>

            <div class="ml-auto flex items-center gap-2">
                @if(config('pos.features.intelligence'))
                <a href="{{ route('reports.sales.ask', $q) }}" class="inline-flex h-10 items-center rounded-lg border border-[#E5E7EB] bg-white px-3 text-sm font-semibold text-[#0B1B3F] hover:border-[#16A34A] hover:text-[#15803D]">Analisis dengan Tanya Yoka</a>
                @endif
                <div class="relative">
                    <button type="button" x-on:click="exportOpen = !exportOpen" aria-haspopup="true" :aria-expanded="exportOpen" class="h-10 rounded-lg border border-[#E5E7EB] bg-white px-3 text-sm font-semibold text-[#334155] hover:border-[#16A34A] hover:text-[#15803D] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#16A34A]">Ekspor</button>
                    <div x-show="exportOpen" x-cloak x-on:click.outside="exportOpen = false" class="absolute right-0 z-20 mt-1 w-44 rounded-xl border border-[#E5E7EB] bg-white py-1 shadow-lg">
                        <a href="{{ route('reports.sales.export', $q) }}" class="block min-h-[40px] px-3 py-2.5 text-sm hover:bg-gray-50">Excel (.xlsx)</a>
                        <a href="{{ route('reports.sales.pdf', $q) }}" class="block min-h-[40px] px-3 py-2.5 text-sm hover:bg-gray-50">PDF</a>
                    </div>
                </div>
            </div>
        </div>
        <p class="mt-2 text-xs text-[#64748B]">
            {{ $f->start->locale('id')->isoFormat('D MMM Y') }} – {{ $f->end->subSecond()->locale('id')->isoFormat('D MMM Y') }} · zona waktu {{ $f->timezone }}
            · {{ count($f->outletIds) === count($f->allowedOutletIds) ? 'semua outlet' : count($f->outletIds).' outlet' }}
        </p>
    </form>

    @yield('report')
</div>
@endsection
