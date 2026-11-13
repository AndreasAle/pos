@php
    $navItems = [
        ['route' => 'settings.business', 'label' => 'Profil Bisnis'],
        ['route' => 'settings.outlet',   'label' => 'Outlet'],
        ['route' => 'settings.receipt',  'label' => 'Struk & Pajak'],
        ['route' => 'settings.qris',     'label' => 'QRIS'],
    ];
@endphp
<div class="fade-up mb-4 flex max-w-full gap-1 self-start overflow-x-auto rounded-lg border border-[#E5E7EB] bg-slate-50 p-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:inline-flex" role="tablist" style="--i:0">
    @foreach($navItems as $nav)
    <a href="{{ route($nav['route']) }}" role="tab"
       class="inline-flex h-9 shrink-0 items-center rounded-md px-3.5 text-[13px] font-semibold transition-all
              {{ request()->routeIs($nav['route']) ? 'bg-white text-[#16A34A] shadow-sm ring-1 ring-[#E5E7EB]' : 'text-[#64748B] hover:text-[#0B1B3F]' }}">{{ $nav['label'] }}</a>
    @endforeach
</div>
