{{-- Footer bersama: landing dan halaman fitur. Opsional: $home (prefix tautan anchor). --}}
    @php
        $companyName = 'Conwebid Indonesia Digital';
        $companyEmail = 'conwebid@gmail.com';
        $companyPhone = '08xx-xxxx-xxxx'; // TODO: isi nomor lengkap
        $companyPhoneDigits = '628xxxxxxxxxx'; // TODO: format 62..., tanpa + dan spasi
    @endphp
    <footer class="overflow-hidden bg-[#073e2d] text-white">
        <section class="relative min-h-[620px] overflow-hidden rounded-t-[34px] md:min-h-[690px] md:rounded-t-[54px]">
            <img src="{{ asset('images/photos/terminal-pos-premium.png') }}"
                 alt="Terminal kasir premium Yoka POS di studio hijau"
                 class="absolute inset-0 h-full w-full object-cover object-[64%_center] md:object-center"
                 loading="lazy">
            <div class="absolute inset-0 bg-gradient-to-r from-[#043e2f]/95 via-[#043e2f]/70 to-transparent md:via-[#043e2f]/20"></div>
            <div class="relative mx-auto flex min-h-[620px] max-w-[1200px] items-start px-6 pb-28 pt-16 md:min-h-[690px] md:items-center md:pb-36 md:pt-10">
                <div class="max-w-[530px]">
                    <p class="mb-5 inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-4 py-2 text-xs font-bold uppercase tracking-[.16em] backdrop-blur-sm">
                        Mulai lebih mudah bersama Yoka
                    </p>
                    <h2 class="text-[40px] font-extrabold leading-[1.08] tracking-[-.035em] sm:text-[52px] md:text-[62px]">
                        Saatnya bisnis<br>makin <span class="relative inline-block">sibuk<span class="absolute -bottom-1 left-0 h-[10px] w-full rounded-[50%] border-b-2 border-emerald-300"></span></span>
                    </h2>
                    <ul class="mt-7 space-y-3 text-sm font-semibold text-white/90 sm:text-base">
                        @foreach (['Transaksi cepat, antrean tetap lancar.', 'Stok dan laporan selalu terpantau.', 'Satu sistem untuk seluruh tim bisnismu.'] as $benefit)
                            <li class="flex items-center gap-3">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-emerald-200 text-emerald-200">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/></svg>
                                </span>
                                {{ $benefit }}
                            </li>
                        @endforeach
                    </ul>
                    <div class="mt-9 flex flex-wrap gap-3">
                        <a href="{{ route('register') }}" class="rounded-full bg-white px-7 py-3.5 text-sm font-extrabold text-emerald-900 transition hover:bg-emerald-50 sm:text-base">Mulai Gratis</a>
                        <a href="{{ $waLink }}" target="_blank" class="rounded-full border border-white/60 px-7 py-3.5 text-sm font-extrabold text-white transition hover:bg-white/10 sm:text-base">Jadwalkan Demo</a>
                    </div>
                </div>
            </div>
        </section>

        <div class="relative z-10 mx-4 -mt-16 rounded-[30px] bg-white text-slate-700 shadow-[0_28px_70px_rgba(0,0,0,.24)] sm:mx-6 md:mx-auto md:-mt-20 md:max-w-[calc(100%-96px)] md:rounded-[42px]">
            <div class="mx-auto max-w-[1300px] px-7 pb-7 pt-11 sm:px-10 md:px-16 md:pb-9 md:pt-14">
                <div class="grid gap-10 border-b border-slate-200 pb-12 sm:grid-cols-2 lg:grid-cols-[1.35fr_.75fr_.85fr_1fr] lg:gap-12">
                    <div>
                        <a href="{{ route('landing') }}" class="inline-flex items-center gap-2" aria-label="Yoka POS">
                            <img src="{{ asset('images/brand/yoka-logo.png') }}" alt="Yoka" class="h-10 w-auto">
                            <span class="text-[23px] font-extrabold leading-none text-emerald-700">POS</span>
                        </a>
                        <p class="mt-5 max-w-sm text-sm leading-7 text-slate-500">Sistem kasir modern untuk cafe, resto, bakery, dan bisnis F&amp;B. Semua operasional penting, dalam satu platform.</p>
                        <p class="mt-5 text-[12px] font-extrabold uppercase tracking-[.16em] text-slate-400">Dikembangkan oleh</p>
                        <p class="mt-1 text-sm font-bold text-slate-900">{{ $companyName }}</p>
                    </div>

                    <div>
                        <p class="mb-5 text-sm font-extrabold text-slate-950">Jelajahi</p>
                        <ul class="space-y-3.5 text-sm text-slate-500">
                            <li><a href="{{ $home ?? '' }}#tentang" class="transition hover:text-emerald-700">Tentang Yoka</a></li>
                            <li><a href="{{ $home ?? '' }}#keunggulan" class="transition hover:text-emerald-700">Keunggulan</a></li>
                            <li><a href="{{ $home ?? '' }}#harga" class="transition hover:text-emerald-700">Paket Harga</a></li>
                            <li><a href="{{ $home ?? '' }}#faq" class="transition hover:text-emerald-700">FAQ</a></li>
                        </ul>
                    </div>

                    <div>
                        <p class="mb-5 text-sm font-extrabold text-slate-950">Produk</p>
                        <ul class="space-y-3.5 text-sm text-slate-500">
                            <li><a href="{{ route('features.show', 'transaksi-kasir') }}" class="transition hover:text-emerald-700">Kasir &amp; Pembayaran</a></li>
                            <li><a href="{{ route('features.show', 'stok-resep') }}" class="transition hover:text-emerald-700">Stok &amp; Resep</a></li>
                            <li><a href="{{ route('features.show', 'kitchen-display') }}" class="transition hover:text-emerald-700">Kitchen Display</a></li>
                            <li><a href="{{ route('features.show', 'laporan') }}" class="transition hover:text-emerald-700">Laporan Bisnis</a></li>
                            <li><a href="{{ route('login') }}" class="transition hover:text-emerald-700">Masuk ke Akun</a></li>
                        </ul>
                    </div>

                    <div>
                        <p class="mb-5 text-sm font-extrabold text-slate-950">Hubungi kami</p>
                        <p class="text-sm leading-6 text-slate-500">Punya pertanyaan atau ingin melihat Yoka bekerja untuk bisnismu?</p>
                        <div class="mt-5 space-y-3">
                            <a href="https://wa.me/{{ $companyPhoneDigits }}?text=Halo%20saya%20tertarik%20dengan%20aplikasi%20POS%20Yoka" target="_blank" class="inline-flex rounded-full border border-emerald-600 px-4 py-2 text-xs font-bold text-emerald-700 transition hover:bg-emerald-50">WhatsApp {{ $companyPhone }}</a>
                            <a href="mailto:{{ $companyEmail }}" class="block break-all text-sm font-semibold text-slate-700 transition hover:text-emerald-700">{{ $companyEmail }}</a>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-5 border-b border-slate-200 py-7 md:flex-row md:items-center md:justify-between">
                    <p class="text-[22px] font-extrabold tracking-[-.025em] text-slate-950">Ada yang bisa kami bantu?</p>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ $waLink }}" target="_blank" class="rounded-full border border-emerald-600 px-5 py-2.5 text-xs font-bold text-emerald-700 transition hover:bg-emerald-50">Bicara dengan Sales</a>
                        <a href="mailto:{{ $companyEmail }}" class="rounded-full border border-slate-300 px-5 py-2.5 text-xs font-bold text-slate-700 transition hover:border-emerald-600 hover:text-emerald-700">Layanan Pelanggan</a>
                    </div>
                </div>

                <div class="flex flex-col gap-4 pt-7 text-xs text-slate-400 md:flex-row md:items-center md:justify-between">
                    <p>&copy; {{ date('Y') }} {{ $companyName }}. Seluruh hak cipta dilindungi.</p>
                    <div class="flex flex-wrap gap-x-6 gap-y-2">
                        <a href="{{ route('legal.privacy') }}" class="transition hover:text-emerald-700">Kebijakan Privasi</a>
                        <a href="{{ route('legal.terms') }}" class="transition hover:text-emerald-700">Syarat Layanan</a>
                        <a href="{{ route('legal.delete') }}" class="transition hover:text-emerald-700">Hapus Akun</a>
                        <a href="{{ $home ?? '' }}#faq" class="transition hover:text-emerald-700">Pusat Bantuan</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="px-6 pb-14 pt-12 text-center md:pb-20 md:pt-16">
            <p class="text-sm font-semibold text-emerald-100/75">Kelola bisnis lebih tenang bersama</p>
            <div class="mt-4 inline-flex items-center gap-2">
                <img src="{{ asset('images/brand/yoka-logo-white.png') }}" alt="Yoka" class="h-11 w-auto">
                <span class="text-[25px] font-extrabold">POS</span>
            </div>
        </div>
    </footer>

