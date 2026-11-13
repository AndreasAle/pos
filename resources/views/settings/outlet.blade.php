@extends('layouts.app')
@section('title','Pengaturan Outlet')
@section('page-title','Pengaturan Outlet')

@section('content')
<div class="page text-[#0B1B3F] pt-2">
    @include('settings._nav')

    <div class="space-y-4 max-w-4xl">
        @forelse($outlets as $outlet)
        <x-panel class="fade-up" style="--i:{{ min($loop->index, 5) + 1 }}">
            <form method="POST" action="{{ route('settings.outlet.update') }}">
                @csrf
                <input type="hidden" name="outlet_id" value="{{ $outlet->id }}">

                <div class="mb-5 flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-[#ECFDF3] text-[#16A34A]">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2zM9 22V12h6v10"/></svg>
                    </span>
                    <div class="min-w-0">
                        <h2 class="truncate text-base font-semibold">{{ $outlet->name }}</h2>
                        @if($outlet->code)<p class="font-mono text-xs text-[#64748B]">{{ $outlet->code }}</p>@endif
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <x-field label="Nama Outlet" name="name">
                        <x-input name="name" :value="old('name', $outlet->name)" required />
                    </x-field>
                    <x-field label="Kode Outlet" name="code">
                        <x-input name="code" :value="old('code', $outlet->code)" class="font-mono" />
                    </x-field>
                    <x-field label="No. Telepon" name="phone">
                        <x-input name="phone" type="tel" :value="old('phone', $outlet->phone)" />
                    </x-field>
                    <x-field label="Alamat" name="address">
                        <x-input name="address" :value="old('address', $outlet->address)" />
                    </x-field>
                </div>

                <div class="mt-5"><x-btn type="submit">Simpan Outlet</x-btn></div>
            </form>
        </x-panel>
        @empty
        <section class="fade-up rounded-xl border border-[#E5E7EB] bg-white" style="--i:1">
            <x-empty title="Belum ada outlet" text="Tambahkan outlet untuk mulai beroperasi." icon="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2zM9 22V12h6v10">
                <x-btn :href="route('outlets.create')">Tambah outlet</x-btn>
            </x-empty>
        </section>
        @endforelse
    </div>
</div>
@endsection
