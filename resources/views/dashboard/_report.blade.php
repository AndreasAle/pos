{{-- Ilustrasi laptop dengan grafik batang yang tumbuh. --}}
<svg viewBox="0 0 240 150" class="w-full h-full" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <defs>
        <linearGradient id="rp-bar" x1="0" y1="1" x2="0" y2="0"><stop offset="0" stop-color="#16A34A"/><stop offset="1" stop-color="#4ADE80"/></linearGradient>
        <filter id="rp-sh" x="-20%" y="-20%" width="140%" height="150%"><feDropShadow dx="0" dy="6" stdDeviation="6" flood-color="#0B1B3F" flood-opacity=".18"/></filter>
    </defs>
    {{-- daun --}}
    <g class="anim-sway" style="transform-origin:34px 140px"><path d="M34 140C30 118 20 108 6 104c6 16 12 28 28 36z" fill="#22C55E"/><path d="M34 140c2-20 10-32 24-38-2 16-8 28-24 38z" fill="#16A34A"/></g>
    <g class="anim-sway" style="transform-origin:214px 142px;animation-delay:.8s"><path d="M214 142c4-20 14-30 26-34-4 16-10 26-26 34z" fill="#22C55E"/><path d="M214 142c-4-16-14-26-28-28 4 14 12 22 28 28z" fill="#16A34A"/></g>

    <g class="anim-float" filter="url(#rp-sh)">
        {{-- layar --}}
        <rect x="52" y="18" width="136" height="88" rx="8" fill="#0B1B3F"/>
        <rect x="58" y="24" width="124" height="76" rx="4" fill="#fff"/>
        <rect x="64" y="30" width="34" height="5" rx="2.5" fill="#CBD5E1"/>
        <rect x="64" y="39" width="20" height="4" rx="2" fill="#E2E8F0"/>
        {{-- grid --}}
        <path d="M64 92h112M64 78h112M64 64h112" stroke="#EEF2F7"/>
        {{-- batang --}}
        <g style="transform-origin:bottom">
            <rect class="anim-bar" style="animation-delay:0s"    x="72"  y="76" width="10" height="16" rx="2" fill="url(#rp-bar)"/>
            <rect class="anim-bar" style="animation-delay:.15s"  x="88"  y="68" width="10" height="24" rx="2" fill="url(#rp-bar)"/>
            <rect class="anim-bar" style="animation-delay:.3s"   x="104" y="72" width="10" height="20" rx="2" fill="url(#rp-bar)"/>
            <rect class="anim-bar" style="animation-delay:.45s"  x="120" y="56" width="10" height="36" rx="2" fill="url(#rp-bar)"/>
            <rect class="anim-bar" style="animation-delay:.6s"   x="136" y="48" width="10" height="44" rx="2" fill="url(#rp-bar)"/>
            <rect class="anim-bar" style="animation-delay:.75s"  x="152" y="38" width="10" height="54" rx="2" fill="url(#rp-bar)"/>
        </g>
        <path class="anim-line" d="M70 70L92 62L108 66L125 50L141 42L157 32" stroke="#A855F7" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" pathLength="1"/>
        {{-- badge centang --}}
        <g class="anim-pop" style="transform-origin:176px 30px"><circle cx="176" cy="30" r="12" fill="#16A34A"/><path d="M170.5 30.5l4 4 7-8" stroke="#fff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></g>
        {{-- alas --}}
        <path d="M36 110h168l-8 14a6 6 0 01-5 3H49a6 6 0 01-5-3z" fill="#CBD5E1"/>
        <rect x="100" y="110" width="40" height="5" rx="2.5" fill="#94A3B8"/>
    </g>
</svg>
