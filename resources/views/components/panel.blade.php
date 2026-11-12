@props(['title' => null, 'sub' => null, 'flush' => false])
<section {{ $attributes->class(['min-w-0 overflow-hidden rounded-xl border border-[#E5E7EB] bg-white']) }}>
    @if($title || isset($actions))
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#E5E7EB] px-5 py-4">
        <div class="min-w-0">
            @if($title)<h2 class="text-base font-semibold text-[#0B1B3F]">{{ $title }}</h2>@endif
            @if($sub)<p class="mt-0.5 text-sm text-[#64748B]">{{ $sub }}</p>@endif
        </div>
        @isset($actions)<div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
    </div>
    @endif
    <div @class(['p-5' => ! $flush])>{{ $slot }}</div>
</section>
