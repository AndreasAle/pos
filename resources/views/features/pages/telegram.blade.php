<main class="pt-[72px] bg-[#dcebf5] text-[#17212b]">
    <section class="px-6 py-16 md:py-24">
        <div class="max-w-[1120px] mx-auto grid lg:grid-cols-[.78fr_1.22fr] gap-14 items-center">
            <div>
                <div class="inline-flex items-center gap-2 border border-[#2aabee] bg-white px-3 py-2 font-mono text-[10px] font-bold text-[#168acd]"><span class="w-2 h-2 bg-[#2aabee]"></span> BOT TELEGRAM YOKA</div>
                <h1 class="mt-7 text-[48px] md:text-[68px] font-extrabold leading-[1] tracking-[-.05em]">Data toko,<br>dibalas lewat chat.</h1>
                <p class="mt-7 text-lg leading-relaxed text-slate-600">Bot membaca pertanyaan berdasarkan aturan kata kunci, lalu mengambil angka langsung dari database bisnis Anda.</p>
                <div class="mt-8 border-l-4 border-[#2aabee] bg-white/65 p-4"><strong class="block text-sm">Bukan AI generatif.</strong><p class="mt-1 text-xs leading-relaxed text-slate-500">Tidak memakai layanan AI luar. Cepat, konsisten, dan data tidak dikirim ke model pihak ketiga.</p></div>
            </div>

            <div class="border border-[#9db7c8] bg-white shadow-[12px_12px_0_#9db7c8]">
                <div class="flex items-center gap-3 border-b border-slate-200 px-5 py-4"><span class="w-11 h-11 bg-[#2aabee] text-white flex items-center justify-center">➤</span><div><b class="block text-sm">Yoka POS Assistant</b><small class="text-[#2aabee]">bot · online</small></div></div>
                <div class="space-y-4 bg-[#dcebf5] p-5 md:p-7 min-h-[430px]">
                    <div class="ml-auto max-w-[76%] bg-[#effdde] px-4 py-3 text-sm shadow-sm">Omzet kemarin berapa?<small class="mt-1 block text-right text-[9px] text-slate-400">09.12 ✓✓</small></div>
                    <div class="max-w-[86%] bg-white px-4 py-3 text-sm leading-relaxed shadow-sm">💰 Omzet kemarin: <b>Rp6.420.000</b> dari 91 transaksi.<br><span class="text-emerald-700">📈 Naik 12% dibanding hari sebelumnya.</span><small class="mt-1 block text-right text-[9px] text-slate-400">09.12</small></div>
                    <div class="ml-auto max-w-[76%] bg-[#effdde] px-4 py-3 text-sm shadow-sm">Siapa yang lagi jaga kasir?<small class="mt-1 block text-right text-[9px] text-slate-400">09.13 ✓✓</small></div>
                    <div class="max-w-[86%] bg-white px-4 py-3 text-sm leading-relaxed shadow-sm">⏱ <b>Rani</b> sedang membuka shift di Outlet Sudirman sejak 08.02. Modal awal Rp500.000.</div>
                </div>
                <div class="border-t border-slate-200 px-5 py-4 font-mono text-xs text-slate-400">Tulis pertanyaan tentang toko…</div>
            </div>
        </div>
    </section>

    <section class="bg-white border-y border-[#9db7c8] px-6 py-20">
        <div class="max-w-[1120px] mx-auto"><h2 class="text-3xl md:text-4xl font-extrabold">Yang bisa ditanyakan sekarang</h2><div class="mt-10 grid md:grid-cols-2 border-t border-[#9db7c8]">@foreach (['Omzet dan jumlah transaksi','Estimasi laba dan margin','Produk terlaris dan produk tertentu','Stok bahan yang menipis','Metode pembayaran','Jam paling ramai','Pelanggan paling sering belanja','Kasir yang sedang membuka shift'] as $i => $question)<div class="flex gap-4 border-b border-[#9db7c8] py-5 md:odd:border-r md:odd:pr-8 md:even:pl-8"><span class="font-mono text-xs text-[#168acd]">{{ str_pad($i+1,2,'0',STR_PAD_LEFT) }}</span><b class="text-sm">{{ $question }}</b></div>@endforeach</div></div>
    </section>

    <section class="px-6 py-16 text-center"><a href="{{ $waLink }}" target="_blank" class="inline-block bg-[#2aabee] px-7 py-3 font-bold text-white">Hubungkan Telegram →</a></section>
</main>
