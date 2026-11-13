{{-- Form promo bersama untuk tambah dan edit.
     Butuh: $outlets, $categories, $action, $submitLabel, $cancelUrl, dan $promotion (null saat tambah). --}}
@php
    $pr     = $promotion ?? null;
    $isEdit = (bool) $pr;
    $val    = fn($key, $default = null) => old($key, $pr?->{$key} ?? $default);
    $dateVal = fn($key) => old($key, $pr?->{$key}?->format('Y-m-d'));
    $types = [
        'percent' => ['Persen (%)', 'Potong harga dengan persentase'],
        'nominal' => ['Nominal (Rp)', 'Potong harga dengan jumlah tetap'],
        'buy_get' => ['Beli X Gratis Y', 'Beli beberapa, gratis beberapa'],
    ];
@endphp

<form method="POST" action="{{ $action }}" class="page text-[#0B1B3F] pt-2"
      x-data="{ type: @js(old('type') ?: ($pr->type ?? 'percent')) }">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_340px] gap-4 items-start">

        <div class="space-y-4 min-w-0">
            <x-panel title="Informasi Promo" sub="Nama yang tampil di kasir dan kode opsional." class="fade-up" style="--i:1">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <x-field label="Nama Promo" name="name" :required="true" class="sm:col-span-2">
                        <x-input name="name" :value="$val('name')" required placeholder="Contoh: Diskon Weekend, Promo Grand Opening" />
                    </x-field>
                    <x-field label="Kode Promo" name="code" hint="Opsional. Kasir bisa mengetik kode ini.">
                        <x-input name="code" :value="$val('code')" placeholder="PROMO10" class="font-mono uppercase" />
                    </x-field>
                </div>
            </x-panel>

            <x-panel title="Jenis Diskon" sub="Pilih cara promo memotong harga." class="fade-up" style="--i:2">
                <input type="hidden" name="type" :value="type">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    @foreach($types as $key => [$title, $desc])
                    <button type="button" @click="type = '{{ $key }}'"
                            class="rounded-lg border p-3.5 text-left transition-colors"
                            :class="type === '{{ $key }}' ? 'border-[#16A34A] bg-[#ECFDF3] ring-1 ring-[#16A34A]' : 'border-[#E5E7EB] bg-white hover:border-[#16A34A]/50'">
                        <span class="block text-sm font-semibold" :class="type === '{{ $key }}' ? 'text-[#15803D]' : ''">{{ $title }}</span>
                        <span class="mt-0.5 block text-xs text-[#64748B]">{{ $desc }}</span>
                    </button>
                    @endforeach
                </div>
                @error('type')<p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>@enderror

                <div class="mt-5 space-y-5">
                    <div x-show="type !== 'buy_get'">
                        <x-field label="Nilai Diskon" name="value" :required="true" hint="Isi angka persen untuk tipe persen, atau rupiah untuk tipe nominal.">
                            <x-input name="value" type="number" :value="$val('value', 0)" min="0" step="0.01" x-bind:required="type !== 'buy_get'" />
                        </x-field>
                    </div>
                    @include('promotions._buy_get', ['promotion' => $pr])
                </div>
            </x-panel>

            <x-panel title="Syarat dan Periode" sub="Batas minimum order, masa berlaku, dan outlet." class="fade-up" style="--i:3">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <x-field label="Minimum Order" name="min_order" hint="Isi 0 agar berlaku untuk semua nilai order." class="sm:col-span-2">
                        <x-input name="min_order" type="number" prefix="Rp" :value="$val('min_order', 0)" min="0" step="1" inputmode="numeric" />
                    </x-field>
                    <x-field label="Tanggal Mulai" name="starts_at">
                        <x-input name="starts_at" type="date" :value="$dateVal('starts_at')" />
                    </x-field>
                    <x-field label="Tanggal Selesai" name="ends_at">
                        <x-input name="ends_at" type="date" :value="$dateVal('ends_at')" />
                    </x-field>
                    <x-field label="Berlaku di Outlet" name="outlet_id" class="sm:col-span-2">
                        <x-select name="outlet_id">
                            <option value="">Semua outlet</option>
                            @foreach($outlets as $outlet)
                            <option value="{{ $outlet->id }}" @selected((string) $val('outlet_id') === (string) $outlet->id)>{{ $outlet->name }}</option>
                            @endforeach
                        </x-select>
                    </x-field>
                </div>
            </x-panel>
        </div>

        <div class="space-y-4 lg:sticky lg:top-20">
            <x-panel title="Status" class="fade-up" style="--i:2">
                <x-switch name="is_active" :label="$isEdit ? 'Promo aktif' : 'Aktifkan langsung'" desc="Promo nonaktif tidak bisa dipakai di kasir." :checked="(bool) $val('is_active', true)" />
            </x-panel>

            <div class="fade-up flex flex-col gap-2" style="--i:3">
                <x-btn type="submit" size="lg">{{ $submitLabel }}</x-btn>
                <x-btn :href="$cancelUrl" variant="ghost">Batal</x-btn>
            </div>
        </div>
    </div>
</form>
