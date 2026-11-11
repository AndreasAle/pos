@props(['route', 'icon' => 'circle'])

@php
$active = request()->routeIs($route) || request()->routeIs($route . '.*');


$path = \App\Support\NavIcons::path($icon);
@endphp

@php $label = trim(strip_tags((string) $slot)); @endphp
<a href="{{ route($route) }}"
   @mouseenter="tipShow($event, @js($label))" @mouseleave="tipHide()" @focus="tipShow($event, @js($label))" @blur="tipHide()"
   @if($active) aria-current="page" @endif
   class="flex items-center gap-2 h-8 px-2.5 rounded-md text-[13px] font-semibold transition-colors
          {{ $active ? 'bg-[#E8F7EF] text-[#0B8F5B]' : 'text-gray-800 hover:bg-[#F4F6F8] hover:text-gray-900' }}"
   :class="collapsed ? 'lg:justify-center lg:px-0' : ''">
    <svg class="w-[18px] h-[18px] flex-shrink-0 {{ $active ? 'text-[#0B8F5B]' : 'text-gray-600' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="stroke-width:1.75">
        {!! $path !!}
    </svg>
    <span class="truncate" :class="collapsed ? 'lg:hidden' : ''">{{ $slot }}</span>
</a>
