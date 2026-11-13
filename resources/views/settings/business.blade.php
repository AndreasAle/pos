@extends('layouts.app')
@section('title','Profil Bisnis')
@section('page-title','Pengaturan Bisnis')

@section('content')
<div class="page text-[#0B1B3F] pt-2">
    @include('settings._nav')

    <form method="POST" action="{{ route('settings.business.update') }}" enctype="multipart/form-data"
          x-data="{ preview: @js($business->logo ? asset('storage/'.$business->logo) : null), pick(e) { const f = e.target.files[0]; if (f) this.preview = URL.createObjectURL(f) } }"
          class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_340px] gap-4 items-start">
        @csrf

        <div class="space-y-4 min-w-0">
            <x-panel title="Informasi Bisnis" sub="Data ini tampil di struk dan laporan." class="fade-up" style="--i:1">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <x-field label="Nama Bisnis" name="name" :required="true" class="sm:col-span-2">
                        <x-input name="name" :value="old('name', $business->name)" required />
                    </x-field>
                    <x-field label="No. Telepon" name="phone">
                        <x-input name="phone" type="tel" :value="old('phone', $business->phone)" placeholder="08xx-xxxx-xxxx" />
                    </x-field>
                    <x-field label="Email" name="email">
                        <x-input name="email" type="email" :value="old('email', $business->email)" placeholder="nama@bisnis.com" />
                    </x-field>
                    <x-field label="Alamat" name="address" class="sm:col-span-2">
                        <x-textarea name="address" placeholder="Alamat lengkap bisnis">{{ old('address', $business->address) }}</x-textarea>
                    </x-field>
                </div>
            </x-panel>

            <x-panel title="Jenis Bisnis" sub="Menentukan menu yang tampil di aplikasi." class="fade-up" style="--i:2">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <x-radio name="business_type" value="fnb" label="Makanan & Minuman" desc="Cafe, resto, bakery. Ada Dine In, Dapur, Resep, dan Bahan Baku." :checked="! $business->isRetail()" />
                    <x-radio name="business_type" value="retail" label="Toko / Retail" desc="Baju, tas, aksesoris. Menyembunyikan Dine In, Dapur, Resep, dan Bahan Baku." :checked="$business->isRetail()" />
                </div>
                @error('business_type')<p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
            </x-panel>
        </div>

        <div class="space-y-4 lg:sticky lg:top-20">
            <x-panel title="Logo Bisnis" class="fade-up" style="--i:2">
                <label class="group relative block cursor-pointer overflow-hidden rounded-lg border-2 border-dashed border-[#E5E7EB] bg-slate-50 transition-colors hover:border-[#16A34A]/60">
                    <div class="aspect-square w-full">
                        <template x-if="preview"><img :src="preview" alt="" class="h-full w-full object-contain p-4"></template>
                        <div x-show="!preview" class="flex h-full w-full flex-col items-center justify-center gap-2 p-6 text-center">
                            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#ECFDF3] text-[#16A34A]">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1"/></svg>
                            </span>
                            <p class="text-sm font-semibold">Pilih logo bisnis</p>
                            <p class="text-xs text-[#64748B]">JPEG, PNG, atau WebP, maks 2 MB</p>
                        </div>
                    </div>
                    <input type="file" name="logo" accept="image/*" class="sr-only" @change="pick($event)">
                </label>
                <p class="mt-2 text-xs text-[#64748B]">Logo tampil di struk. Pilih gambar baru untuk mengganti.</p>
                @error('logo')<p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
            </x-panel>

            <div class="fade-up" style="--i:3">
                <x-btn type="submit" size="lg" class="w-full">Simpan Perubahan</x-btn>
            </div>
        </div>
    </form>
</div>
@endsection
