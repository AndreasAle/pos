@props(['title'])
{{-- Label grup (expanded) / garis pemisah (collapsed) --}}
<div class="pt-4 pb-1">
    <p class="px-3 text-xs font-semibold text-gray-400 mb-1" :class="collapsed ? 'lg:hidden' : ''">{{ $title }}</p>
    <div class="hidden border-t border-[#E5E7EB] mx-2" :class="collapsed ? 'lg:block' : ''"></div>
</div>
