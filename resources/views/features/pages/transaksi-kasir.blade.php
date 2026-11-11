<main class="pt-[72px]">
    <section class="bg-[#f4efe4] border-b border-[#d8d0bf]">
        <div class="max-w-[1180px] mx-auto grid lg:grid-cols-[.78fr_1.22fr] min-h-[690px]">
            <div class="px-6 py-20 lg:py-28 lg:pr-16 flex flex-col justify-between border-r border-[#d8d0bf]">
                <div>
                    <p class="font-mono text-xs tracking-[.2em] uppercase text-emerald-800">01 / Transaksi Kasir</p>
                    <h1 class="mt-8 text-[48px] sm:text-[64px] lg:text-[72px] font-extrabold leading-[.98] tracking-[-.045em] text-slate-950">Satu layar.<br>Satu alur.<br>Antrean jalan.</h1>
                    <p class="mt-8 max-w-md text-lg leading-relaxed text-slate-600">Produk, varian, topping, catatan, pembayaran, dan struk berada dalam satu urutan kerja yang jelas.</p>
                </div>
                <a href="{{ $waLink }}" target="_blank" class="mt-10 inline-flex w-fit border-b-2 border-emerald-800 pb-1 font-bold text-emerald-800">Coba alur kasir →</a>
            </div>

            <div class="px-6 py-16 lg:p-16 bg-[#e9e1d2] flex items-center">
                <div class="w-full border-2 border-slate-900 bg-white shadow-[14px_14px_0_#0f172a]">
                    <div class="flex items-center justify-between border-b-2 border-slate-900 px-5 py-4">
                        <strong class="text-sm">YOKA / POS</strong><span class="font-mono text-xs">SHIFT #024 · 14:32</span>
                    </div>
                    <div class="grid md:grid-cols-[1fr_210px] min-h-[390px]">
                        <div class="p-5 border-b-2 md:border-b-0 md:border-r-2 border-slate-900">
                            <div class="flex gap-2 border-b border-slate-300 pb-4 text-xs font-bold"><span class="bg-slate-950 text-white px-3 py-2">Semua</span><span class="px-3 py-2">Kopi</span><span class="px-3 py-2">Makanan</span></div>
                            <div class="mt-5 grid grid-cols-2 gap-3">
                                @foreach ([['Es Kopi Susu','18.000'],['Americano','15.000'],['Croissant','22.000'],['Nasi Goreng','28.000'],['Matcha Latte','24.000'],['Roti Bakar','17.000']] as [$item,$price])
                                    <div class="border border-slate-300 p-3"><span class="block text-sm font-bold">{{ $item }}</span><span class="mt-2 block font-mono text-xs text-emerald-700">Rp{{ $price }}</span></div>
                                @endforeach
                            </div>
                        </div>
                        <div class="p-5 bg-[#f8fafc] flex flex-col">
                            <p class="text-xs font-extrabold uppercase tracking-widest">Pesanan</p>
                            <div class="mt-5 space-y-4 text-sm">
                                <div class="flex justify-between"><span>2× Es Kopi Susu<small class="block text-slate-400">Less ice</small></span><b>36.000</b></div>
                                <div class="flex justify-between"><span>1× Croissant</span><b>22.000</b></div>
                            </div>
                            <div class="mt-auto border-t-2 border-slate-900 pt-4"><div class="flex justify-between font-extrabold"><span>Total</span><span>Rp58.000</span></div><button class="mt-4 w-full bg-emerald-700 py-3 text-sm font-bold text-white">Bayar sekarang</button></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-white px-6 py-20">
        <div class="max-w-[1100px] mx-auto">
            <p class="max-w-3xl text-2xl md:text-4xl font-bold leading-tight text-slate-900">Kasir tidak perlu mengingat rumus. Sistem menjaga harga, menghitung add-on, dan memperbarui stok setelah pembayaran.</p>
            <div class="mt-14 grid md:grid-cols-3 border-y border-slate-300 divide-y md:divide-y-0 md:divide-x divide-slate-300">
                @foreach ($feature['highlights'] as [$title,$desc])
                    <div class="py-8 md:px-8 first:pl-0"><h2 class="text-lg font-extrabold">{{ $title }}</h2><p class="mt-3 text-sm leading-relaxed text-slate-500">{{ $desc }}</p></div>
                @endforeach
            </div>
        </div>
    </section>
</main>
