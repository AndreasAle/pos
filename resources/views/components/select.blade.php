@props(['name' => null, 'compact' => false])
<div class="relative">
    <select @if($name) name="{{ $name }}" id="{{ $name }}" @endif
        {{ $attributes->class([
            'w-full appearance-none rounded-lg border border-[#E5E7EB] bg-white pl-3.5 pr-10 text-sm text-[#0B1B3F] transition focus:border-[#16A34A] focus:outline-none focus:ring-2 focus:ring-[#16A34A]/30',
            $compact ? 'h-10 font-medium' : 'h-11',
        ]) }}>{{ $slot }}</select>
    <svg class="pointer-events-none absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#64748B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
</div>
