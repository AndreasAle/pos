{{-- Ilustrasi perangkat kasir (SVG buatan sendiri, bukan foto produk). kind: printer, printer80, scanner, tablet, stand, mpos, drawer, paper --}}
@props(['kind', 'class' => 'h-full w-full'])
@php
    // Foto produk asli (latar dihapus) di public/images/hardware. Bila belum ada fotonya, pakai ilustrasi.
    $photo = ['printer' => 'printer58', 'scanner' => 'scanner', 'tablet' => 'tablet', 'stand' => 'stand', 'mpos' => 'mpos', 'drawer' => 'drawer', 'paper' => 'paper'][$kind] ?? null;
    $labels = ['printer' => 'Printer struk 58mm', 'printer80' => 'Printer struk 80mm', 'scanner' => 'Barcode scanner', 'tablet' => 'Tablet kasir', 'stand' => 'Stand tablet', 'mpos' => 'Mesin kasir Android', 'drawer' => 'Laci kas', 'paper' => 'Kertas struk 58mm'];
@endphp
@if($photo && is_file(public_path("images/hardware/{$photo}.webp")))
<img src="{{ asset("images/hardware/{$photo}.webp") }}" alt="{{ $labels[$kind] ?? $kind }}" loading="lazy" decoding="async" class="{{ $class }} object-contain">
@else
<svg viewBox="0 0 200 160" class="{{ $class }}" role="img" aria-label="Ilustrasi {{ $kind }}" xmlns="http://www.w3.org/2000/svg">
@switch($kind)
@case('printer')
    <ellipse cx="100" cy="140" rx="62" ry="7" fill="#0B1B3F" opacity=".08"/>
    <path d="M78 34h44v40H78z" fill="#fff" stroke="#CBD5E1" stroke-width="2"/>
    <path d="M84 44h32M84 52h24M84 60h28" stroke="#94A3B8" stroke-width="3" stroke-linecap="round"/>
    <rect x="46" y="64" width="108" height="70" rx="14" fill="#1E293B"/>
    <rect x="46" y="64" width="108" height="26" rx="12" fill="#334155"/>
    <rect x="70" y="70" width="60" height="6" rx="3" fill="#0F172A"/>
    <rect x="58" y="104" width="22" height="8" rx="4" fill="#16A34A"/>
    <circle cx="136" cy="108" r="5" fill="#475569"/>
    @break
@case('printer80')
    <ellipse cx="100" cy="140" rx="72" ry="7" fill="#0B1B3F" opacity=".08"/>
    <path d="M66 26h68v46H66z" fill="#fff" stroke="#CBD5E1" stroke-width="2"/>
    <path d="M74 38h52M74 47h40M74 56h46" stroke="#94A3B8" stroke-width="3" stroke-linecap="round"/>
    <rect x="36" y="62" width="128" height="74" rx="14" fill="#0F172A"/>
    <rect x="36" y="62" width="128" height="28" rx="12" fill="#1E293B"/>
    <rect x="60" y="68" width="80" height="7" rx="3.5" fill="#020617"/>
    <rect x="50" y="106" width="26" height="8" rx="4" fill="#16A34A"/>
    <circle cx="146" cy="110" r="5" fill="#475569"/>
    @break
@case('scanner')
    <ellipse cx="100" cy="142" rx="44" ry="6" fill="#0B1B3F" opacity=".08"/>
    <path d="M70 30c0-8 6-12 14-12h42c10 0 16 6 14 16l-6 30c-2 8-8 12-16 12h-14l-6 50c-1 8-6 12-14 12h-4c-8 0-12-6-11-14l7-56c-4-4-6-10-6-16z" fill="#1E293B"/>
    <path d="M84 22h42c8 0 12 4 12 10" stroke="#334155" stroke-width="6" stroke-linecap="round" fill="none"/>
    <rect x="96" y="76" width="10" height="16" rx="4" fill="#16A34A"/>
    <path d="M140 40l38-10M140 48l40 0M140 56l38 10" stroke="#EF4444" stroke-width="2" stroke-linecap="round" opacity=".55"/>
    @break
