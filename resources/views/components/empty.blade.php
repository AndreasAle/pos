@props(['title', 'text' => null, 'icon' => 'M9 12h6m-6 4h6M7 4h10a2 2 0 012 2v14l-3-2-2 2-2-2-2 2-2-2-3 2V6a2 2 0 012-2z'])
<div {{ $attributes->class(['flex flex-col items-center px-6 py-12 text-center']) }}>
    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#ECFDF3] text-[#16A34A]">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
    </span>
    <p class="mt-3 text-base font-semibold text-[#0B1B3F]">{{ $title }}</p>
    @if($text)<p class="mt-1 max-w-sm text-sm text-[#64748B]">{{ $text }}</p>@endif
    @if(trim((string) $slot) !== '')<div class="mt-4 flex flex-wrap justify-center gap-2">{{ $slot }}</div>@endif
</div>
