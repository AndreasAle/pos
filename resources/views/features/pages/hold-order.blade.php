<main class="pt-[72px] bg-[#fff8ed]">
    <section class="px-6 py-20 md:py-28 border-b-2 border-[#30261f]">
        <div class="max-w-[1120px] mx-auto">
            <div class="grid lg:grid-cols-[1fr_.75fr] gap-12 items-end">
                <div><p class="font-mono text-xs uppercase tracking-[.2em] text-orange-700">Pesanan belum selesai ≠ pesanan hilang</p><h1 class="mt-6 text-[50px] md:text-[76px] font-extrabold leading-[.98] tracking-[-.05em] text-[#30261f]">Titip dulu.<br>Lanjut nanti.</h1></div>
                <p class="text-lg leading-relaxed text-stone-600">Hold Order menyimpan seluruh isi keranjang dengan nama pelanggan dan nomor meja. Saat siap, kasir tinggal membuka dan melanjutkan pembayaran.</p>
            </div>

            <div class="mt-16 grid md:grid-cols-3 gap-5" style="transform:rotate(-.35deg)">
                @foreach ([['A-07','Pak Budi','Meja 12','3 item','18:42'],['A-08','Maya','Takeaway','2 item','18:44'],['A-09','Rombongan Kantor','Meja 04','8 item','18:46']] as [$code,$name,$table,$items,$time])
                    <article class="relative border-2 border-[#30261f] bg-[#fffdf7] p-6 shadow-[7px_7px_0_#f1a65a]">
                        <div class="absolute -top-3 left-1/2 -translate-x-1/2 w-20 h-6 bg-orange-200/80"></div>
                        <div class="flex justify-between font-mono text-xs"><span>HOLD {{ $code }}</span><span>{{ $time }}</span></div>
                        <h2 class="mt-8 text-2xl font-extrabold text-[#30261f]">{{ $name }}</h2>
                        <p class="mt-2 text-sm text-stone-500">{{ $table }} · {{ $items }}</p>
                        <div class="mt-8 border-t border-dashed border-stone-400 pt-4 flex justify-between text-xs font-bold"><span>TERSIMPAN</span><span>BUKA →</span></div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="px-6 py-20 bg-[#30261f] text-[#fff8ed]">
        <div class="max-w-[1120px] mx-auto grid lg:grid-cols-[.7fr_1.3fr] gap-14">
            <div><span class="font-mono text-xs text-orange-300">ALUR HOLD</span><h2 class="mt-5 text-4xl font-extrabold">Tidak perlu catatan kertas di samping kasir.</h2></div>
            <ol class="border-l border-orange-300/40">
                @foreach ($feature['steps'] as $i => [$title,$desc])
                    <li class="relative pl-10 pb-10 last:pb-0"><span class="absolute -left-4 top-0 w-8 h-8 bg-orange-300 text-[#30261f] flex items-center justify-center font-mono text-xs font-bold">{{ $i+1 }}</span><h3 class="text-xl font-bold">{{ $title }}</h3><p class="mt-2 text-sm leading-relaxed text-stone-300">{{ $desc }}</p></li>
                @endforeach
            </ol>
        </div>
    </section>
    <section class="px-6 py-16 text-center"><a href="{{ $waLink }}" target="_blank" class="border-b-2 border-orange-700 pb-1 font-bold text-orange-800">Lihat Hold Order di Yoka →</a></section>
</main>
