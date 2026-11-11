@php
$navItems = [
    ['route'=>'reports.sales',     'label'=>'Penjualan'],
    ['route'=>'reports.products',  'label'=>'Produk Terlaris'],
    ['route'=>'reports.cashier',   'label'=>'Per Kasir'],
    ['route'=>'reports.shift',     'label'=>'Per Shift'],
    ['route'=>'reports.inventory', 'label'=>'Inventory'],
    ['route'=>'reports.profit',    'label'=>'Estimasi Profit'],
];
@endphp
<x-page-header eyebrow="Laporan" :title="trim(View::getSection('page-title', 'Laporan'))" subtitle="Pantau performa bisnis berdasarkan periode dan outlet."/>
<div class="flex gap-2 mb-5 overflow-x-auto pb-1 -mx-1 px-1">
    @foreach($navItems as $nav)
    <a href="{{ route($nav['route']) }}"
       class="whitespace-nowrap text-sm font-medium px-4 py-2 rounded-xl border transition-colors
              {{ request()->routeIs($nav['route']) ? 'bg-gradient-to-br from-emerald-500 to-teal-600 text-white border-transparent shadow-sm' : 'bg-white text-gray-600 border-gray-200 hover:border-emerald-300 hover:text-emerald-700' }}">
        {{ $nav['label'] }}
    </a>
    @endforeach
</div>
