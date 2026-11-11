<main class="pt-[72px] bg-[#f7f9f3]">
    <section class="paper-grid px-6 py-20 md:py-28 border-b border-emerald-900/20">
        <div class="max-w-[1140px] mx-auto">
            <div class="grid lg:grid-cols-[.8fr_1.2fr] gap-14 items-end">
                <div><p class="font-mono text-xs tracking-[.2em] uppercase text-emerald-700">Inventory logic</p><h1 class="mt-7 text-[48px] md:text-[70px] font-extrabold leading-[1] tracking-[-.05em] text-emerald-950">Satu menu terjual.<br>Bahan ikut bergerak.</h1></div>
                <p class="border-l-4 border-emerald-700 pl-6 text-lg leading-relaxed text-slate-600">Resep menghubungkan produk di kasir dengan bahan di gudang. Tidak ada potong stok berdasarkan tebakan.</p>
            </div>

            <div class="mt-16 border-2 border-emerald-950 bg-white">
                <div class="grid lg:grid-cols-[1fr_80px_1.6fr] items-stretch">
                    <div class="p-7 bg-emerald-900 text-white"><span class="font-mono text-xs text-emerald-300">PRODUK</span><h2 class="mt-5 text-3xl font-extrabold">Es Kopi Susu</h2><p class="mt-2 text-sm text-emerald-100">1 porsi terjual</p><p class="mt-8 font-mono text-xs">SKU: KOPI-001</p></div>
                    <div class="hidden lg:flex items-center justify-center text-4xl text-emerald-900">→</div>
                    <div class="grid sm:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-emerald-900/20">
                        @foreach ([['Biji kopi','18 g','12,4 kg'],['Susu segar','120 ml','34,8 L'],['Gula aren','25 ml','8,2 L']] as [$name,$usage,$stock])
                            <div class="p-6"><span class="font-mono text-[10px] text-slate-400">BAHAN</span><h3 class="mt-4 font-extrabold text-emerald-950">{{ $name }}</h3><p class="mt-7 text-2xl font-black text-emerald-700">− {{ $usage }}</p><p class="mt-2 text-xs text-slate-500">Sisa {{ $stock }}</p></div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="px-6 py-20 bg-white">
        <div class="max-w-[1140px] mx-auto grid lg:grid-cols-[1.2fr_.8fr] gap-14">
            <div class="overflow-x-auto border-t-2 border-emerald-950"><table class="w-full min-w-[560px] text-left text-sm"><thead class="border-b border-slate-300 font-mono text-[10px] uppercase tracking-wider text-slate-400"><tr><th class="py-4">Bahan</th><th>Stok</th><th>Minimum</th><th>Status</th></tr></thead><tbody class="divide-y divide-slate-200">@foreach ([['Biji kopi','12,4 kg','3 kg','Aman'],['Susu segar','4 L','8 L','Belanja'],['Gula aren','8,2 L','2 L','Aman'],['Sirup vanilla','1 botol','3 botol','Belanja']] as [$name,$stock,$min,$status])<tr><td class="py-5 font-bold">{{ $name }}</td><td>{{ $stock }}</td><td>{{ $min }}</td><td class="font-bold {{ $status==='Aman'?'text-emerald-700':'text-orange-600' }}">{{ $status }}</td></tr>@endforeach</tbody></table></div>
            <div><p class="font-mono text-xs uppercase tracking-[.2em] text-emerald-700">Bukan sekadar angka stok</p><h2 class="mt-5 text-4xl font-extrabold text-emerald-950">Ada riwayat untuk setiap perubahan.</h2><p class="mt-5 leading-relaxed text-slate-600">Stok masuk, penjualan, retur, dan penyesuaian manual meninggalkan jejak dengan waktu serta pengguna.</p><a href="{{ $waLink }}" target="_blank" class="mt-8 inline-block border-b-2 border-emerald-800 pb-1 font-bold text-emerald-800">Atur resep pertama →</a></div>
        </div>
    </section>
</main>
