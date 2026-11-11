<main class="pt-[72px] bg-[#fff7f3] text-[#3c2230]">
    <section class="overflow-hidden px-6 py-20 md:py-28">
        <div class="max-w-[1120px] mx-auto grid lg:grid-cols-2 gap-16 items-center">
            <div><p class="font-mono text-xs uppercase tracking-[.2em] text-fuchsia-700">Customer loop</p><h1 class="mt-7 text-[50px] md:text-[72px] font-extrabold leading-[.98] tracking-[-.05em]">Sekali datang.<br>Ada alasan untuk kembali.</h1><p class="mt-7 max-w-xl text-lg leading-relaxed text-[#765465]">Poin, promo, dan bundle tercatat di transaksi—bukan di kartu stempel yang mudah hilang.</p><a href="{{ $waLink }}" target="_blank" class="mt-9 inline-block bg-[#3c2230] px-7 py-3 text-sm font-bold text-white">Buat program pelanggan →</a></div>

            <div class="relative min-h-[500px]">
                <div class="absolute left-0 top-6 w-[82%] border-2 border-[#3c2230] bg-[#efc9d8] p-7 shadow-[10px_10px_0_#3c2230] -rotate-2">
                    <div class="flex justify-between"><span class="font-mono text-xs">YOKA MEMBER</span><span class="font-bold">BREWCOOL</span></div><p class="mt-16 text-sm">PUTRI AZAHRA</p><p class="mt-2 text-5xl font-black">1.240 <small class="text-base">poin</small></p><div class="mt-12 flex justify-between text-xs"><span>Member sejak 2026</span><span>•••• 1842</span></div>
                </div>
                <div class="absolute bottom-0 right-0 w-[76%] border-2 border-[#3c2230] bg-[#fffdf8] p-6 shadow-[8px_8px_0_#f4a261] rotate-2">
                    <div class="flex justify-between font-mono text-xs"><span>PROMO AKTIF</span><span>11—30 NOV</span></div><h2 class="mt-7 text-3xl font-black">BELI 2<br>GRATIS 1</h2><p class="mt-5 text-sm text-[#765465]">Es krim cup semua rasa</p><div class="mt-7 border-t border-dashed border-[#3c2230] pt-4 text-xs font-bold">OTOMATIS DI KASIR</div>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-[#3c2230] px-6 py-20 text-white">
        <div class="max-w-[1120px] mx-auto grid lg:grid-cols-[.65fr_1.35fr] gap-16"><div><p class="font-mono text-xs text-pink-300">PROGRAM PENJUALAN</p><h2 class="mt-5 text-4xl font-extrabold">Atur aturan. Kasir tinggal melayani.</h2></div><div class="divide-y divide-white/20 border-y border-white/20">@foreach ([['01','Poin loyalitas','Dapat dan tukar poin dengan batas saldo yang aman.'],['02','Promo fleksibel','Nominal, persentase, kategori, hingga beli-gratis.'],['03','Bundle produk','Gabungkan beberapa item dalam satu paket khusus.']] as [$no,$title,$desc])<div class="grid grid-cols-[48px_1fr] gap-4 py-6"><span class="font-mono text-pink-300">{{ $no }}</span><div><h3 class="text-xl font-bold">{{ $title }}</h3><p class="mt-2 text-sm leading-relaxed text-pink-100/65">{{ $desc }}</p></div></div>@endforeach</div></div>
    </section>
</main>
