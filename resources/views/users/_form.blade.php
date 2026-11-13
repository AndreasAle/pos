{{-- Form user bersama untuk tambah dan edit.
     Butuh: $outlets, $action, $submitLabel, $cancelUrl, dan $user (null saat tambah). --}}
@php
    $u      = $user ?? null;
    $isEdit = (bool) $u;
    $val    = fn($key, $default = null) => old($key, $u?->{$key} ?? $default);
    $roles  = $isEdit
        ? ['owner' => 'Owner', 'admin' => 'Admin', 'cashier' => 'Kasir', 'kitchen' => 'Dapur', 'warehouse' => 'Gudang']
        : ['admin' => 'Admin', 'cashier' => 'Kasir', 'kitchen' => 'Dapur', 'warehouse' => 'Gudang'];
@endphp

<form method="POST" action="{{ $action }}" class="page text-[#0B1B3F] pt-2 max-w-3xl space-y-4">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <x-panel title="Data User" sub="Identitas, role, dan outlet tempat staf bekerja." class="fade-up" style="--i:1">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <x-field label="Nama Lengkap" name="name" :required="true" class="sm:col-span-2">
                <x-input name="name" :value="$val('name')" required />
            </x-field>
            <x-field label="Email" name="email" :required="true">
                <x-input name="email" type="email" :value="$val('email')" required />
            </x-field>
            <x-field label="No. HP" name="phone">
                <x-input name="phone" type="tel" :value="$val('phone')" placeholder="08xx-xxxx-xxxx" />
            </x-field>
            <x-field label="Role" name="role" :required="true">
                <x-select name="role" required>
                    @unless($isEdit)<option value="">Pilih role</option>@endunless
                    @foreach($roles as $v => $l)
                    <option value="{{ $v }}" @selected($val('role') === $v)>{{ $l }}</option>
                    @endforeach
                </x-select>
            </x-field>
            <x-field label="Outlet" name="outlet_id">
                <x-select name="outlet_id">
                    <option value="">Semua outlet</option>
                    @foreach($outlets as $outlet)
                    <option value="{{ $outlet->id }}" @selected((string) $val('outlet_id') === (string) $outlet->id)>{{ $outlet->name }}</option>
                    @endforeach
                </x-select>
            </x-field>
        </div>
    </x-panel>

    <x-panel title="Password" :sub="$isEdit ? 'Kosongkan jika tidak ingin mengubah password.' : 'Minimal 8 karakter.'" class="fade-up" style="--i:2">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <x-field :label="$isEdit ? 'Password Baru' : 'Password'" name="password" :required="! $isEdit">
                <x-input name="password" type="password" :required="! $isEdit" autocomplete="new-password" placeholder="Minimal 8 karakter" />
            </x-field>
            <x-field :label="$isEdit ? 'Konfirmasi Password Baru' : 'Konfirmasi Password'" name="password_confirmation" :required="! $isEdit">
                <x-input name="password_confirmation" type="password" :required="! $isEdit" autocomplete="new-password" />
            </x-field>
        </div>
    </x-panel>

    <x-panel title="PIN Kasir" sub="Opsional, 4 sampai 8 angka. Untuk masuk cepat lewat tablet kasir." class="fade-up" style="--i:3">
        <x-field label="PIN" name="pin" :hint="$isEdit ? 'Kosongkan untuk membiarkan PIN yang sekarang.' : 'Dipakai di halaman /kasir tanpa email dan password. Pastikan berbeda dari PIN staf lain di outlet yang sama.'">
            <x-input name="pin" inputmode="numeric" pattern="[0-9]*" maxlength="8" autocomplete="off" class="font-mono tracking-widest" placeholder="Contoh: 234523" />
        </x-field>
        @if($isEdit && $u->pin)
        <label class="mt-4 flex cursor-pointer items-start gap-2.5 rounded-lg border border-[#E5E7EB] bg-slate-50 p-3.5 text-sm">
            <input type="checkbox" name="remove_pin" value="1" class="mt-0.5 h-4 w-4 rounded border-[#E5E7EB] accent-[#16A34A]">
            <span><span class="font-semibold">Hapus PIN</span><span class="block text-xs text-[#64748B]">Kasir ini tidak bisa lagi masuk lewat tablet.</span></span>
        </label>
        @endif
    </x-panel>

    @if($isEdit && $u->id !== auth()->id())
    <x-panel class="fade-up" style="--i:4">
        <x-switch name="is_active" label="User aktif" desc="User nonaktif tidak bisa masuk ke aplikasi." :checked="(bool) $val('is_active', true)" />
    </x-panel>
    @endif

    <div class="fade-up flex flex-wrap items-center gap-2" style="--i:5">
        <x-btn type="submit">{{ $submitLabel }}</x-btn>
        <x-btn :href="$cancelUrl" variant="ghost">Batal</x-btn>
    </div>
</form>
