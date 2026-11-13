{{-- Tab navigasi modul inventori. Butuh $active: ingredients | movements | recipes --}}
@php
    $tabs = [
        'ingredients' => ['Bahan Baku', route('ingredients.index')],
        'movements'   => ['Pergerakan Stok', route('inventory.movements')],
        'recipes'     => ['Resep Produk', route('recipes.index')],
    ];
@endphp
<div class="flex gap-1 p-1 rounded-lg bg-slate-50 border border-[#E5E7EB] overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden self-start" role="tablist">
    @foreach($tabs as $key => [$label, $url])
    <a href="{{ $url }}" role="tab"
       class="shrink-0 h-9 px-3.5 inline-flex items-center rounded-md text-[13px] font-semibold transition-all
              {{ $active === $key ? 'bg-white text-[#16A34A] shadow-sm ring-1 ring-[#E5E7EB]' : 'text-[#64748B] hover:text-[#0B1B3F]' }}">{{ $label }}</a>
    @endforeach
</div>
