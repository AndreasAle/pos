<main class="pt-[72px] bg-[#eceae4] text-[#191919]">
    <section class="px-6 py-20 md:py-28">
        <div class="max-w-[1080px] mx-auto grid lg:grid-cols-[.9fr_1.1fr] gap-16 items-center">
            <div>
                <p class="font-mono text-xs uppercase tracking-[.22em]">Output / 58 mm / 80 mm</p>
                <h1 class="mt-8 text-[52px] md:text-[74px] font-black leading-[.94] tracking-[-.055em]">Selesai bayar.<br>Struk keluar.</h1>
                <p class="mt-8 max-w-lg text-lg leading-relaxed text-neutral-600">Printer browser untuk desktop, RawBT untuk Bluetooth Android, dan PDF saat pelanggan membutuhkan salinan digital.</p>
                <div class="mt-10 grid grid-cols-3 border-y-2 border-black divide-x-2 divide-black text-center font-mono text-xs"><span class="py-4">BROWSER</span><span class="py-4">RAWBT</span><span class="py-4">PDF</span></div>
            </div>

            <div class="relative flex justify-center py-8">
                <div class="absolute top-0 w-[310px] h-24 bg-neutral-800 border-4 border-black"><div class="mx-auto mt-10 h-2 w-44 bg-black"></div></div>
                <article class="receipt-edge relative mt-16 w-[280px] bg-[#fffef8] px-7 pb-14 pt-8 font-mono text-[11px] leading-relaxed shadow-[0_18px_30px_rgba(0,0,0,.22)]">
                    <div class="text-center"><strong class="text-lg">YOKA COFFEE</strong><p>Jl. Sudirman No. 10</p><p>ORDER YK-20261111-0182</p></div>
                    <div class="my-5 border-t border-dashed border-black"></div>
                    <div class="space-y-2"><p class="flex justify-between"><span>2x Es Kopi Susu</span><span>36.000</span></p><p class="flex justify-between"><span>1x Croissant</span><span>22.000</span></p><p class="pl-4 text-neutral-500">+ Less ice</p></div>
                    <div class="my-5 border-t border-dashed border-black"></div>
                    <p class="flex justify-between font-bold"><span>TOTAL</span><span>58.000</span></p><p class="flex justify-between"><span>TUNAI</span><span>60.000</span></p><p class="flex justify-between"><span>KEMBALI</span><span>2.000</span></p>
                    <div class="my-5 border-t border-dashed border-black"></div><p class="text-center">Terima kasih<br>Kasir: Putri · 19:42 WIB</p>
                </article>
            </div>
        </div>
    </section>

    <section class="border-y-2 border-black bg-white px-6 py-16">
        <div class="max-w-[1080px] mx-auto grid md:grid-cols-3 divide-y-2 md:divide-y-0 md:divide-x-2 divide-black">
            @foreach ($feature['highlights'] as [$title,$desc])
                <div class="py-8 md:px-10 first:pl-0"><h2 class="font-mono text-sm font-black uppercase">{{ $title }}</h2><p class="mt-4 text-sm leading-relaxed text-neutral-600">{{ $desc }}</p></div>
            @endforeach
        </div>
    </section>
    <section class="px-6 py-16 text-center font-mono text-sm"><a href="{{ $waLink }}" target="_blank" class="bg-black px-7 py-4 text-white">SIAPKAN PRINTER KASIR →</a></section>
</main>
