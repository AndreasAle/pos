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
<div class="flex flex-wrap gap-2 mb-5">
    @foreach($navItems as $nav)
    <a href="{{ route($nav['route']) }}"
       class="text-sm font-medium px-4 py-2 rounded-xl border transition-colors
              {{ request()->routeIs($nav['route']) ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-gray-600 border-gray-300 hover:border-emerald-400' }}">
        {{ $nav['label'] }}
    </a>
    @endforeach
</div>
