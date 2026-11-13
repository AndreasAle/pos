@props(['variant' => 'primary', 'size' => 'md', 'href' => null, 'type' => 'button'])
@php
    $sizes = ['sm' => 'h-8 px-3 text-[13px]', 'md' => 'h-10 px-4 text-sm', 'lg' => 'h-12 px-5 text-sm'];
    $variants = [
        'primary'        => 'bg-[#16A34A] text-white shadow-sm hover:bg-[#15803D]',
        'dark'           => 'bg-[#0B1B3F] text-white shadow-sm hover:bg-[#13285A]',
        'secondary'      => 'border border-[#E5E7EB] bg-white text-[#0B1B3F] hover:bg-slate-50',
        'soft'           => 'border border-[#BBF7D0] bg-[#ECFDF3] text-[#15803D] hover:bg-[#DDF8E8]',
        'danger'         => 'bg-red-600 text-white shadow-sm hover:bg-red-700',
        'danger-outline' => 'border border-red-200 bg-white text-red-600 hover:bg-red-50',
        'ghost'          => 'text-[#64748B] hover:bg-slate-100 hover:text-[#0B1B3F]',
    ];
    $cls = 'inline-flex shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-lg font-semibold transition-colors disabled:opacity-50 ' . ($sizes[$size] ?? $sizes['md']) . ' ' . ($variants[$variant] ?? $variants['primary']);
@endphp
@if($href)
<a href="{{ $href }}" {{ $attributes->merge(['class' => $cls]) }}>{{ $slot }}</a>
@else
<button type="{{ $type }}" {{ $attributes->merge(['class' => $cls]) }}>{{ $slot }}</button>
@endif
