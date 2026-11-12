@php
    $subscription = $currentBusiness?->activeSubscription;
    $isOwner = auth()->user()->isOwner();
    $supportUrl = config('pos.support_url') ?: 'mailto:'.config('mail.from.address');

    $endsAt = $subscription?->ends_at?->copy()->endOfDay();
    $daysRemaining = $endsAt && $endsAt->isFuture()
        ? max(0, (int) now()->startOfDay()->diffInDays($endsAt->copy()->startOfDay(), false))
        : 0;
@endphp

@if($subscription?->status === 'trial')
    <div class="fixed inset-x-0 top-0 z-[60] border-b border-amber-200 bg-[#FFF7DD] shadow-[0_1px_0_rgba(15,23,42,.03)]">
        <div class="flex min-h-12 items-center justify-between gap-4 px-4 py-2 sm:px-6">
            <div class="flex min-w-0 items-center gap-2.5 text-sm text-[#0B1B3F]">
                <svg class="h-4 w-4 shrink-0 text-amber-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>

                <p class="truncate">
                    <span class="font-semibold">Masa coba gratis</span>
                    @if($endsAt)
                        <span class="hidden sm:inline"> berakhir {{ $endsAt->translatedFormat('d F Y') }}</span>
                        <span class="mx-1.5 text-slate-400">&middot;</span>
                        <span class="font-semibold {{ $daysRemaining <= 2 ? 'text-red-700' : 'text-amber-800' }}">
                            {{ $daysRemaining > 0 ? $daysRemaining.' hari tersisa' : 'berakhir hari ini' }}
                        </span>
                    @endif
                </p>
            </div>

            @if($isOwner)
                @if(Route::has('saas.plans'))
                    <a href="{{ route('saas.plans') }}" class="shrink-0 rounded-md border border-[#0B5D3B] px-4 py-2 text-xs font-semibold text-[#0B5D3B] transition hover:bg-white/70">
                        Pilih Paket
                    </a>
                @else
                    <a href="{{ $supportUrl }}" target="_blank" rel="noopener" class="shrink-0 rounded-md border border-[#0B5D3B] px-4 py-2 text-xs font-semibold text-[#0B5D3B] transition hover:bg-white/70">
                        Hubungi Yoka
                    </a>
                @endif
            @endif
        </div>
    </div>
@endif
