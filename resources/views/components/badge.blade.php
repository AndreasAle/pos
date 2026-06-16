@props(['color' => 'gray'])

@php
$colors = [
    'green'  => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    'red'    => 'bg-red-50 text-red-700 border-red-200',
    'yellow' => 'bg-yellow-50 text-yellow-700 border-yellow-200',
    'blue'   => 'bg-blue-50 text-blue-700 border-blue-200',
    'gray'   => 'bg-gray-100 text-gray-600 border-gray-200',
    'purple' => 'bg-purple-50 text-purple-700 border-purple-200',
    'orange' => 'bg-orange-50 text-orange-700 border-orange-200',
];
$cls = $colors[$color] ?? $colors['gray'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium $cls"]) }}>
    {{ $slot }}
</span>
