<x-device-shell title="Siapkan kasir" subtitle="Dua hal sebelum mulai jualan. Detail lain (kategori, varian, stok, laporan) bisa diatur nanti di back office lewat browser." width="max-w-2xl">
    @if(session('success'))
        <div class="mb-4 rounded-xl bg-[#ECFDF3] border border-[#BBF7D0] text-[#15803D] px-4 py-3 text-sm font-semibold">{{ session('success') }}</div>
    @endif

    <div class="grid gap-4 md:grid-cols-2">
        {{-- Produk --}}
        <section class="bg-white rounded-xl p-5 shadow-2xl">
            <div class="flex items-center justify-between">
                <h2 class="font-bold text-[#0B1B3F]">1. Produk</h2>
                <x-pill :tone="$products->isEmpty() ? 'amber' : 'green'">{{ $products->isEmpty() ? 'Belum ada' : $products->count() . ' produk' }}</x-pill>
            </div>
            <form method="POST" action="{{ route('device.start.product') }}" class="mt-4 space-y-3">
                @csrf
                <input name="name" required maxlength="120" placeholder="Nama produk, mis. Es Teh Manis" value="{{ old('name') }}"
                       class="w-full px-3.5 py-3 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-[#16A34A]">
                <div class="relative">
                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-sm font-semibold text-gray-500">Rp</span>
                    <input name="price" required type="number" min="0" step="100" inputmode="numeric" placeholder="Harga jual" value="{{ old('price') }}"
                           class="w-full pl-10 pr-3.5 py-3 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-[#16A34A]">
                </div>
                @error('name')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                @error('price')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                <button class="w-full bg-[#16A34A] hover:bg-emerald-700 text-white font-bold py-3 rounded-xl text-sm">Tambah produk</button>
            </form>
            @if($products->isNotEmpty())
                <ul class="mt-4 divide-y divide-gray-100 text-sm">
                    @foreach($products as $p)
                        <li class="flex justify-between py-2"><span>{{ $p->name }}</span><span class="font-semibold">Rp {{ number_format($p->price, 0, ',', '.') }}</span></li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Kasir --}}
        <section class="bg-white rounded-xl p-5 shadow-2xl">
            <div class="flex items-center justify-between">
                <h2 class="font-bold text-[#0B1B3F]">2. Kasir <span class="font-normal text-gray-400 text-sm">(opsional)</span></h2>
                <x-pill :tone="$cashiers->isEmpty() ? 'gray' : 'green'">{{ $cashiers->isEmpty() ? 'Anda sendiri' : $cashiers->count() . ' kasir' }}</x-pill>
            </div>
            <form method="POST" action="{{ route('device.start.cashier') }}" class="mt-4 space-y-3">
                @csrf
                <input name="name" required maxlength="80" placeholder="Nama kasir"
                       class="w-full px-3.5 py-3 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-[#16A34A]">
                <input name="pin" required type="password" inputmode="numeric" pattern="[0-9]*" minlength="4" maxlength="6" placeholder="PIN kasir (4–6 angka)"
                       class="w-full px-3.5 py-3 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-[#16A34A]">
                @error('pin')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                <button class="w-full border border-[#16A34A] text-[#16A34A] hover:bg-[#ECFDF3] font-bold py-3 rounded-xl text-sm">Tambah kasir</button>
            </form>
            @if($cashiers->isNotEmpty())
                <ul class="mt-4 divide-y divide-gray-100 text-sm">
                    @foreach($cashiers as $c)
                        <li class="flex justify-between py-2"><span>{{ $c->name }}</span><span class="text-gray-500">{{ $c->pin ? 'PIN aktif' : 'Belum ada PIN' }}</span></li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <a href="{{ route('pos.index') }}"
       class="block mt-5 text-center font-bold py-4 rounded-xl text-lg transition-colors
              {{ $products->isEmpty() ? 'bg-white/20 text-white/70 pointer-events-none' : 'bg-white text-[#0B1B3F] hover:bg-emerald-50' }}">
        {{ $products->isEmpty() ? 'Tambah minimal satu produk dulu' : 'Mulai jualan' }}
    </a>
</x-device-shell>
