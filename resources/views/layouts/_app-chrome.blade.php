{{--
    Bingkai halaman saat dibuka dari aplikasi Android Yoka POS: bar atas tipis
    dan navigasi bawah. Tanpa sidebar, search global, bahasa, atau lonceng web.
--}}
@php
    $me = auth()->user();
    $kasirIcon = 'M4 10.5h16V19a1.5 1.5 0 01-1.5 1.5h-13A1.5 1.5 0 014 19v-8.5zM5.5 10.5L7 4.5h10l1.5 6M9 15h6';
    $tabs = [
        ['route' => 'orders.index', 'match' => 'orders.*', 'label' => 'Pesanan', 'icon' => 'M6 3.5h12v17l-2-1.25-2 1.25-2-1.25-2 1.25-2-1.25-2 1.25v-17zM9 8.5h6M9 12.5h6'],
        ['route' => 'shifts.index', 'match' => 'shifts.*', 'label' => 'Shift',   'icon' => 'M12 20.5a8.5 8.5 0 100-17 8.5 8.5 0 000 17zM12 7.5V12l3 2'],
    ];
    $helpIcon  = 'M12 20.5a8.5 8.5 0 100-17 8.5 8.5 0 000 17zM9.75 9.5a2.25 2.25 0 113.4 1.95c-.7.4-1.15.85-1.15 1.55M12 16.25v.01';
    $userIcon  = 'M12 12a3.75 3.75 0 100-7.5 3.75 3.75 0 000 7.5zM5 20c.9-3.3 3.7-5.25 7-5.25s6.1 1.95 7 5.25';
    $supportUrl = config('pos.support_url') ?: 'mailto:' . config('mail.from.address');
    $onTab = collect($tabs)->contains(fn ($t) => request()->routeIs($t['match']) && ! request()->routeIs('shifts.open', 'shifts.close', 'shifts.show', 'orders.show'));
    $firstName = \Illuminate\Support\Str::before($me->name, ' ') ?: $me->name;
@endphp

<header class="app-bar fixed inset-x-0 top-0 z-40 h-14 bg-white/95 backdrop-blur border-b border-[#EEF0F3]">
    <div class="h-full px-3 sm:px-6 flex items-center gap-1">
        @if($onTab)
            <span class="w-2"></span>
        @else
            <button type="button" onclick="history.length > 1 ? history.back() : (location.href='{{ route('pos.index') }}')"
                    aria-label="Kembali" class="w-10 h-10 -ml-1 rounded-full flex items-center justify-center text-[#0B1B3F] active:bg-[#F1F5F9]">
                <svg class="w-[22px] h-[22px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </button>
        @endif

        <h1 class="min-w-0 truncate {{ $onTab ? 'text-[20px] font-bold tracking-[-0.02em]' : 'text-[17px] font-semibold' }} tracking-[-0.01em] text-[#0B1B3F]">@yield('page-title', 'Yoka POS')</h1>

    </div>
</header>

<nav class="app-tabs fixed inset-x-0 bottom-0 z-40 px-3 pb-[calc(10px+env(safe-area-inset-bottom))] pointer-events-none" x-data="{ account: false }">
    @php
        $tabLink = function ($t) {
            $active = request()->routeIs($t['match']);
            return [$active, $active ? 'text-[#15803D]' : 'text-[#94A3B8] active:text-[#64748B]'];
        };
    @endphp
    <div class="pointer-events-auto relative h-[68px] max-w-xl mx-auto grid grid-cols-5 items-center rounded-[28px] bg-white shadow-[0_10px_30px_rgba(11,27,63,.12)] ring-1 ring-black/[.04]">
        @foreach($tabs as $t)
            @php [$active, $tone] = $tabLink($t); @endphp
            <a href="{{ route($t['route']) }}" class="flex flex-col items-center justify-center gap-1 {{ $tone }}">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="{{ $active ? 'currentColor' : 'none' }}" fill-opacity=".14" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $t['icon'] }}"/></svg>
                <span class="text-[11px] {{ $active ? 'font-semibold' : 'font-medium' }}">{{ $t['label'] }}</span>
            </a>
        @endforeach

        {{-- Kasir: tombol utama di tengah, menonjol ke atas --}}
        <a href="{{ route('pos.index') }}" class="flex flex-col items-center justify-end h-full pb-2" aria-label="Kasir">
            <span class="-mt-9 w-[60px] h-[60px] rounded-full bg-[#16A34A] active:bg-[#15803D] text-white flex items-center justify-center ring-[6px] ring-white shadow-[0_8px_20px_rgba(22,163,74,.35)]">
                <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $kasirIcon }}"/></svg>
            </span>
            <span class="mt-1 text-[11px] font-semibold text-[#15803D]">Kasir</span>
        </a>

        <a href="{{ $supportUrl }}" target="_blank" rel="noopener" class="flex flex-col items-center justify-center gap-1 text-[#94A3B8] active:text-[#64748B]">
            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $helpIcon }}"/></svg>
            <span class="text-[11px] font-medium">Bantuan</span>
        </a>

        <button type="button" @click="account = true" class="flex flex-col items-center justify-center gap-1" :class="account ? 'text-[#15803D]' : 'text-[#94A3B8]'">
            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $userIcon }}"/></svg>
            <span class="text-[11px] font-medium">Akun</span>
        </button>
    </div>

    {{-- Lembar akun --}}
    <div x-show="account" x-cloak class="pointer-events-auto fixed inset-0 z-50" @keydown.escape.window="account = false">
        <div class="absolute inset-0 bg-[#0B1B3F]/30" @click="account = false" x-show="account" x-transition.opacity></div>
        <div class="absolute inset-x-0 bottom-0 mx-auto max-w-xl bg-white rounded-t-3xl px-6 pt-3 pb-[calc(20px+env(safe-area-inset-bottom))]"
             x-show="account" x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-full" x-transition:leave="transition duration-150 ease-in" x-transition:leave-end="translate-y-full">
            <div class="mx-auto w-10 h-1 rounded-full bg-[#E2E8F0]"></div>
            <div class="mt-5 flex items-center gap-3">
                <span class="w-11 h-11 rounded-full bg-[#ECFDF3] text-[#15803D] text-[15px] font-bold flex items-center justify-center">{{ mb_strtoupper(mb_substr($me->name, 0, 1)) }}</span>
                <span class="min-w-0">
                    <span class="block text-[16px] font-semibold text-[#0B1B3F] truncate">{{ \Illuminate\Support\Str::title(mb_strtolower($me->name)) }}</span>
                    <span class="block text-[13px] text-[#64748B] capitalize">{{ $me->role }}{{ $me->outlet ? ' · '.$me->outlet->name : '' }}</span>
                </span>
            </div>
            <div class="mt-4 divide-y divide-[#F1F5F9]">
                <form method="POST" action="{{ route('pin.logout') }}">
                    @csrf
                    <button type="submit" class="w-full h-14 flex items-center text-[15px] font-medium text-[#0B1B3F]">Ganti kasir</button>
                </form>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full h-14 flex items-center text-[15px] font-medium text-[#DC2626]">Keluar</button>
                </form>
            </div>
        </div>
    </div>
</nav>
