
@php
    use App\Support\NavIcons;

    $user     = auth()->user();
    $role     = $user->role;
    $isRetail = (bool) $business->isRetail();
    $manager  = in_array($role, ['owner', 'admin']);
    $rp       = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');

    // Label waktu ala chat: jam hari ini, "Kemarin", atau tanggal.
    $when = function ($c) {
        if (!$c) return '';
        $c = $c instanceof \Carbon\Carbon ? $c : \Carbon\Carbon::parse($c);
        return $c->isToday() ? $c->format('H.i') : ($c->isYesterday() ? 'Kemarin' : $c->format('d/m/y'));
    };

    $last      = $recentOrders[0] ?? null;
    $lastAt    = $last ? \Carbon\Carbon::parse($last['created_at']) : null;
    $statusTxt = ['paid' => 'Lunas', 'pending' => 'Pending', 'draft' => 'Draft', 'cancelled' => 'Batal', 'refunded' => 'Refund'];
    $lowCount  = count($lowStock);
    $tgLinked  = $business->telegramLinked();
    $topName   = $topProducts[0]['product_name'] ?? null;

    // Tiap baris = satu menu. attn: perlu perhatian, pin: favorit, badge: angka di kanan, tick: centang ganda biru di depan pratinjau.
    $rows = [];
    $add = function (array $r) use (&$rows) { $rows[] = $r + ['badge' => null, 'tone' => 'green', 'time' => '', 'attn' => false, 'pin' => false, 'tick' => false]; };

    // ── Utama ──
    $add(['group' => 'utama', 'title' => 'Ringkasan Hari Ini', 'icon' => 'chart-bar', 'grad' => 'from-emerald-500 to-teal-600',
          'href' => route('dashboard', ['ringkasan' => 1]), 'time' => 'Hari ini', 'pin' => true,
          'preview' => $rp($summary['todayRevenue']) . ' · ' . number_format($summary['todayOrders']) . ' transaksi', 'kw' => 'dashboard omzet grafik']);

    if (in_array($role, ['owner', 'admin', 'cashier'])) {
        $add(['group' => 'utama', 'title' => 'Kasir / POS', 'icon' => 'shopping-cart', 'grad' => 'from-green-500 to-emerald-600',
              'href' => route('pos.index'), 'time' => $when($lastAt), 'pin' => true,
              'preview' => $activeShift ? 'Shift aktif · ' . ($activeShift->outlet->name ?? '') : 'Mulai transaksi baru', 'kw' => 'kasir pos jual bayar']);
        $add(['group' => 'utama', 'title' => 'Riwayat Order', 'icon' => 'clipboard-list', 'grad' => 'from-sky-500 to-blue-600',
              'href' => route('orders.index'), 'time' => $when($lastAt), 'badge' => $summary['todayOrders'] ?: null,
              'preview' => $last ? $last['order_number'] . ': ' . $rp($last['grand_total']) . ' · ' . ($statusTxt[$last['status']] ?? $last['status']) : 'Belum ada order',
              'tick' => (bool) $last, 'kw' => 'order transaksi nota struk']);
        $add(['group' => 'utama', 'title' => 'Shift Kasir', 'icon' => 'clock', 'grad' => 'from-amber-400 to-orange-500',
              'href' => route('shifts.index'), 'time' => $activeShift ? $when($activeShift->opened_at) : '', 'attn' => !$activeShift && $role === 'cashier',
              'preview' => $activeShift ? 'Berjalan sejak ' . $activeShift->opened_at->format('H.i') : 'Belum ada shift aktif', 'kw' => 'shift buka tutup kas']);
    }

    // ── Operasional ──
    if ($manager) {
        $add(['group' => 'operasional', 'title' => $isRetail ? 'Produk' : 'Produk / Menu', 'icon' => 'cube', 'grad' => 'from-violet-500 to-purple-600',
              'href' => route('products.index'), 'preview' => $stats['products'] . ' produk · ' . $stats['categories'] . ' kategori', 'kw' => 'produk menu barang harga']);
        $add(['group' => 'operasional', 'title' => 'Kategori', 'icon' => 'tag', 'grad' => 'from-pink-500 to-rose-600',
              'href' => route('categories.index'), 'preview' => $stats['categories'] . ' kategori', 'kw' => 'kategori kelompok']);
        $add(['group' => 'operasional', 'title' => 'Pelanggan', 'icon' => 'users', 'grad' => 'from-cyan-500 to-sky-600',
              'href' => route('customers.index'), 'preview' => $stats['customers'] . ' pelanggan', 'kw' => 'pelanggan member poin']);
        $add(['group' => 'operasional', 'title' => 'Promo & Diskon', 'icon' => 'badge-percent', 'grad' => 'from-red-500 to-orange-500',
              'href' => route('promotions.index'), 'preview' => $stats['promotions'] . ' promo', 'kw' => 'promo diskon voucher']);
    }
    if (!$isRetail && in_array($role, ['owner', 'admin', 'kitchen'])) {
        $add(['group' => 'operasional', 'title' => 'Dapur (KDS)', 'icon' => 'fire', 'grad' => 'from-orange-500 to-red-500',
              'href' => route('kitchen.index'), 'preview' => 'Pesanan yang sedang disiapkan', 'kw' => 'dapur kds kitchen masak']);
    }
    if (!$isRetail && in_array($role, ['owner', 'admin', 'warehouse'])) {
        $add(['group' => 'operasional', 'title' => 'Bahan Baku', 'icon' => 'beaker', 'grad' => 'from-teal-500 to-cyan-600',
              'href' => route('ingredients.index'), 'badge' => $lowCount ?: null, 'tone' => 'amber', 'attn' => $lowCount > 0,
              'preview' => $lowCount ? $lowCount . ' bahan baku menipis' : $stats['ingredients'] . ' bahan · stok aman', 'kw' => 'bahan baku stok persediaan habis menipis']);
        $add(['group' => 'operasional', 'title' => 'Resep', 'icon' => 'book-open', 'grad' => 'from-lime-500 to-green-600',
              'href' => route('recipes.index'), 'preview' => 'Takaran bahan tiap menu', 'kw' => 'resep takaran']);
        $add(['group' => 'operasional', 'title' => 'Pergerakan Stok', 'icon' => 'arrows-right-left', 'grad' => 'from-indigo-500 to-blue-600',
              'href' => route('inventory.movements'), 'preview' => 'Riwayat stok masuk dan keluar', 'kw' => 'stok mutasi pergerakan']);
    }

    // ── Laporan ──
    if ($manager) {
        $add(['group' => 'laporan', 'title' => 'Laporan Penjualan', 'icon' => 'chart-bar', 'grad' => 'from-emerald-500 to-green-600',
              'href' => route('reports.sales'), 'preview' => 'Omzet hari ini ' . $rp($summary['todayRevenue']), 'kw' => 'laporan penjualan omzet']);
        $add(['group' => 'laporan', 'title' => 'Produk Terlaris', 'icon' => 'chart-pie', 'grad' => 'from-fuchsia-500 to-purple-600',
              'href' => route('reports.products'), 'preview' => $topName ? 'Terlaris hari ini: ' . $topName : 'Menu paling laku', 'kw' => 'terlaris laris best seller']);
        $add(['group' => 'laporan', 'title' => 'Laporan Kasir', 'icon' => 'user-circle', 'grad' => 'from-sky-500 to-indigo-600',
              'href' => route('reports.cashier'), 'preview' => 'Penjualan per kasir', 'kw' => 'kasir laporan']);
        $add(['group' => 'laporan', 'title' => 'Laporan Shift', 'icon' => 'clock-rotate-left', 'grad' => 'from-amber-500 to-orange-600',
              'href' => route('reports.shift'), 'preview' => 'Riwayat dan selisih kas shift', 'kw' => 'shift laporan kas']);
        $add(['group' => 'laporan', 'title' => 'Laporan Inventory', 'icon' => 'archive', 'grad' => 'from-slate-500 to-slate-700',
              'href' => route('reports.inventory'), 'preview' => 'Nilai dan pergerakan stok', 'kw' => 'inventory stok laporan']);
        $add(['group' => 'laporan', 'title' => 'Estimasi Profit', 'icon' => 'banknotes', 'grad' => 'from-green-500 to-emerald-700',
              'href' => route('reports.profit'), 'preview' => 'Laba hari ini ' . $rp($summary['todayProfit']), 'kw' => 'profit laba untung']);
    }

    // ── Pengaturan ──
    if ($manager) {
        $add(['group' => 'pengaturan', 'title' => 'Outlet', 'icon' => 'building-storefront', 'grad' => 'from-stone-500 to-stone-700',
              'href' => route('outlets.index'), 'preview' => count($outlets) . ' outlet aktif', 'kw' => 'outlet cabang toko']);
        $add(['group' => 'pengaturan', 'title' => 'Manajemen User', 'icon' => 'user-group', 'grad' => 'from-blue-500 to-indigo-600',
              'href' => route('users.index'), 'preview' => $stats['users'] . ' pengguna', 'kw' => 'user pengguna staf kasir akses']);
        $add(['group' => 'pengaturan', 'title' => 'Profil Bisnis', 'icon' => 'building-office', 'grad' => 'from-gray-500 to-gray-700',
              'href' => route('settings.business'), 'preview' => $business->name, 'kw' => 'profil bisnis toko pengaturan']);
        $add(['group' => 'pengaturan', 'title' => 'Pengaturan Struk', 'icon' => 'receipt-percent', 'grad' => 'from-rose-500 to-pink-600',
              'href' => route('settings.receipt'), 'preview' => 'Tampilan struk dan pajak', 'kw' => 'struk pajak printer']);
        $add(['group' => 'pengaturan', 'title' => 'Bot Telegram', 'icon' => 'paper-airplane', 'grad' => 'from-sky-400 to-blue-600',
              'href' => route('settings.telegram'), 'attn' => !$tgLinked,
              'preview' => $tgLinked ? 'Terhubung · notifikasi aktif' : 'Belum terhubung', 'kw' => 'telegram bot notifikasi']);
    }

    $groups = [
        'all'         => ['Semua', count($rows)],
        'attn'        => ['Perlu perhatian', collect($rows)->where('attn', true)->count()],
        'pin'         => ['Favorit', collect($rows)->where('pin', true)->count()],
        'operasional' => ['Operasional', collect($rows)->where('group', 'operasional')->count()],
        'laporan'     => ['Laporan', collect($rows)->where('group', 'laporan')->count()],
        'pengaturan'  => ['Pengaturan', collect($rows)->where('group', 'pengaturan')->count()],
    ];
