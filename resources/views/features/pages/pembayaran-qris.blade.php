<main class="pt-[72px] bg-[#07182d] text-white">
    <section class="relative overflow-hidden px-6 py-20 md:py-28">
        <div class="absolute inset-y-0 right-0 w-1/2 opacity-20" style="background:repeating-linear-gradient(90deg,#38bdf8 0,#38bdf8 1px,transparent 1px,transparent 60px)"></div>
        <div class="relative max-w-[1120px] mx-auto grid lg:grid-cols-2 gap-16 items-center">
            <div>
                <p class="font-mono text-xs uppercase tracking-[.22em] text-sky-300">Payment rail / Yoka POS</p>
                <h1 class="mt-7 text-[46px] md:text-[68px] font-extrabold leading-[1.02] tracking-[-.04em]">Bayar sesuai cara pelanggan.</h1>
                <p class="mt-7 max-w-xl text-lg leading-relaxed text-slate-300">Tunai, QRIS, debit, transfer, e-wallet, atau gabungan beberapa metode. Seluruh nominal masuk ke laporan yang sama.</p>
                <div class="mt-10 flex flex-wrap gap-x-8 gap-y-3 font-mono text-xs text-sky-200"><span>QRIS_DYNAMIC</span><span>SPLIT_PAYMENT</span><span>SERVER_TOTAL</span></div>
            </div>

            <div class="border border-sky-400/40 bg-[#0b213d] p-6 md:p-9 shadow-[20px_20px_0_rgba(14,165,233,.15)]">
                <div class="flex justify-between font-mono text-xs text-sky-300"><span>PAYMENT REQUEST</span><span>#YK-04182</span></div>
                <p class="mt-10 text-sm text-slate-400">TOTAL PEMBAYARAN</p>
                <p class="mt-2 text-5xl md:text-6xl font-extrabold tracking-tight">Rp128.500</p>
                <div class="mt-9 grid grid-cols-[150px_1fr] gap-6 items-center">
                    <div class="aspect-square bg-white p-4 grid grid-cols-5 gap-1">
                        @foreach ([1,1,1,0,1,1,0,1,0,0,1,1,1,1,1,0,1,0,1,0,1,0,1,1,1] as $cell)
                            <span class="{{ $cell ? 'bg-slate-950' : 'bg-white' }}"></span>
                        @endforeach
                    </div>
                    <div><p class="font-bold">Scan QRIS</p><p class="mt-2 text-sm leading-relaxed text-slate-400">Nominal sudah tertanam. Pelanggan cukup scan dan konfirmasi.</p><span class="mt-5 inline-block border border-emerald-400 px-3 py-2 font-mono text-xs text-emerald-300">MENUNGGU PEMBAYARAN</span></div>
                </div>
            </div>
        </div>
    </section>

    <section class="border-y border-white/15 bg-[#0a1e37] px-6 py-16">
        <div class="max-w-[1120px] mx-auto grid md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-white/15">
            @foreach ([['01','Pilih metode','Kasir memilih satu metode atau mengaktifkan split payment.'],['02','Cocokkan nominal','Sistem memvalidasi nilai bayar terhadap total server.'],['03','Masuk laporan','Setiap bagian pembayaran tercatat dengan metode aslinya.']] as [$no,$title,$desc])
                <div class="py-7 md:px-9 first:pl-0"><span class="font-mono text-sky-400">{{ $no }}</span><h2 class="mt-4 text-xl font-bold">{{ $title }}</h2><p class="mt-2 text-sm leading-relaxed text-slate-400">{{ $desc }}</p></div>
            @endforeach
        </div>
    </section>

    <section class="px-6 py-20 text-center"><p class="font-mono text-xs text-sky-300">TUNAI · QRIS · TRANSFER · EWALLET · DEBIT · OTHER</p><a href="{{ $waLink }}" target="_blank" class="mt-7 inline-block border border-sky-300 px-7 py-3 font-bold text-sky-200 hover:bg-sky-300 hover:text-[#07182d]">Aktifkan pembayaran →</a></section>
</main>
