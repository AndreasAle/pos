@props(['title', 'back' => null, 'backLabel' => 'Kembali', 'sub' => null])
<div {{ $attributes->class(['fade-up mb-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between']) }}>
    <div class="min-w-0">
        @if($back)
        <a href="{{ $back }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-[#64748B] transition-colors hover:text-[#16A34A]">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            {{ $backLabel }}
        </a>
        @endif
        <div class="@if($back) mt-2 @endif flex flex-wrap items-center gap-3">
            <h2 class="text-xl font-bold tracking-tight sm:text-2xl">{{ $title }}</h2>
            @isset($badge){{ $badge }}@endisset
        </div>
        @if($sub)<p class="mt-1 text-sm text-[#64748B]">{{ $sub }}</p>@endif
    </div>
    @isset($actions)<div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
</div>