@case('tablet')
    <ellipse cx="100" cy="142" rx="70" ry="6" fill="#0B1B3F" opacity=".08"/>
    <rect x="28" y="24" width="144" height="108" rx="12" fill="#0F172A"/>
    <rect x="36" y="32" width="128" height="92" rx="6" fill="#F8FAFC"/>
    <rect x="44" y="40" width="72" height="8" rx="4" fill="#0B1B3F"/>
    <rect x="44" y="56" width="22" height="22" rx="5" fill="#DCFCE7"/>
    <rect x="70" y="56" width="22" height="22" rx="5" fill="#DCFCE7"/>
    <rect x="96" y="56" width="22" height="22" rx="5" fill="#DCFCE7"/>
    <rect x="44" y="82" width="22" height="22" rx="5" fill="#DCFCE7"/>
    <rect x="70" y="82" width="22" height="22" rx="5" fill="#DCFCE7"/>
    <rect x="96" y="82" width="22" height="22" rx="5" fill="#DCFCE7"/>
    <rect x="124" y="40" width="32" height="76" rx="5" fill="#fff" stroke="#E2E8F0"/>
    <rect x="128" y="102" width="24" height="10" rx="3" fill="#16A34A"/>
    @break
@case('stand')
    <ellipse cx="100" cy="142" rx="50" ry="6" fill="#0B1B3F" opacity=".08"/>
    <path d="M72 136h56l-6-10H78z" fill="#94A3B8"/>
    <rect x="94" y="70" width="12" height="58" rx="4" fill="#CBD5E1"/>
    <path d="M64 30h72l-8 44H72z" fill="#E2E8F0" stroke="#94A3B8" stroke-width="2"/>
    @break
@case('mpos')
    <ellipse cx="100" cy="144" rx="40" ry="6" fill="#0B1B3F" opacity=".08"/>
    <path d="M86 14h28v26H86z" fill="#fff" stroke="#CBD5E1" stroke-width="2"/>
    <path d="M92 22h16M92 29h12" stroke="#94A3B8" stroke-width="2.5" stroke-linecap="round"/>
    <rect x="66" y="28" width="68" height="112" rx="14" fill="#1E293B"/>
    <rect x="66" y="28" width="68" height="22" rx="12" fill="#16A34A"/>
    <rect x="72" y="56" width="56" height="74" rx="6" fill="#F8FAFC"/>
    <rect x="78" y="62" width="30" height="6" rx="3" fill="#0B1B3F"/>
    <rect x="78" y="74" width="20" height="16" rx="4" fill="#DCFCE7"/>
    <rect x="102" y="74" width="20" height="16" rx="4" fill="#DCFCE7"/>
    <rect x="78" y="94" width="20" height="16" rx="4" fill="#DCFCE7"/>
    <rect x="102" y="94" width="20" height="16" rx="4" fill="#DCFCE7"/>
    <rect x="78" y="116" width="44" height="8" rx="3" fill="#16A34A"/>
    @break
@case('drawer')
    <ellipse cx="100" cy="142" rx="74" ry="7" fill="#0B1B3F" opacity=".08"/>
    <path d="M30 60l20-20h100l20 20z" fill="#334155"/>
    <rect x="30" y="60" width="140" height="56" rx="6" fill="#1E293B"/>
    <path d="M38 116h124l8 20H30z" fill="#0F172A"/>
    <rect x="46" y="118" width="108" height="10" rx="2" fill="#334155"/>
    <path d="M60 60v-6M80 60v-6M100 60v-6M120 60v-6M140 60v-6" stroke="#94A3B8" stroke-width="3" stroke-linecap="round"/>
    <rect x="92" y="84" width="16" height="10" rx="3" fill="#94A3B8"/>
    <circle cx="100" cy="89" r="2" fill="#1E293B"/>
    @break
@case('paper')
    <ellipse cx="100" cy="140" rx="60" ry="6" fill="#0B1B3F" opacity=".08"/>
    @foreach([[62, 96], [100, 96], [138, 96], [81, 66], [119, 66]] as [$x, $y])
        <circle cx="{{ $x }}" cy="{{ $y }}" r="18" fill="#fff" stroke="#CBD5E1" stroke-width="2"/>
        <circle cx="{{ $x }}" cy="{{ $y }}" r="5" fill="#E2E8F0"/>
    @endforeach
    @break
@endswitch
</svg>
@endif
