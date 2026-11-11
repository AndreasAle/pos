<main class="pt-[72px] bg-[#fbfbf8] text-[#16181d]">
    <section class="px-6 py-16 md:py-24 border-b border-slate-300">
        <div class="max-w-[1180px] mx-auto">
            <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-8"><div><p class="font-mono text-xs tracking-[.2em] uppercase text-indigo-700">Business report / 01—30 Nov 2026</p><h1 class="mt-6 text-[52px] md:text-[76px] font-extrabold leading-[.96] tracking-[-.05em]">Angka yang bisa<br>ditindaklanjuti.</h1></div><p class="max-w-md text-lg leading-relaxed text-slate-600">Penjualan, produk, kasir, shift, stok, dan profit dibaca dalam konteks periode serta outlet.</p></div>

            <div class="mt-16 grid grid-cols-2 lg:grid-cols-4 border-y-2 border-slate-900 divide-x divide-slate-300">@foreach ([['OMZET','Rp148,2 jt','+12,8%'],['TRANSAKSI','2.184','+8,2%'],['RATA-RATA','Rp67.857','+4,1%'],['EST. PROFIT','Rp51,7 jt','34,9% margin']] as [$label,$value,$change])<div class="p-5 md:p-7"><span class="font-mono text-[10px] text-slate-400">{{ $label }}</span><strong class="mt-3 block text-2xl md:text-3xl">{{ $value }}</strong><span class="mt-2 block text-xs font-bold text-emerald-700">{{ $change }}</span></div>@endforeach</div>
        </div>
    </section>

    <section class="px-6 py-20">
        <div class="max-w-[1180px] mx-auto grid lg:grid-cols-[1.4fr_.6fr] gap-12">
            <div><div class="flex items-end justify-between"><div><p class="font-mono text-[10px] text-slate-400">PENJUALAN HARIAN</p><h2 class="mt-2 text-2xl font-extrabold">November 2026</h2></div><span class="font-mono text-xs">SEMUA OUTLET</span></div><div class="mt-8 h-[320px] border-l border-b border-slate-400 flex items-end gap-2 px-3">@foreach ([34,48,42,65,53,72,61,78,70,82,57,68,76,88,73,91,84,67,79,94,86,98,76,89,93,81,97,90,100,96] as $bar)<div class="flex-1 bg-indigo-600 hover:bg-indigo-400" style="height:{{ $bar }}%" title="{{ $bar }}"></div>@endforeach</div><div class="mt-3 flex justify-between font-mono text-[9px] text-slate-400"><span>01 NOV</span><span>15 NOV</span><span>30 NOV</span></div></div>

            <aside class="border-t-2 border-slate-900"><div class="py-5 border-b border-slate-300"><p class="font-mono text-[10px] text-slate-400">PRODUK TERLARIS</p></div>@foreach ([['Es Kopi Susu','482','Rp8,67 jt'],['Americano','316','Rp4,74 jt'],['Croissant','248','Rp5,45 jt'],['Nasi Goreng','221','Rp6,18 jt']] as $i => [$name,$qty,$sales])<div class="grid grid-cols-[32px_1fr_auto] items-center gap-3 py-5 border-b border-slate-300"><span class="font-mono text-xs text-indigo-600">0{{ $i+1 }}</span><div><b class="block text-sm">{{ $name }}</b><small class="text-slate-400">{{ $qty }} terjual</small></div><b class="text-sm">{{ $sales }}</b></div>@endforeach</aside>
        </div>
    </section>

    <section class="bg-indigo-700 px-6 py-16 text-white"><div class="max-w-[1180px] mx-auto flex flex-col md:flex-row md:items-center justify-between gap-8"><div><p class="font-mono text-xs text-indigo-200">EXPORT</p><h2 class="mt-3 text-3xl font-extrabold">Bawa laporan ke rapat.</h2><p class="mt-2 text-indigo-100">Excel, CSV, dan PDF siap diunduh berdasarkan filter yang dipilih.</p></div><a href="{{ $waLink }}" target="_blank" class="border border-white px-7 py-3 font-bold hover:bg-white hover:text-indigo-700">Lihat laporan Yoka →</a></div></section>
</main>
