@props(['label', 'value', 'sub' => null, 'tone' => 'default', 'href' => null, 'hint' => null])
@php
    $tones = [
        'default' => 'border-[#E5E7EB] bg-white',
        'brand'   => 'border-[#16A34A]/40 bg-[#ECFDF3]',
        'warn'    => 'border-amber-200 bg-amber-50',
        'danger'  => 'border-red-200 bg-red-50',
    ];
    $labelTone = ['default' => 'text-[#64748B]', 'brand' => 'text-[#15803D]', 'warn' => 'text-amber-800', 'danger' => 'text-red-700'][$tone] ?? 'text-[#64748B]';
    $valueTone = ['danger' => 'text-red-700'][$tone] ?? 'text-[#0B1B3F]';
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif @if($hint) title="{{ $hint }}" @endif
    {{ $attributes->class(['block min-w-0 rounded-xl border p-4 transition-shadow', $tones[$tone] ?? $tones['default'], 'hover:shadow-md hover:border-[#16A34A]/60' => $href]) }}>
    <p class="text-xs font-semibold uppercase tracking-wide {{ $labelTone }}">{{ $label }}</p>
    <p class="mt-1.5 truncate text-base sm:text-lg xl:text-xl 2xl:text-2xl font-bold tabular-nums {{ $valueTone }}">{{ $value }}</p>
    @if($sub)<p class="mt-1 text-xs text-[#64748B]">{{ $sub }}</p>@endif
</{{ $tag }}>
