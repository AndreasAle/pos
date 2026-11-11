<main class="pt-[72px] bg-[#171a1c] text-white">
    <section class="px-6 py-16 md:py-24 border-b border-white/10">
        <div class="max-w-[1200px] mx-auto flex flex-col lg:flex-row lg:items-end justify-between gap-8">
            <div><p class="font-mono text-xs tracking-[.2em] text-lime-300">KITCHEN DISPLAY / LIVE</p><h1 class="mt-6 text-[50px] md:text-[72px] font-extrabold leading-[.95] tracking-[-.05em]">Dapur melihat<br>yang kasir catat.</h1></div>
            <div class="lg:max-w-sm"><p class="text-lg leading-relaxed text-slate-400">Nama menu, varian, add-on, dan catatan khusus masuk tanpa diteriakkan atau ditulis ulang.</p><div class="mt-6 flex items-center gap-2 font-mono text-xs text-lime-300"><span class="w-2 h-2 bg-lime-300 animate-pulse"></span> 6 PESANAN AKTIF</div></div>
        </div>
    </section>

    <section class="px-4 md:px-6 py-10 md:py-14 bg-[#0e1011] overflow-x-auto">
        <div class="max-w-[1240px] min-w-[900px] mx-auto grid grid-cols-3 gap-4">
            @foreach ([['BARU','bg-amber-300 text-black',[['#0182','Meja 07','02:14',['2× Es Kopi Susu','1× Croissant']],['#0183','Takeaway','00:48',['1× Nasi Goreng','Tanpa bawang']]]],['DIPROSES','bg-sky-400 text-black',[['#0179','Meja 03','08:32',['2× Matcha Latte','1× Roti Bakar']],['#0180','Meja 11','05:17',['3× Americano','Extra shot']]]],['SIAP','bg-lime-300 text-black',[['#0178','Takeaway','11:06',['1× Beef Burger','1× Kentang Goreng']]]]] as [$status,$color,$orders])
                <div class="border border-white/15 bg-[#171a1c]">
                    <div class="flex justify-between px-4 py-3 font-mono text-xs {{ $color }}"><b>{{ $status }}</b><span>{{ count($orders) }}</span></div>
                    <div class="p-3 space-y-3">
                        @foreach ($orders as [$code,$table,$time,$items])
                            <article class="border border-white/20 bg-[#222629] p-4"><div class="flex justify-between"><b class="text-xl">{{ $code }}</b><span class="font-mono text-xs {{ str_starts_with($time,'0')?'text-lime-300':'text-amber-300' }}">{{ $time }}</span></div><p class="mt-1 text-xs text-slate-400">{{ $table }}</p><div class="mt-5 border-t border-white/10 pt-4 space-y-2">@foreach ($items as $item)<p class="text-sm font-semibold">{{ $item }}</p>@endforeach</div><button class="mt-5 w-full border border-white/20 py-2 font-mono text-xs text-slate-300">UBAH STATUS →</button></article>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="px-6 py-20 bg-lime-300 text-[#171a1c]"><div class="max-w-[1100px] mx-auto grid md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-black/25">@foreach ($feature['highlights'] as [$title,$desc])<div class="py-7 md:px-9 first:pl-0"><h2 class="text-xl font-black uppercase">{{ $title }}</h2><p class="mt-3 text-sm leading-relaxed">{{ $desc }}</p></div>@endforeach</div><div class="mt-12 text-center"><a href="{{ $waLink }}" target="_blank" class="border-2 border-black bg-black px-7 py-3 font-bold text-white">Pasang layar dapur →</a></div></section>
</main>
