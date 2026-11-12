@props(['tone' => 'gray'])
@php
    $map = [
        'green' => 'bg-[#ECFDF3] text-[#15803D] ring-[#BBF7D0]',
        'amber' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'red'   => 'bg-red-50 text-red-700 ring-red-200',
        'blue'  => 'bg-sky-50 text-sky-700 ring-sky-200',
        'gray'  => 'bg-slate-100 text-slate-600 ring-slate-200',
    ];
@endphp
<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset', $map[$tone] ?? $map['gray']]) }}><span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>{{ $slot }}</span>
