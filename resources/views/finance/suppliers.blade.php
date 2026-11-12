@extends('layouts.app')
@section('title', 'Supplier')
@section('page-title', 'Supplier')
@section('page-subtitle', 'Daftar pemasok bahan baku, termasuk tempo pembayaran, total belanja, dan hutang yang masih berjalan.')
@section('content')
@php
    $in = 'h-10 w-full px-3'; $lb = 'mb-1.5 block text-xs font-semibold text-[#64748B]';
    $rp = fn ($v) => \App\Support\Money::rp($v);
    $due = fn ($s) => max(0, (float) $s->purchases_total - (float) $s->purchases_paid);
    $totalDebt = $suppliers->sum($due);
    $totalBuy = $suppliers->sum(fn ($s) => (float) $s->purchases_total);
    $active = $suppliers->where('is_active', true)->count();
@endphp
<div class="y-report">
@include('finance._flash')

<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    <x-kpi label="Supplier aktif" :value="$active" :sub="$suppliers->count().' terdaftar'" tone="brand"/>
    <x-kpi label="Total pembelian" :value="$rp($totalBuy)" sub="Seluruh waktu, tanpa yang dibatalkan"/>
    <x-kpi label="Hutang berjalan" :value="$rp($totalDebt)" :sub="$totalDebt > 0 ? 'Lihat umur hutang →' : 'Semua lunas'" :tone="$totalDebt > 0 ? 'warn' : 'default'" :href="route('payables.index')"/>
    <x-kpi label="Catat belanja" value="Pembelian baru" sub="Tambah stok & hutang →" :href="route('purchases.create')"/>
</div>

<div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
    <x-panel flush class="xl:col-span-2" title="Daftar supplier" :sub="$suppliers->count().' supplier'">
        @if($suppliers->count())
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-sm">
                <thead><tr><th scope="col" class="px-5 py-3 text-left">Supplier</th><th scope="col" class="px-5 py-3 text-left">Kontak</th><th scope="col" class="px-5 py-3 text-right">Tempo</th><th scope="col" class="px-5 py-3 text-right">Pembelian</th><th scope="col" class="px-5 py-3 text-right">Hutang</th><th scope="col" class="px-5 py-3"><span class="sr-only">Aksi</span></th></tr></thead>
                <tbody class="divide-y divide-[#E5E7EB]">
                @foreach($suppliers as $s)
                    @php $debt = $due($s); @endphp
                    <tr class="{{ $s->is_active ? '' : 'opacity-60' }}">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#ECFDF3] text-sm font-bold text-[#16A34A]">{{ strtoupper(mb_substr($s->name, 0, 1)) }}</span>
                                <div class="min-w-0"><p class="truncate font-semibold">{{ $s->name }}@unless($s->is_active)<x-pill class="ml-1.5">Nonaktif</x-pill>@endunless</p>@if($s->contact_person)<p class="truncate text-xs text-[#64748B]">{{ $s->contact_person }}</p>@endif</div>
                            </div>
                        </td>
                        <td class="px-5 py-3.5 text-[#475569]">{{ $s->phone ?: ($s->email ?: '-') }}</td>
                        <td class="px-5 py-3.5 text-right whitespace-nowrap">{{ $s->payment_terms_days ? $s->payment_terms_days.' hari' : 'Tunai' }}</td>
                        <td class="px-5 py-3.5 text-right tabular-nums"><a class="font-semibold text-[#16A34A] hover:underline" href="{{ route('purchases.index', ['supplier' => $s->id]) }}">{{ $s->purchases_count }}</a><span class="block text-xs text-[#64748B]">{{ $rp((float) $s->purchases_total) }}</span></td>
                        <td class="px-5 py-3.5 text-right tabular-nums {{ $debt > 0 ? 'font-bold text-amber-700' : 'text-[#94A3B8]' }}">{{ $debt > 0 ? $rp($debt) : '–' }}</td>
                        <td class="px-5 py-3.5 text-right"><a class="inline-flex h-9 items-center rounded-lg border border-[#E5E7EB] px-3 text-[13px] font-semibold hover:border-[#16A34A] hover:text-[#15803D]" href="{{ route('suppliers.edit', $s) }}">Ubah</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @else
            <x-empty title="Belum ada supplier" text="Tambahkan supplier lewat form di samping, lalu catat pembelian untuk menambah stok bahan baku." icon="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
        @endif
    </x-panel>

    <x-panel :title="$edit ? 'Ubah supplier' : 'Tambah supplier'" :sub="$edit ? $edit->name : 'Isi data dasar, sisanya opsional.'" class="self-start">
        <form method="POST" action="{{ $edit ? route('suppliers.update', $edit) : route('suppliers.store') }}" class="space-y-3.5">@csrf
            <label class="block"><span class="{{ $lb }}">Nama *</span><input name="name" required maxlength="255" value="{{ old('name', $edit->name ?? '') }}" class="{{ $in }}"></label>
            <label class="block"><span class="{{ $lb }}">Nama kontak</span><input name="contact_person" maxlength="255" value="{{ old('contact_person', $edit->contact_person ?? '') }}" class="{{ $in }}"></label>
            <div class="grid grid-cols-2 gap-3">
                <label class="block"><span class="{{ $lb }}">Telepon</span><input name="phone" maxlength="30" value="{{ old('phone', $edit->phone ?? '') }}" class="{{ $in }}"></label>
                <label class="block"><span class="{{ $lb }}">Tempo (hari)</span><input type="number" min="0" max="365" name="payment_terms_days" value="{{ old('payment_terms_days', $edit->payment_terms_days ?? 0) }}" class="{{ $in }}"></label>
            </div>
            <label class="block"><span class="{{ $lb }}">Email</span><input type="email" name="email" maxlength="255" value="{{ old('email', $edit->email ?? '') }}" class="{{ $in }}"></label>
            <label class="block"><span class="{{ $lb }}">NPWP</span><input name="tax_id" maxlength="40" value="{{ old('tax_id', $edit->tax_id ?? '') }}" class="{{ $in }}"></label>
            <label class="block"><span class="{{ $lb }}">Alamat</span><textarea name="address" rows="2" maxlength="500" class="w-full px-3 py-2">{{ old('address', $edit->address ?? '') }}</textarea></label>
            @if($edit)<label class="flex min-h-[40px] items-center gap-2 text-sm font-medium"><input type="checkbox" name="is_active" value="1" @checked($edit->is_active) class="h-4 w-4 rounded"> Supplier aktif</label>@endif
            <div class="flex gap-2 pt-1"><button class="h-10 rounded-lg bg-[#16A34A] px-5 text-sm font-semibold text-white shadow-sm hover:bg-[#15803D]">Simpan</button>@if($edit)<a href="{{ route('suppliers.index') }}" class="inline-flex h-10 items-center rounded-lg px-4 text-sm font-semibold text-[#64748B] hover:bg-slate-100">Batal</a>@endif</div>
        </form>
    </x-panel>
</div>
</div>
@endsection