@endphp

<div class="page wa md:max-w-[520px] md:my-4 md:rounded-2xl md:border md:border-[var(--wa-frame)] md:shadow-xl overflow-hidden bg-[var(--wa-bg)] text-[var(--wa-fg)] -mx-4 sm:-mx-6 md:mx-auto min-h-[calc(100vh-56px)] md:min-h-[640px]"
     x-data="{
        q: '', f: 'all', theme: (() => { try { return localStorage.getItem('yoka.home.theme') || 'dark' } catch (e) { return 'dark' } })(),
        flip() { this.theme = this.theme === 'dark' ? 'light' : 'dark'; try { localStorage.setItem('yoka.home.theme', this.theme) } catch (e) {} },
        meta: @js(array_map(fn ($r) => [$r['group'], $r['attn'], $r['pin'], strtolower($r['title'] . ' ' . $r['preview'] . ' ' . ($r['kw'] ?? ''))], $rows)),
        get empty() { const t = this.q.trim().toLowerCase(); return !this.meta.some(m => (!t || m[3].includes(t)) && (this.f === 'all' || this.f === m[0] || (this.f === 'attn' && m[1]) || (this.f === 'pin' && m[2]))) }
     }" :data-theme="theme">

    {{-- Header --}}
    <div class="flex items-center justify-between gap-3 px-5 pt-5 pb-3">
        <div class="min-w-0">
            <h2 class="text-[24px] leading-8 font-bold tracking-tight truncate">{{ $business->name }}</h2>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <button type="button" @click="flip()" class="w-10 h-10 rounded-full flex items-center justify-center text-[var(--wa-mute)] hover:bg-[var(--wa-field)] transition-colors" :aria-label="theme === 'dark' ? 'Tema terang' : 'Tema gelap'">
                <svg x-show="theme === 'dark'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M12 3v2m0 14v2M3 12h2m14 0h2M5.6 5.6l1.4 1.4m10 10l1.4 1.4M5.6 18.4L7 17m10-10l1.4-1.4"/></svg>
                <svg x-show="theme !== 'dark'" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/></svg>
            </button>
            @if(in_array($role, ['owner', 'admin', 'cashier']))
            <a href="{{ route('pos.index') }}" class="w-10 h-10 rounded-full bg-[var(--wa-accent)] text-[var(--wa-badge-fg)] flex items-center justify-center shadow-sm hover:brightness-110 transition" aria-label="Transaksi baru">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
            </a>
            @endif
        </div>
    </div>

    {{-- Pencarian --}}
    <div class="px-4 pb-3">
        <label class="flex items-center gap-3 h-11 px-4 rounded-full bg-[var(--wa-field)] focus-within:ring-2 focus-within:ring-[var(--wa-accent)]/60 transition">
            <svg class="w-5 h-5 text-[var(--wa-mute)] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M21 21l-4.3-4.3"/></svg>
            <input type="search" x-model="q" placeholder="Cari menu atau laporan" autocomplete="off" aria-label="Cari menu"
                   class="w-full bg-transparent outline-none text-[15px] text-[var(--wa-fg)] placeholder:text-[var(--wa-mute)]">
        </label>
    </div>

    {{-- Chip filter --}}
    <div class="wa-scroll flex gap-2 px-4 pb-3 overflow-x-auto">
        @foreach($groups as $key => [$label, $n])
        @if($key === 'all' || $n > 0)
        <button type="button" @click="f = '{{ $key }}'"
                class="shrink-0 h-9 px-4 rounded-full text-[14px] font-medium transition-colors"
                :class="f === '{{ $key }}' ? 'bg-[var(--wa-chip-on-bg)] text-[var(--wa-chip-on-fg)]' : 'bg-[var(--wa-chip)] text-[var(--wa-mute)] hover:brightness-110'">
            {{ $label }}@if($key !== 'all') <span class="ml-1 opacity-80">{{ $n }}</span>@endif
        </button>
        @endif
        @endforeach
    </div>

    {{-- Daftar menu --}}
    <div class="pb-6">
        @foreach($rows as $i => $r)
        @php $search = strtolower($r['title'] . ' ' . $r['preview'] . ' ' . ($r['kw'] ?? '')); @endphp
        <a href="{{ $r['href'] }}" data-row
           :class="((!q.trim() || @js($search).includes(q.trim().toLowerCase())) && (f === 'all' || f === '{{ $r['group'] }}'{!! $r['attn'] ? " || f === 'attn'" : '' !!}{!! $r['pin'] ? " || f === 'pin'" : '' !!})) ? '' : '!hidden'"
           class="fade-up group flex items-center gap-3.5 pl-4 pr-4 hover:bg-[var(--wa-hover)] active:bg-[var(--wa-hover)] transition-colors" style="--i:{{ min($i, 7) * 0.6 }}">
            <span class="w-[52px] h-[52px] my-2.5 rounded-full bg-gradient-to-br {{ $r['grad'] }} text-white flex items-center justify-center shrink-0 shadow-sm">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="stroke-width:1.75">{!! NavIcons::path($r['icon']) !!}</svg>
            </span>
            <span class="flex-1 min-w-0 flex flex-col justify-center py-3 border-b border-[var(--wa-line)] self-stretch">
                <span class="flex items-baseline justify-between gap-3">
                    <span class="text-[17px] leading-6 font-medium truncate">{{ $r['title'] }}</span>
                    @if($r['time'])<time class="shrink-0 text-xs {{ $r['badge'] ? 'text-[var(--wa-accent)] font-medium' : 'text-[var(--wa-mute)]' }}">{{ $r['time'] }}</time>@endif
                </span>
                <span class="flex items-center gap-2 mt-0.5">
                    @if($r['tick'])
                    <svg class="w-[18px] h-[18px] shrink-0 text-[#53bdeb]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 13l4 4L15 8"/><path d="M9 15l2 2 9-9"/></svg>
                    @endif
                    <span class="flex-1 min-w-0 truncate text-[14.5px] text-[var(--wa-mute)]">{{ $r['preview'] }}</span>
                    @if($r['badge'])
                    <span class="shrink-0 min-w-[22px] h-[22px] px-1.5 rounded-full text-[12px] font-semibold flex items-center justify-center {{ $r['tone'] === 'amber' ? 'bg-amber-500 text-white' : 'bg-[var(--wa-accent)] text-[var(--wa-badge-fg)]' }}">{{ $r['badge'] }}</span>
                    @elseif($r['pin'])
                    <svg class="w-4 h-4 shrink-0 text-[var(--wa-mute)] rotate-45" viewBox="0 0 24 24" fill="currentColor"><path d="M16 3a1 1 0 01.7 1.7L15 6.4V11l2.3 2.3a1 1 0 01-.7 1.7H13v5a1 1 0 11-2 0v-5H7.4a1 1 0 01-.7-1.7L9 11V6.4L7.3 4.7A1 1 0 018 3h8z"/></svg>
                    @endif
                </span>
            </span>
        </a>
        @endforeach

        <p :class="empty ? '' : 'hidden'" class="px-6 py-14 text-center text-[var(--wa-mute)] hidden">Tidak ada menu yang cocok dengan pencarianmu.</p>
    </div>
</div>

