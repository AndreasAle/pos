@php
$navItems = [
    ['route' => 'reports.sales',       'label' => 'Penjualan'],
    ['route' => 'reports.products',    'label' => 'Produk Terlaris'],
    ['route' => 'reports.cashier',     'label' => 'Kasir'],
    ['route' => 'reports.shift',       'label' => 'Shift'],
    ['route' => 'reports.inventory',   'label' => 'Inventory'],
    ['route' => 'reports.profit',      'label' => 'Estimasi Profit'],
    ['route' => 'reports.profit-loss', 'label' => 'Laba Rugi'],
    ['route' => 'reports.tax',         'label' => 'Pajak'],
    ['route' => 'reports.returns',     'label' => 'Retur'],
];
@endphp
<nav aria-label="Jenis laporan" class="mb-4 flex max-w-full gap-1 overflow-x-auto rounded-lg border border-[#E5E7EB] bg-slate-50 p-1 y-scroll sm:inline-flex">
    @foreach($navItems as $nav)
    <a href="{{ route($nav['route']) }}" @if(request()->routeIs($nav['route'])) aria-current="page" @endif
       class="shrink-0 rounded-md px-3.5 py-2 text-[13px] font-semibold transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-[#16A34A]
              {{ request()->routeIs($nav['route']) ? 'bg-white text-[#16A34A] shadow-sm ring-1 ring-[#E5E7EB]' : 'text-[#64748B] hover:text-[#0B1B3F]' }}">{{ $nav['label'] }}</a>
    @endforeach
</nav>
