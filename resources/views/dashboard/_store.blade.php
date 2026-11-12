{{-- Ilustrasi toko statis. --}}
<svg viewBox="0 0 260 220" class="w-full h-full" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <defs>
        <linearGradient id="st-wall" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#FFFFFF"/><stop offset="1" stop-color="#E7F8EE"/></linearGradient>
        <linearGradient id="st-green" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#22C55E"/><stop offset="1" stop-color="#15803D"/></linearGradient>
        <linearGradient id="st-glass" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#DFF3FF"/><stop offset="1" stop-color="#B8E2FA"/></linearGradient>
        <filter id="st-shadow" x="-20%" y="-20%" width="140%" height="150%"><feDropShadow dx="0" dy="8" stdDeviation="8" flood-color="#0B8F5B" flood-opacity=".18"/></filter>
    </defs>

    {{-- tanah / bayangan --}}
    <ellipse cx="130" cy="206" rx="104" ry="8" fill="#0B8F5B" opacity=".10"/>

    <g filter="url(#st-shadow)">
        {{-- bangunan --}}
        <rect x="52" y="82" width="156" height="118" rx="6" fill="url(#st-wall)" stroke="#D1F0DD"/>
        {{-- papan atas --}}
        <rect x="46" y="70" width="168" height="16" rx="4" fill="#15803D"/>
        {{-- kanopi bergaris --}}
        <g>
            <path d="M44 74h172l10 30H34z" fill="#fff"/>
            <path d="M44 74h28.7l-3.3 30H34zM101.3 74h28.7v30h-28.7zM159.3 74H188l7.6 30h-28.7z" fill="url(#st-green)"/>
            <path d="M72.7 74h28.6l-1.6 30H69.4zM130 74h29.3l-1.6 30H131.6zM188 74h28l10 30h-30.4z" fill="#fff"/>
            {{-- lengkung bawah kanopi --}}
            <g>
                <path d="M34 104h32l-1 8a15.2 15.2 0 01-30 0z" fill="#16A34A"/>
                <path d="M66 104h32l-1 8a15.2 15.2 0 01-30 0z" fill="#fff"/>
                <path d="M98 104h32l-1 8a15.2 15.2 0 01-30 0z" fill="#16A34A"/>
                <path d="M130 104h32l-1 8a15.2 15.2 0 01-30 0z" fill="#fff"/>
                <path d="M162 104h32l-1 8a15.2 15.2 0 01-30 0z" fill="#16A34A"/>
                <path d="M194 104h32l-1 8a15.2 15.2 0 01-30 0z" fill="#fff"/>
            </g>
            <path d="M44 74h172" stroke="#0F6B33" stroke-width="2" opacity=".35"/>
        </g>
        {{-- jendela etalase --}}
        <rect x="64" y="128" width="78" height="58" rx="5" fill="url(#st-glass)" stroke="#9AD3F0"/>
        <path d="M72 128l22 58M92 128l22 58" stroke="#fff" stroke-width="5" opacity=".35"/>
        <rect x="68" y="168" width="70" height="4" rx="2" fill="#0B8F5B" opacity=".18"/>
        {{-- produk di etalase --}}
        <rect x="76" y="154" width="12" height="14" rx="2" fill="#16A34A"/><rect x="92" y="148" width="12" height="20" rx="2" fill="#A855F7"/><rect x="108" y="156" width="12" height="12" rx="2" fill="#F59E0B"/><rect x="123" y="151" width="10" height="17" rx="2" fill="#0EA5E9"/>
        {{-- papan OPEN --}}
        <g>
            <rect x="82" y="92" width="46" height="16" rx="4" fill="#0B1B3F"/>
            <text x="105" y="104" text-anchor="middle" font-family="Inter,Arial,sans-serif" font-size="10" font-weight="700" fill="#4ADE80">OPEN</text>
        </g>
        {{-- pintu --}}
        <rect x="156" y="128" width="38" height="72" rx="4" fill="#0F766E"/>
        <rect x="162" y="134" width="26" height="40" rx="3" fill="#99F6E4" opacity=".55"/>
        <circle cx="187" cy="168" r="2.4" fill="#FDE68A"/>
        <rect x="150" y="200" width="50" height="4" rx="2" fill="#0B8F5B" opacity=".25"/>
    </g>

    {{-- tanaman --}}
    <g transform="translate(214 150)">
        <g>
            <path d="M14 44C14 28 8 18 0 12c10-2 17 6 14 20z" fill="#22C55E"/>
            <path d="M14 44C14 24 20 12 30 6c-4 14-4 26-16 38z" fill="#16A34A"/>
            <path d="M14 44c-1-12 0-24 6-34-1 12-2 22-6 34z" fill="#4ADE80"/>
        </g>
        <path d="M2 44h24l-3 22H5z" fill="#F59E0B"/><rect x="0" y="40" width="28" height="6" rx="2" fill="#FBBF24"/>
    </g>

</svg>
