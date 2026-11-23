{{--
    Sidebar Yoka AI: percakapan dikelompokkan per waktu + menu akun di bawah.
    Dipakai Tanya Yoka dan halaman Kredit Yoka AI. Butuh state Alpine dari induk:
    historyOpen, search, searchOpen, deleteConversation(), toggleAgent().
--}}
@php
    $groups = $conversations->groupBy(function ($c) {
        $d = $c->updated_at;
        return match (true) {
            $d->isToday() => 'Hari ini',
            $d->isYesterday() => 'Kemarin',
            $d->gte(now()->subDays(7)) => '7 hari terakhir',
            $d->gte(now()->subDays(30)) => '30 hari terakhir',
            default => 'Lebih lama',
        };
    });
    $me = auth()->user();
    $sub = $me->business?->activeSubscription()->with('plan')->first();
    $planLabel = match (true) {
        $sub?->status === 'trial' => 'Masa coba',
        (bool) $sub?->plan => $sub->plan->name,
        default => 'Belum berlangganan',
    };
    $supportUrl = config('pos.support_url') ?: 'mailto:'.config('mail.from.address');
    $activeId = $activeConversation?->id;
@endphp

<aside class="absolute lg:relative z-20 inset-y-0 left-0 flex w-[272px] shrink-0 flex-col border-r border-[#EEF0F2] bg-[#FAFBFA] transition-transform lg:translate-x-0"
       :class="historyOpen ? 'translate-x-0 shadow-2xl lg:shadow-none' : '-translate-x-full'">
    <div class="space-y-0.5 px-3 pt-3">
        <a href="{{ route('intelligence.index') }}" class="flex h-10 items-center gap-3 rounded-lg px-3 text-[14px] font-bold text-[#0B1B3F] transition hover:bg-white">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
            Percakapan baru
        </a>
        <button type="button" @click="searchOpen = !searchOpen; $nextTick(() => searchOpen && $refs.search.focus())" class="flex h-10 w-full items-center gap-3 rounded-lg px-3 text-left text-[14px] font-bold text-[#0B1B3F] transition hover:bg-white">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M20 20l-3.5-3.5"/></svg>
            Cari
        </button>
        <div x-show="searchOpen" x-cloak class="px-1 pb-1 pt-0.5"><input x-ref="search" x-model="search" type="text" placeholder="Cari percakapan..." class="h-9 w-full rounded-lg border border-[#E5E7EB] bg-white px-3 text-[13px] font-semibold text-slate-700 placeholder:font-medium focus:border-[#65B98E] focus:outline-none focus:ring-2 focus:ring-[#E1F5EA]"></div>
    </div>

    <div class="mt-2 min-h-0 flex-1 overflow-y-auto px-2 pb-2">
        @forelse($groups as $label => $items)
            <div class="pt-3" x-data="{ titles: @js($items->map(fn ($c) => \Illuminate\Support\Str::lower($c->title ?: 'percakapan baru'))->values()) }"
                 x-show="!search || titles.some(t => t.includes(search.toLowerCase()))">
                <p class="px-3 pb-1 text-[12px] font-semibold text-slate-400">{{ $label }}</p>
                @foreach($items as $conversation)
                    <div id="conversation-{{ $conversation->id }}" class="group relative" data-t="{{ \Illuminate\Support\Str::lower($conversation->title ?: 'percakapan baru') }}" x-show="!search || $el.dataset.t.includes(search.toLowerCase())">
                        <a href="{{ route('intelligence.index', ['conversation' => $conversation->id]) }}" title="{{ $conversation->title }}"
                           class="block truncate rounded-lg py-2 pl-3 pr-9 text-[14px] transition {{ $activeId === $conversation->id ? 'bg-[#E8F5ED] font-bold text-[#087A4E]' : 'font-medium text-slate-700 hover:bg-white' }}">{{ $conversation->title ?: 'Percakapan baru' }}</a>
                        <button type="button" @click.prevent="deleteConversation({{ $conversation->id }})" title="Hapus percakapan"
                                class="absolute right-1.5 top-1.5 h-7 w-7 rounded-md text-slate-400 opacity-0 hover:bg-red-50 hover:text-red-600 focus:opacity-100 group-hover:opacity-100">&times;</button>
                    </div>
                @endforeach
            </div>
        @empty
            <p class="px-3 py-4 text-[13px] leading-relaxed text-slate-500">Belum ada percakapan. Mulai dengan pertanyaan tentang penjualan atau operasional bisnis.</p>
        @endforelse
    </div>

    {{-- Menu akun --}}
    <div class="relative border-t border-[#EEF0F2] p-2" x-data="{ menu: false, view: 'menu' }" @click.outside="menu = false; view = 'menu'" @keydown.escape.window="menu = false; view = 'menu'">
        <div x-show="menu" x-cloak x-transition.origin.bottom
             class="absolute bottom-full left-2 right-2 z-30 mb-2 overflow-hidden rounded-xl border border-[#E5E7EB] bg-white py-1.5 text-[14px] shadow-[0_12px_40px_rgba(15,23,42,.14)]">

            <div x-show="view === 'menu'">
                <p class="truncate px-4 pb-1.5 pt-1 text-[12px] font-semibold text-slate-400">{{ $me->email }}</p>
                @if($credits)
                    <a href="{{ route('ai-credits.index') }}" class="flex items-center gap-3 px-4 py-2 text-[#0B1B3F] hover:bg-[#F4FBF7]">
                        <svg class="h-[18px] w-[18px] text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8"/><path stroke-linecap="round" d="M12 8v8M9.5 10.5h5"/></svg>
                        <span class="flex-1">Penggunaan</span>
                        <span class="text-[13px] font-semibold {{ $credits['total'] < 1 ? 'text-red-600' : 'text-slate-500' }}">{{ number_format($credits['total'], 0, ',', '.') }}</span>
                    </a>
                @endif
                <button type="button" @click="view = 'insights'" class="flex w-full items-center gap-3 px-4 py-2 text-left text-[#0B1B3F] hover:bg-[#F4FBF7]">
                    <svg class="h-[18px] w-[18px] text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l1.5 4.5L18 9l-4.5 1.5L12 15l-1.5-4.5L6 9l4.5-1.5L12 3z"/></svg>
                    <span class="flex-1">Insight bisnis</span>
                    @if($businessInsights->count())<span class="rounded-full bg-amber-100 px-2 py-0.5 text-[12px] font-bold text-amber-800">{{ $businessInsights->count() }}</span>@endif
                    <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </button>
                @if(config('pos.features.intelligence_agents') && $intelligenceAgents->isNotEmpty())
                    <button type="button" @click="view = 'agents'" class="flex w-full items-center gap-3 px-4 py-2 text-left text-[#0B1B3F] hover:bg-[#F4FBF7]">
                        <svg class="h-[18px] w-[18px] text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8"/><path stroke-linecap="round" d="M12 8v4l3 2"/></svg>
                        <span class="flex-1">Agen terjadwal</span>
                        <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                @endif
                <div class="my-1.5 border-t border-[#EEF0F2]"></div>
                <a href="{{ $supportUrl }}" target="_blank" rel="noopener" class="flex items-center gap-3 px-4 py-2 text-[#0B1B3F] hover:bg-[#F4FBF7]">
                    <svg class="h-[18px] w-[18px] text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8"/><path stroke-linecap="round" stroke-linejoin="round" d="M9.8 9.8a2.3 2.3 0 114 1.6c-.8.5-1.8 1-1.8 2.1M12 16.5v.01"/></svg>
                    Bantuan
                </a>
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-4 py-2 text-[#0B1B3F] hover:bg-[#F4FBF7]">
                    <svg class="h-[18px] w-[18px] text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 11l8-6 8 6v8a1 1 0 01-1 1h-4v-5h-6v5H5a1 1 0 01-1-1v-8z"/></svg>
                    Kembali ke Dashboard
                </a>
                <div class="my-1.5 border-t border-[#EEF0F2]"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-3 px-4 py-2 text-left text-[#0B1B3F] hover:bg-[#F4FBF7]">
                        <svg class="h-[18px] w-[18px] text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 16l4-4-4-4M19 12H9M12 20H6a2 2 0 01-2-2V6a2 2 0 012-2h6"/></svg>
                        Keluar
                    </button>
                </form>
            </div>

            <div x-show="view === 'insights'" x-cloak>
                <button type="button" @click="view = 'menu'" class="flex w-full items-center gap-2 px-4 py-1.5 text-left text-[13px] font-semibold text-slate-500 hover:text-[#0B1B3F]">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    Insight bisnis
                </button>
                <div class="max-h-72 space-y-1 overflow-y-auto px-2 pb-1 pt-1">
                    @forelse($businessInsights->take(5) as $insight)
                        <a href="{{ route('intelligence.index', ['ask' => $insight->title]) }}" class="block rounded-lg px-2.5 py-2 hover:bg-[#F4FBF7]">
                            <p class="text-[13px] font-semibold leading-snug text-[#0B1B3F]">{{ $insight->title }}</p>
                            <p class="mt-0.5 text-[12px] text-slate-500">{{ $insight->confidence ?? 0 }}% yakin</p>
                        </a>
                    @empty
                        <p class="px-2.5 py-2 text-[13px] text-slate-500">Tidak ada hal penting yang perlu dicek saat ini.</p>
                    @endforelse
                </div>
            </div>

            @if(config('pos.features.intelligence_agents') && $intelligenceAgents->isNotEmpty())
                <div x-show="view === 'agents'" x-cloak>
                    <button type="button" @click="view = 'menu'" class="flex w-full items-center gap-2 px-4 py-1.5 text-left text-[13px] font-semibold text-slate-500 hover:text-[#0B1B3F]">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                        Agen terjadwal
                    </button>
                    <div class="space-y-2 px-4 pb-2 pt-1">
                        @foreach($intelligenceAgents as $agent)
                            <div class="flex items-center justify-between gap-2 text-[13px]"><span>{{ str($agent->agent_key)->replace('_', ' ')->title() }}</span><button type="button" @click="toggleAgent({{ $agent->id }}, $event)" class="rounded-full px-2 py-1 font-semibold {{ $agent->enabled ? 'bg-[#E1F5EA] text-[#087A4E]' : 'bg-slate-100 text-slate-500' }}">{{ $agent->enabled ? 'Aktif' : 'Nonaktif' }}</button></div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <button type="button" @click="menu = !menu; view = 'menu'" class="flex h-12 w-full items-center gap-3 rounded-lg px-2.5 text-left transition hover:bg-white" :aria-expanded="menu">
            <x-avatar :user="$me" class="h-8 w-8" text="text-[13px]"/>
            <span class="min-w-0 flex-1 truncate text-[14px]"><span class="font-semibold text-[#0B1B3F]">{{ \Illuminate\Support\Str::of($me->name)->before(' ')->title() }}</span> <span class="text-slate-400">&middot; {{ $planLabel }}</span></span>
            @if($businessInsights->count())<span class="h-2 w-2 shrink-0 rounded-full bg-amber-400" title="Ada insight baru"></span>@endif
            <svg class="h-3.5 w-3.5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg>
        </button>
    </div>
</aside>
