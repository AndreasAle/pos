@props(['label', 'value', 'icon' => null, 'color' => 'emerald', 'sub' => null])

@php
$colors = [
    'emerald' => ['bg' => 'bg-emerald-50', 'icon' => 'text-emerald-600', 'ring' => 'ring-emerald-100'],
    'blue'    => ['bg' => 'bg-blue-50',    'icon' => 'text-blue-600',    'ring' => 'ring-blue-100'],
    'purple'  => ['bg' => 'bg-purple-50',  'icon' => 'text-purple-600',  'ring' => 'ring-purple-100'],
    'orange'  => ['bg' => 'bg-orange-50',  'icon' => 'text-orange-600',  'ring' => 'ring-orange-100'],
];
$c = $colors[$color] ?? $colors['emerald'];
@endphp

<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 flex items-start gap-4">
    @if($icon)
    <div class="flex-shrink-0 w-11 h-11 rounded-xl {{ $c['bg'] }} ring-2 {{ $c['ring'] }} flex items-center justify-center">
        {!! $icon !!}
    </div>
    @endif
    <div class="flex-1 min-w-0">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ $label }}</p>
        <p class="mt-1 text-2xl font-bold text-gray-900 truncate">{{ $value }}</p>
        @if($sub)
        <p class="mt-0.5 text-xs text-gray-500">{{ $sub }}</p>
        @endif
    </div>
</div>
