{{-- Form pelanggan bersama untuk tambah dan edit.
     Butuh: $action, $submitLabel, $cancelUrl, dan $customer (null saat tambah). --}}
@php
    $c      = $customer ?? null;
    $isEdit = (bool) $c;
    $val    = fn($key, $default = null) => old($key, $c?->{$key} ?? $default);
@endphp

<form method="POST" action="{{ $action }}" class="page text-[#0B1B3F] pt-2 max-w-3xl">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <x-panel title="Data Pelanggan" sub="Nama wajib diisi, kontak membantu mengenali pelanggan di kasir." class="fade-up" style="--i:1">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <x-field label="Nama Lengkap" name="name" :required="true" class="sm:col-span-2">
                <x-input name="name" :value="$val('name')" required placeholder="Nama pelanggan" />
            </x-field>
            <x-field label="No. HP" name="phone">
                <x-input name="phone" type="tel" :value="$val('phone')" placeholder="08xx-xxxx-xxxx" inputmode="tel" />
            </x-field>
            <x-field label="Email" name="email">
                <x-input name="email" type="email" :value="$val('email')" placeholder="nama@email.com" />
            </x-field>
            @if($isEdit)
            <div class="sm:col-span-2">
                <x-switch name="is_active" label="Pelanggan aktif" desc="Pelanggan nonaktif tidak muncul saat dipilih di kasir." :checked="(bool) $val('is_active', true)" />
            </div>
            @endif
        </div>
    </x-panel>

    <div class="fade-up mt-4 flex flex-wrap items-center gap-2" style="--i:2">
        <x-btn type="submit">{{ $submitLabel }}</x-btn>
        <x-btn :href="$cancelUrl" variant="ghost">Batal</x-btn>
    </div>
</form>
