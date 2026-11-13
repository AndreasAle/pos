{{-- Form outlet bersama untuk tambah dan edit.
     Butuh: $action, $submitLabel, $cancelUrl, dan $outlet (null saat tambah). --}}
@php
    $o      = $outlet ?? null;
    $isEdit = (bool) $o;
    $val    = fn($key, $default = null) => old($key, $o?->{$key} ?? $default);
@endphp

<form method="POST" action="{{ $action }}" class="page text-[#0B1B3F] pt-2 max-w-3xl">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <x-panel title="Informasi Outlet" sub="Data cabang yang tampil di struk dan laporan." class="fade-up" style="--i:1">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <x-field label="Nama Outlet" name="name" :required="true">
                <x-input name="name" :value="$val('name')" required placeholder="Contoh: Outlet Cabang Selatan" />
            </x-field>
            <x-field label="Kode Outlet" name="code" hint="Dipakai untuk login PIN kasir.">
                <x-input name="code" :value="$val('code')" class="font-mono" placeholder="Contoh: CB-01" />
            </x-field>
            <x-field label="No. Telepon" name="phone">
                <x-input name="phone" type="tel" :value="$val('phone')" placeholder="08xx-xxxx-xxxx" />
            </x-field>
            <x-field label="Alamat" name="address" class="sm:col-span-2">
                <x-textarea name="address" placeholder="Alamat lengkap outlet">{{ $val('address') }}</x-textarea>
            </x-field>
            @if($isEdit)
            <div class="sm:col-span-2">
                <x-switch name="is_active" label="Outlet aktif" desc="Outlet nonaktif tidak bisa dipakai untuk transaksi baru." :checked="(bool) $val('is_active', true)" />
            </div>
            @endif
        </div>
    </x-panel>

    <div class="fade-up mt-4 flex flex-wrap items-center gap-2" style="--i:2">
        <x-btn type="submit">{{ $submitLabel }}</x-btn>
        <x-btn :href="$cancelUrl" variant="ghost">Batal</x-btn>
    </div>
</form>
