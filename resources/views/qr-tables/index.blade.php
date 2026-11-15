@extends('layouts.app')
@section('title','QR Meja')
@section('page-title','QR Meja & Self Order')
@section('content')
<div class="page pt-2 text-[#0B1B3F]">
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div><h1 class="text-2xl font-bold">Area dan meja</h1><p class="mt-1 text-sm text-[#64748B]">QR bersifat tetap. Setiap kunjungan pelanggan dibuat sebagai sesi baru.</p></div>
        <form method="GET"><select name="outlet" onchange="this.form.submit()" class="h-10 rounded-lg border-[#D0D5DD] text-sm font-semibold">
            @foreach($outlets as $outlet)<option value="{{ $outlet->id }}" @selected($outletId===$outlet->id)>{{ $outlet->name }}</option>@endforeach
        </select></form>
    </div>

    <div class="mb-5 grid gap-4 sm:grid-cols-3">
        <x-kpi label="Total meja" :value="$tables->count()" sub="Pada outlet terpilih" />
        <x-kpi label="Sedang digunakan" :value="$tables->where('status','occupied')->count()" sub="Memiliki sesi aktif" tone="brand" />
        <x-kpi label="Pesanan aktif" :value="$tables->sum(fn($t) => $t->openSession?->orders->whereNotIn('status',['cancelled','refunded'])->count() ?? 0)" sub="Dari QR meja" />
    </div>

    <div class="grid gap-5 xl:grid-cols-[340px_1fr]">
        <div class="space-y-4">
            <x-panel title="Tambah area" sub="Contoh: Indoor, Teras, Lantai 2">
                <form method="POST" action="{{ route('qr-tables.areas.store') }}" class="space-y-3">@csrf
                    <input type="hidden" name="outlet_id" value="{{ $outletId }}">
                    <input name="name" required maxlength="80" placeholder="Nama area" class="h-11 w-full rounded-lg border-[#D0D5DD] text-sm">
                    <x-btn type="submit" class="w-full justify-center">Tambah area</x-btn>
                </form>
            </x-panel>
            <x-panel title="Tambah meja" sub="Sistem otomatis membuat token QR yang aman.">
                <form method="POST" action="{{ route('qr-tables.store') }}" class="space-y-3">@csrf
                    <input type="hidden" name="outlet_id" value="{{ $outletId }}">
                    <select name="dining_area_id" class="h-11 w-full rounded-lg border-[#D0D5DD] text-sm"><option value="">Tanpa area</option>@foreach($areas as $area)<option value="{{ $area->id }}">{{ $area->name }}</option>@endforeach</select>
                    <input name="name" required maxlength="40" placeholder="Contoh: A01" class="h-11 w-full rounded-lg border-[#D0D5DD] text-sm">
                    <x-btn type="submit" class="w-full justify-center">Buat meja dan QR</x-btn>
                </form>
            </x-panel>
        </div>

        <x-panel title="Daftar QR meja" sub="Cetak QR dan pasang pada meja yang sesuai." :flush="true">
            @if($tables->isEmpty())
                <x-empty title="Belum ada meja" text="Buat area lalu tambahkan meja pertama Anda." />
            @else
            <div class="grid gap-4 p-5 sm:grid-cols-2 2xl:grid-cols-3">
                @foreach($tables as $table)
                <article class="rounded-xl border border-[#E5E7EB] p-4 transition hover:shadow-md">
                    <div class="flex items-start justify-between gap-3">
                        <div><p class="text-lg font-bold">{{ $table->name }}</p><p class="text-xs text-[#64748B]">{{ $table->area?->name ?? 'Tanpa area' }}</p></div>
                        <x-pill :tone="$table->status === 'occupied' ? 'amber' : ($table->is_active ? 'green' : 'gray')">{{ !$table->is_active ? 'Nonaktif' : ($table->status === 'occupied' ? 'Terisi' : 'Tersedia') }}</x-pill>
                    </div>
                    <div class="my-4 flex justify-center rounded-xl bg-[#F8FAFC] p-3"><img class="h-32 w-32" src="{{ route('qr-tables.qr',$table) }}" alt="QR {{ $table->name }}"></div>
                    <div class="grid grid-cols-2 gap-2">
                        <x-btn :href="route('qr-tables.print',$table)" target="_blank" variant="soft" class="justify-center">Cetak</x-btn>
                        <form method="POST" action="{{ route('qr-tables.rotate',$table) }}" onsubmit="return confirm('QR lama tidak akan berlaku. Lanjutkan?')">@csrf @method('PATCH')<x-btn type="submit" variant="soft" class="w-full justify-center">Ganti URL</x-btn></form>
                    </div>
                    @if($table->openSession)
                    <form method="POST" action="{{ route('qr-tables.sessions.close',$table->openSession) }}" class="mt-2">@csrf<x-btn type="submit" variant="soft" class="w-full justify-center">Tutup sesi meja</x-btn></form>
                    @endif
                </article>
                @endforeach
            </div>
            @endif
        </x-panel>
    </div>
</div>
@endsection
