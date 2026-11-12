@extends('layouts.app')
@section('title', 'Laporan Penjualan')
@section('page-title', 'Laporan Penjualan')

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
<div class="text-[14px] text-gray-800" x-data="{ custom: {{ $f->preset === 'custom' ? 'true' : 'false' }}, outletsOpen: false, exportOpen: false }">

    @if(session('report_error') || $errors->any())
    <div role="alert" class="mb-3 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-800">
        {{ session('report_error') ?? $errors->first() }} Filter dikembalikan ke bawaan.
    </div>
    @endif

    {{-- Tabs --}}
    <nav aria-label="Bagian laporan penjualan" class="mb-3 flex flex-wrap items-center gap-x-1 border-b border-gray-200">
        @foreach($tabs as $key => [$label, $route])
            <a href="{{ route($route, $q) }}" @if($tab === $key) aria-current="page" @endif
               class="-mb-px px-3.5 py-2.5 text-sm font-semibold border-b-2 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0C7856] rounded-t
                      {{ $tab === $key ? 'border-[#0C7856] text-[#0C7856]' : 'border-transparent text-gray-500 hover:text-gray-800' }}">{{ $label }}</a>
        @endforeach
        <span class="ml-auto hidden lg:flex items-center gap-3 pb-1 text-xs">
            <a href="{{ route('reports.products') }}" class="text-gray-500 hover:text-gray-800">Produk terlaris (lama)</a>
            <a href="{{ route('reports.cashier') }}" class="text-gray-500 hover:text-gray-800">Kasir</a>
            <a href="{{ route('reports.shift') }}" class="text-gray-500 hover:text-gray-800">Shift</a>
            <a href="{{ route('reports.profit') }}" class="text-gray-500 hover:text-gray-800">Estimasi profit</a>
        </span>
    </nav>

    {{-- Filter toolbar: all state lives in the URL --}}
    <form method="GET" action="{{ url()->current() }}" class="mb-4 rounded-lg border border-gray-200 bg-white p-3">
        @foreach(['row','col','metric','status','q','sort','dir'] as $keep)
            @if(request()->filled($keep) && $tab !== 'summary')<input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}">@endif
        @endforeach
        <div class="flex flex-wrap items-end gap-3">
            <label class="block">
                <span class="mb-1 block text-xs font-semibold text-gray-500">Periode</span>
                <select name="preset" x-on:change="custom = $event.target.value === 'custom'" class="h-10 rounded-lg border-gray-300 text-sm focus:border-[#0C7856] focus:ring-[#0C7856]">
                    @foreach($presetLabels as $k => $l)<option value="{{ $k }}" @selected($f->preset === $k)>{{ $l }}</option>@endforeach
                </select>
            </label>
            <div x-show="custom" x-cloak class="flex items-end gap-2">
                <label class="block"><span class="mb-1 block text-xs font-semibold text-gray-500">Dari</span>
                    <input type="date" name="date_from" value="{{ $f->dateFrom() }}" class="h-10 rounded-lg border-gray-300 text-sm focus:border-[#0C7856] focus:ring-[#0C7856]"></label>
                <label class="block"><span class="mb-1 block text-xs font-semibold text-gray-500">Sampai</span>
                    <input type="date" name="date_to" value="{{ $f->dateTo() }}" class="h-10 rounded-lg border-gray-300 text-sm focus:border-[#0C7856] focus:ring-[#0C7856]"></label>
            </div>

            @if(count($outlets) > 1)
            <div class="relative">
                <span class="mb-1 block text-xs font-semibold text-gray-500">Outlet</span>
                <button type="button" x-on:click="outletsOpen = !outletsOpen" aria-haspopup="true" :aria-expanded="outletsOpen"
                        class="h-10 min-w-[10rem] rounded-lg border border-gray-300 bg-white px-3 text-left text-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0C7856]">
                    {{ count($f->outletIds) === count($f->allowedOutletIds) ? 'Semua outlet' : count($f->outletIds).' outlet dipilih' }}
                </button>
                <div x-show="outletsOpen" x-cloak x-on:click.outside="outletsOpen = false" class="absolute z-20 mt-1 w-64 rounded-lg border border-gray-200 bg-white p-2 shadow-lg">
                    @foreach($outlets as $o)
                    <label class="flex min-h-[40px] cursor-pointer items-center gap-2 rounded px-2 hover:bg-gray-50">
                        <input type="checkbox" name="outlet_ids[]" value="{{ $o->id }}" @checked(in_array($o->id, $f->outletIds) && count($f->outletIds) !== count($f->allowedOutletIds)) class="rounded border-gray-300 text-[#0C7856] focus:ring-[#0C7856]">
                        <span class="text-sm">{{ $o->name }}</span>
                    </label>
                    @endforeach
                    <p class="px-2 pt-1 text-xs text-gray-400">Kosongkan semua = semua outlet.</p>
                </div>
            </div>
            @endif

            <label class="block"><span class="mb-1 block text-xs font-semibold text-gray-500">Kasir</span>
                <select name="cashier_ids[]" class="h-10 rounded-lg border-gray-300 text-sm focus:border-[#0C7856] focus:ring-[#0C7856]">
                    <option value="">Semua kasir</option>
                    @foreach($cashiers as $c)<option value="{{ $c->id }}" @selected(in_array($c->id, $f->cashierIds))>{{ $c->name }}</option>@endforeach
                </select></label>
            <label class="block"><span class="mb-1 block text-xs font-semibold text-gray-500">Metode bayar</span>
                <select name="payment_methods[]" class="h-10 rounded-lg border-gray-300 text-sm focus:border-[#0C7856] focus:ring-[#0C7856]">
                    <option value="">Semua metode</option>
                    @foreach(\App\Services\Reporting\SalesReportFilter::PAYMENT_METHODS as $m)<option value="{{ $m }}" @selected(in_array($m, $f->paymentMethods))>{{ \App\Support\Money::method($m) }}</option>@endforeach
                </select></label>
            @if(in_array($tab, ['breakdown','products']))
            <label class="block"><span class="mb-1 block text-xs font-semibold text-gray-500">Kategori</span>
                <select name="category_ids[]" class="h-10 rounded-lg border-gray-300 text-sm focus:border-[#0C7856] focus:ring-[#0C7856]">
                    <option value="">Semua kategori</option>
                    @foreach($categoriesAll as $c)<option value="{{ $c->id }}" @selected(in_array($c->id, $f->categoryIds))>{{ $c->name }}</option>@endforeach
                </select></label>
            @endif
            @if($tab === 'summary')
            <label class="block"><span class="mb-1 block text-xs font-semibold text-gray-500">Tren per</span>
                <select name="grouping" class="h-10 rounded-lg border-gray-300 text-sm focus:border-[#0C7856] focus:ring-[#0C7856]">
                    @foreach(['day' => 'Hari', 'weekday' => 'Hari dalam minggu', 'hour' => 'Jam', 'week' => 'Minggu', 'month' => 'Bulan'] as $k => $l)<option value="{{ $k }}" @selected($f->grouping === $k)>{{ $l }}</option>@endforeach
                </select></label>
            @endif
            @if($tab === 'analytics')
            <label class="block"><span class="mb-1 block text-xs font-semibold text-gray-500">Bandingkan</span>
                <select name="comparison" x-data x-on:change="$el.form.querySelectorAll('[data-cmp]').forEach(e => e.hidden = e.dataset.cmp !== $el.value)" class="h-10 rounded-lg border-gray-300 text-sm focus:border-[#0C7856] focus:ring-[#0C7856]">
                    @foreach(['none' => 'Tanpa pembanding', 'previous' => 'Periode sebelumnya', 'custom' => 'Rentang tanggal lain', 'outlets' => 'Outlet lain'] as $k => $l)<option value="{{ $k }}" @selected($f->comparison === $k)>{{ $l }}</option>@endforeach
                </select></label>
            <div data-cmp="custom" @if($f->comparison !== 'custom') hidden @endif class="flex items-end gap-2">
                <label class="block"><span class="mb-1 block text-xs font-semibold text-gray-500">Pembanding dari</span>
                    <input type="date" name="compare_from" value="{{ $f->comparison === 'custom' ? $f->compareStart?->toDateString() : '' }}" class="h-10 rounded-lg border-gray-300 text-sm"></label>
                <label class="block"><span class="mb-1 block text-xs font-semibold text-gray-500">sampai</span>
                    <input type="date" name="compare_to" value="{{ $f->comparison === 'custom' ? $f->compareEnd?->subSecond()->toDateString() : '' }}" class="h-10 rounded-lg border-gray-300 text-sm"></label>
            </div>
            <label data-cmp="outlets" @if($f->comparison !== 'outlets') hidden @endif class="block"><span class="mb-1 block text-xs font-semibold text-gray-500">Outlet pembanding</span>
                <select name="compare_outlet_ids[]" class="h-10 rounded-lg border-gray-300 text-sm">
                    @foreach($outlets as $o)<option value="{{ $o->id }}" @selected(in_array($o->id, $f->compareOutletIds))>{{ $o->name }}</option>@endforeach
                </select></label>
            @endif

            <div class="flex items-center gap-2">
                <button type="submit" class="h-10 rounded-lg bg-[#0C7856] px-4 text-sm font-semibold text-white hover:bg-[#0a6a4c] focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#0C7856]">Terapkan</button>
                <a href="{{ url()->current() }}" class="inline-flex h-10 items-center rounded-lg px-3 text-sm text-gray-500 hover:bg-gray-100">Reset</a>
            </div>

            <div class="ml-auto flex items-center gap-2">
                @if(config('pos.features.intelligence'))
                <a href="{{ route('reports.sales.ask', $q) }}" class="inline-flex h-10 items-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-semibold text-[#102C35] hover:border-[#0C7856]">Analisis dengan Tanya Yoka</a>
                @endif
                <div class="relative">
                    <button type="button" x-on:click="exportOpen = !exportOpen" aria-haspopup="true" :aria-expanded="exportOpen" class="h-10 rounded-lg border border-gray-300 bg-white px-3 text-sm font-semibold text-gray-700 hover:border-[#0C7856] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0C7856]">Ekspor</button>
                    <div x-show="exportOpen" x-cloak x-on:click.outside="exportOpen = false" class="absolute right-0 z-20 mt-1 w-44 rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
                        <a href="{{ route('reports.sales.export', $q) }}" class="block min-h-[40px] px-3 py-2.5 text-sm hover:bg-gray-50">Excel (.xlsx)</a>
                        <a href="{{ route('reports.sales.pdf', $q) }}" class="block min-h-[40px] px-3 py-2.5 text-sm hover:bg-gray-50">PDF</a>
                    </div>
                </div>
            </div>
        </div>
        <p class="mt-2 text-xs text-gray-500">
            {{ $f->start->isoFormat('D MMM Y') }} – {{ $f->end->subSecond()->isoFormat('D MMM Y') }} · zona waktu {{ $f->timezone }}
            · {{ count($f->outletIds) === count($f->allowedOutletIds) ? 'semua outlet' : count($f->outletIds).' outlet' }}
        </p>
    </form>

    @yield('report')
</div>
@endsection
