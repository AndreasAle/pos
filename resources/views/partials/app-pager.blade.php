{{-- Pager tenang untuk mode aplikasi: sebelumnya · x / y · berikutnya. --}}
@if($paginator->lastPage() > 1)
<div class="px-5 sm:px-0 pt-8 pb-4 flex items-center justify-between">
    @if($paginator->onFirstPage())
        <span class="h-11 px-4 inline-flex items-center text-[15px] font-semibold text-[#CBD5E1]">Sebelumnya</span>
    @else
        <a href="{{ $paginator->previousPageUrl() }}" class="h-11 px-4 -ml-4 inline-flex items-center gap-1 text-[15px] font-semibold text-[#0B1B3F] active:bg-[#F1F5F9] rounded-full">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>Sebelumnya</a>
    @endif
    <span class="text-[13px] text-[#94A3B8] tabular-nums">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
    @if($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" class="h-11 px-4 -mr-4 inline-flex items-center gap-1 text-[15px] font-semibold text-[#0B1B3F] active:bg-[#F1F5F9] rounded-full">
            Berikutnya<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></a>
    @else
        <span class="h-11 px-4 inline-flex items-center text-[15px] font-semibold text-[#CBD5E1]">Berikutnya</span>
    @endif
</div>
@endif
