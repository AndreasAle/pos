@php
$navItems = [
    ['route'=>'settings.business', 'label'=>'Profil Bisnis'],
    ['route'=>'settings.outlet',   'label'=>'Outlet'],
    ['route'=>'settings.receipt',  'label'=>'Struk & Pajak'],
    ['route'=>'settings.qris',     'label'=>'🔳 QRIS'],
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
