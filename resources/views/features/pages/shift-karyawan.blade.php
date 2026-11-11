<main class="pt-[72px] bg-[#f1eee6] text-[#1f2933]">
    <section class="px-6 py-20 md:py-28">
        <div class="max-w-[1120px] mx-auto grid lg:grid-cols-[.85fr_1.15fr] gap-16 items-start">
            <div class="lg:sticky lg:top-28"><p class="font-mono text-xs uppercase tracking-[.2em] text-emerald-800">Shift ledger</p><h1 class="mt-7 text-[52px] md:text-[72px] font-extrabold leading-[.96] tracking-[-.05em]">Uang kas harus bisa dijelaskan.</h1><p class="mt-7 text-lg leading-relaxed text-slate-600">Dari modal awal sampai uang fisik saat tutup, setiap shift memiliki catatan dan penanggung jawab.</p></div>

            <div class="border-2 border-slate-900 bg-[#fffdf8] shadow-[10px_10px_0_#1f2933]">
                <div class="flex justify-between border-b-2 border-slate-900 px-6 py-5"><div><p class="font-mono text-[10px] text-slate-500">SHIFT REPORT</p><h2 class="mt-1 text-xl font-extrabold">Outlet Sudirman</h2></div><span class="h-fit bg-emerald-700 px-3 py-2 font-mono text-xs text-white">DITUTUP</span></div>
                <div class="grid sm:grid-cols-2 border-b-2 border-slate-900"><div class="p-6 sm:border-r-2 border-slate-900"><span class="font-mono text-[10px] text-slate-500">KASIR</span><p class="mt-2 font-bold">Rani Pratiwi</p><p class="text-sm text-slate-500">08.02—16.14 WIB</p></div><div class="p-6"><span class="font-mono text-[10px] text-slate-500">TRANSAKSI</span><p class="mt-2 text-3xl font-black">86</p><p class="text-sm text-slate-500">Rp7.420.000 omzet</p></div></div>
                <div class="p-6 space-y-4 text-sm"><div class="flex justify-between"><span>Modal awal</span><b>Rp500.000</b></div><div class="flex justify-between"><span>Penjualan tunai</span><b>Rp2.840.000</b></div><div class="flex justify-between"><span>Pengeluaran kas</span><b>− Rp120.000</b></div><div class="border-t border-dashed border-slate-500 pt-4 flex justify-between"><span>Kas seharusnya</span><b>Rp3.220.000</b></div><div class="flex justify-between"><span>Kas aktual</span><b>Rp3.215.000</b></div></div>
                <div class="grid grid-cols-2 border-t-2 border-slate-900"><div class="p-5 font-bold">SELISIH</div><div class="p-5 bg-amber-200 text-right font-black">− Rp5.000</div></div>
            </div>
        </div>
    </section>

    <section class="border-y-2 border-slate-900 bg-white px-6 py-20">
        <div class="max-w-[1120px] mx-auto"><div class="flex flex-col md:flex-row md:items-end justify-between gap-6"><h2 class="text-4xl font-extrabold">Satu tim, akses berbeda.</h2><p class="max-w-md text-sm text-slate-500">Setiap orang melihat menu yang sesuai dengan tanggung jawabnya.</p></div><div class="mt-12 grid grid-cols-2 md:grid-cols-5 border-2 border-slate-900">@foreach ([['OWNER','Semua akses'],['ADMIN','Operasional'],['KASIR','POS & shift'],['KITCHEN','Pesanan dapur'],['WAREHOUSE','Inventory']] as [$role,$access])<div class="p-5 border-b md:border-b-0 border-r last:border-r-0 border-slate-900"><span class="font-mono text-[10px]">{{ $role }}</span><p class="mt-8 text-sm font-bold">{{ $access }}</p></div>@endforeach</div></div>
    </section>
    <section class="px-6 py-16 text-center"><a href="{{ $waLink }}" target="_blank" class="border-b-2 border-emerald-800 pb-1 font-bold text-emerald-800">Rapikan shift tim →</a></section>
</main>
