<div x-data="yokaDashboardTour()" x-init="start()" x-show="open" x-cloak
     @resize.window.debounce.150ms="locate()" @keydown.escape.window="finish()"
     class="fixed inset-0 z-[100]" role="dialog" aria-modal="true" aria-label="Panduan Yoka POS">
    <template x-if="!rect"><div class="absolute inset-0 bg-slate-950/70 backdrop-blur-[2px]"></div></template>
    <template x-if="rect">
        <div>
            <div class="absolute left-0 right-0 top-0 bg-slate-950/70" :style="`height:${Math.max(0, rect.top - 8)}px`"></div>
            <div class="absolute left-0 bg-slate-950/70" :style="`top:${Math.max(0, rect.top - 8)}px;width:${Math.max(0, rect.left - 8)}px;height:${rect.height + 16}px`"></div>
            <div class="absolute right-0 bg-slate-950/70" :style="`top:${Math.max(0, rect.top - 8)}px;left:${rect.right + 8}px;height:${rect.height + 16}px`"></div>
            <div class="absolute bottom-0 left-0 right-0 bg-slate-950/70" :style="`top:${rect.bottom + 8}px`"></div>
            <div class="pointer-events-none absolute rounded-2xl border-2 border-emerald-300 shadow-[0_0_0_5px_rgba(16,185,129,.22)] transition-all duration-300"
                 :style="`left:${rect.left - 5}px;top:${rect.top - 5}px;width:${rect.width + 10}px;height:${rect.height + 10}px`"></div>
        </div>
    </template>

    <section class="absolute z-10 w-[calc(100%-32px)] max-w-[430px] overflow-hidden rounded-3xl bg-white shadow-2xl"
             :class="step === 0 ? 'left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2' : 'bottom-4 left-4 sm:bottom-7 sm:left-auto sm:right-7'">
        <div class="h-1.5 bg-slate-100"><div class="h-full bg-emerald-500 transition-all duration-300" :style="`width:${((step + 1) / steps.length) * 100}%`"></div></div>
        <div class="p-6 sm:p-7">
            <div class="flex items-start justify-between gap-4">
                <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l1.7 3.4L17.5 7l-2.7 2.7.6 3.8-3.4-1.8-3.4 1.8.6-3.8L6.5 7l3.8-.6L12 3zm6 10 1 2 2 .3-1.5 1.4.4 2.1-1.9-1-1.9 1 .4-2.1-1.5-1.4 2-.3 1-2z"/></svg>
                </span>
                <button type="button" @click="finish()" class="rounded-lg px-2 py-1 text-xs font-bold text-slate-400 hover:bg-slate-100 hover:text-slate-600">Lewati</button>
            </div>
            <p class="mt-5 text-xs font-bold uppercase tracking-[.16em] text-emerald-600" x-text="`Panduan ${step + 1} dari ${steps.length}`"></p>
            <h2 class="mt-2 text-2xl font-extrabold tracking-[-.025em] text-[#0B1B3F]" x-text="current.title"></h2>
            <p class="mt-3 text-sm leading-6 text-slate-600" x-text="current.text"></p>
            <div class="mt-6 flex items-center justify-between gap-3">
                <button type="button" @click="back()" :disabled="step === 0" class="h-11 rounded-full px-5 text-sm font-bold text-slate-600 hover:bg-slate-100 disabled:invisible">Kembali</button>
                <button type="button" @click="next()" class="inline-flex h-11 items-center gap-2 rounded-full bg-emerald-600 px-6 text-sm font-bold text-white hover:bg-emerald-700">
                    <span x-text="step === steps.length - 1 ? 'Mulai Gunakan Yoka' : 'Lanjut'"></span>
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </button>
            </div>
        </div>
    </section>
</div>

@push('scripts')
<script>
function yokaDashboardTour() {
    return {
        open: true, step: 0, rect: null, saving: false,
        steps: [
            { target: null, title: 'Selamat datang di ruang kerja Anda', text: 'Bisnis sudah aktif dan trial 7 hari dimulai. Mari kenali bagian penting Yoka POS sebelum transaksi pertama.' },
            { target: '#yoka-dashboard-summary', title: 'Kondisi bisnis dalam satu layar', text: 'Ringkasan omzet, transaksi, stok, dan aktivitas terbaru membantu Anda mengambil keputusan tanpa menunggu tutup kasir.' },
            { target: '[data-tour="pos"]', title: 'Kasir siap menerima pesanan', text: 'Buka Kasir / POS untuk membuat transaksi, memilih pembayaran, mencetak struk, dan mengirim pesanan ke dapur.' },
            { target: '[data-tour="products"]', title: 'Mulai dari produk atau menu', text: 'Tambahkan nama, harga, varian, kategori, dan stok. Produk yang aktif akan langsung tersedia di layar kasir.' },
            { target: '[data-tour="reports"]', title: 'Pantau angka yang benar-benar penting', text: 'Laporan Yoka merangkum penjualan, produk terlaris, kinerja kasir, stok, dan estimasi profit.' },
            { target: '[data-tour="support"]', title: 'Kami ada saat Anda membutuhkan bantuan', text: 'Hubungi tim Yoka dari sini. Anda juga dapat membuka panduan ini lagi kapan saja melalui menu profil.' },
        ],
        get current() { return this.steps[this.step] },
        start() { document.documentElement.style.overflow = 'hidden'; setTimeout(() => this.locate(), 350) },
        locate() {
            this.rect = null;
            if (window.innerWidth < 768 || !this.current.target) return;
            const element = document.querySelector(this.current.target);
            if (!element) return;
            element.scrollIntoView({ block: 'center', behavior: 'smooth' });
            setTimeout(() => {
                const box = element.getBoundingClientRect();
                this.rect = { left: box.left, top: box.top, right: box.right, bottom: box.bottom, width: box.width, height: box.height };
            }, 260);
        },
        next() { if (this.step >= this.steps.length - 1) return this.finish(); this.step++; this.$nextTick(() => this.locate()) },
        back() { if (this.step > 0) { this.step--; this.$nextTick(() => this.locate()) } },
        async finish() {
            if (this.saving) return;
            this.saving = true;
            try {
                await fetch(@js(route('onboarding.tour.complete')), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                });
            } finally {
                document.documentElement.style.overflow = '';
                this.open = false;
                this.saving = false;
            }
        },
    }
}
</script>
@endpush
