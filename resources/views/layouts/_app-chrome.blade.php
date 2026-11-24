{{--
    Bingkai halaman saat dibuka dari aplikasi Android Yoka POS: bar atas tipis
    dan navigasi bawah. Tanpa sidebar, search global, bahasa, atau lonceng web.
--}}
@php
    $me = auth()->user();
    $tabs = [
        ['route' => 'pos.index',    'match' => 'pos.*',    'label' => 'Kasir',   'icon' => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 2.3c-.6.6-.2 1.7.7 1.7H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z'],
        ['route' => 'orders.index', 'match' => 'orders.*', 'label' => 'Pesanan', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01'],
        ['route' => 'shifts.index', 'match' => 'shifts.*', 'label' => 'Shift',   'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
    ];
    $onTab = collect($tabs)->contains(fn ($t) => request()->routeIs($t['match']) && ! request()->routeIs('shifts.open', 'shifts.close', 'shifts.show', 'orders.show'));
    $firstName = \Illuminate\Support\Str::before($me->name, ' ') ?: $me->name;
@endphp

<header class="app-bar fixed inset-x-0 top-0 z-40 h-14 bg-white/95 backdrop-blur border-b border-[#EEF0F3]">
    <div class="h-full px-3 sm:px-6 flex items-center gap-1">
        @if($onTab)
            <img src="{{ asset('images/brand/yoka-icon.png') }}" alt="" class="w-7 h-7 ml-1 mr-2 rounded-lg">
        @else
            <button type="button" onclick="history.length > 1 ? history.back() : (location.href='{{ route('pos.index') }}')"
                    aria-label="Kembali" class="w-10 h-10 -ml-1 rounded-full flex items-center justify-center text-[#0B1B3F] active:bg-[#F1F5F9]">
                <svg class="w-[22px] h-[22px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </button>
        @endif

        <h1 class="min-w-0 truncate text-[17px] font-semibold tracking-[-0.01em] text-[#0B1B3F]">@yield('page-title', 'Yoka POS')</h1>

        <div class="ml-auto relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
            <button type="button" @click="open = !open" class="h-10 pl-1 pr-2 rounded-full flex items-center gap-2 active:bg-[#F1F5F9]" aria-label="Akun">
                <span class="w-8 h-8 rounded-full bg-[#ECFDF3] text-[#15803D] text-[13px] font-bold flex items-center justify-center">{{ mb_strtoupper(mb_substr($me->name, 0, 1)) }}</span>
                <span class="hidden sm:inline text-sm font-medium text-[#0B1B3F]">{{ $firstName }}</span>
            </button>
            <div x-show="open" x-cloak x-transition.origin.top.right.duration.150ms
                 class="absolute right-0 top-full mt-2 w-60 bg-white rounded-2xl shadow-[0_18px_48px_rgba(11,27,63,.16)] ring-1 ring-black/5 py-2">
                <div class="px-4 pt-1 pb-3">
                    <p class="text-sm font-semibold text-[#0B1B3F] truncate">{{ $me->name }}</p>
                    <p class="text-xs text-[#64748B] capitalize">{{ $me->role }}{{ $me->outlet ? ' · '.$me->outlet->name : '' }}</p>
                </div>
                <form method="POST" action="{{ route('pin.logout') }}">
                    @csrf
                    <button type="submit" class="w-full flex items-center gap-3 px-4 h-11 text-sm text-[#0B1B3F] active:bg-[#F8FAFC]">
                        <svg class="w-[18px] h-[18px] text-[#64748B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Ganti kasir
                    </button>
                </form>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full flex items-center gap-3 px-4 h-11 text-sm text-red-600 active:bg-red-50">
                        <svg class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        Keluar
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>

<nav class="app-tabs fixed inset-x-0 bottom-0 z-40 bg-white/95 backdrop-blur border-t border-[#EEF0F3] pb-[env(safe-area-inset-bottom)]">
    <div class="h-16 max-w-xl mx-auto grid grid-cols-3">
        @foreach($tabs as $t)
            @php $active = request()->routeIs($t['match']); @endphp
            <a href="{{ route($t['route']) }}" class="relative flex flex-col items-center justify-center gap-1 {{ $active ? 'text-[#15803D]' : 'text-[#94A3B8]' }}">
                @if($active)<span class="absolute top-0 h-[3px] w-8 rounded-b-full bg-[#16A34A]"></span>@endif
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="{{ $active ? 2 : 1.7 }}"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $t['icon'] }}"/></svg>
                <span class="text-[11px] {{ $active ? 'font-semibold' : 'font-medium' }}">{{ $t['label'] }}</span>
            </a>
        @endforeach
    </div>
</nav>
