@php
    $subscription = $currentBusiness?->activeSubscription;
    $isOwner = auth()->user()->isOwner();
    $supportUrl = config('pos.support_url') ?: 'mailto:'.config('mail.from.address');

    $endsAt = $subscription?->ends_at?->copy()->endOfDay();
    $daysRemaining = $endsAt && $endsAt->isFuture()
        ? max(0, (int) now()->startOfDay()->diffInDays($endsAt->copy()->startOfDay(), false))
        : 0;
@endphp

@if($subscription && in_array($subscription->status, ['trial', 'active']))
    <div class="border-b {{ $subscription->status === 'trial' ? 'border-amber-200 bg-[#FFF6DA]' : 'border-emerald-200 bg-[#EAF8F0]' }}">
        <div class="flex min-h-11 items-center justify-between gap-4 px-4 py-2 sm:px-6">
            <div class="flex min-w-0 items-center gap-2.5 text-sm text-[#0B1B3F]">
                <svg class="h-4 w-4 shrink-0 {{ $subscription->status === 'trial' ? 'text-amber-700' : 'text-[#0B8F5B]' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>

                @if($subscription->status === 'trial')
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
                @else
                    <p class="truncate">
                        <span class="font-semibold">Paket {{ $subscription->plan?->name ?? 'Yoka POS' }} aktif</span>
                        @if($endsAt)
                            <span class="hidden sm:inline"> hingga {{ $endsAt->translatedFormat('d F Y') }}</span>
                        @else
                            <span class="hidden sm:inline"> tanpa batas waktu</span>
                        @endif
                    </p>
                @endif
            </div>

            @if($isOwner)
                @if(config('pos.features.subscription') && Route::has('saas.plans'))
                    <a href="{{ route('saas.plans') }}" class="shrink-0 border border-current px-3 py-1.5 text-xs font-semibold text-[#0B5D3B] hover:bg-white/60">
                        Lihat Paket
                    </a>
                @else
                    <a href="{{ $supportUrl }}" target="_blank" rel="noopener" class="shrink-0 border border-current px-3 py-1.5 text-xs font-semibold text-[#0B5D3B] hover:bg-white/60">
                        Hubungi Yoka
                    </a>
                @endif
            @endif
        </div>
    </div>
@endif
