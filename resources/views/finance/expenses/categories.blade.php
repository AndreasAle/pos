@extends('layouts.app')
@section('title', 'Kategori Biaya')
@section('page-title', 'Kategori Biaya (Akun)')
@section('page-subtitle', 'Akun biaya yang dipakai di laporan laba rugi. Tambah akun sendiri bila perlu.')
@section('content')
@php $in = 'h-10 w-full rounded-lg text-sm'; $lb = 'mb-1 block text-xs font-semibold text-[#64748B]'; $gl = ['operating' => 'Beban operasional', 'other_expense' => 'Beban lain-lain', 'tax' => 'Setoran pajak']; @endphp
<div class="y-report grid grid-cols-1 gap-4 text-[14px] text-[#0B1B3F] xl:grid-cols-3">
<div class="xl:col-span-3">@include('finance._flash')</div>
<section class="overflow-hidden rounded-xl border border-[#E5E7EB] bg-white xl:col-span-2"><h2 class="border-b border-[#E5E7EB] px-4 py-3 text-sm font-semibold">Daftar akun biaya</h2>
<div class="overflow-x-auto"><table class="w-full min-w-[520px] text-sm"><thead class="text-xs"><tr><th scope="col" class="px-4 py-2.5 text-left">Kode</th><th scope="col" class="px-4 py-2.5 text-left">Nama</th><th scope="col" class="px-4 py-2.5 text-left">Kelompok</th><th scope="col" class="px-4 py-2.5 text-right">Entri</th><th scope="col" class="px-4 py-2.5"><span class="sr-only">Aksi</span></th></tr></thead>
<tbody class="divide-y divide-[#E5E7EB]">@foreach($categories as $c)<tr class="{{ $c->is_active ? '' : 'text-[#94A3B8]' }}"><td class="px-4 py-2.5 font-mono">{{ $c->code }}</td><td class="px-4 py-2.5">{{ $c->name }}</td><td class="px-4 py-2.5">{{ $gl[$c->group] }}</td><td class="px-4 py-2.5 text-right tabular-nums">{{ $c->expenses_count }}</td><td class="px-4 py-2.5 text-right"><form method="POST" action="{{ route('expense-categories.toggle', $c) }}">@csrf<button class="text-xs font-semibold text-[#16A34A] hover:underline">{{ $c->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form></td></tr>@endforeach</tbody></table></div></section>
<section class="rounded-xl border border-[#E5E7EB] bg-white p-4"><h2 class="text-base font-semibold text-[#0B1B3F]">Tambah akun</h2>
<form method="POST" action="{{ route('expense-categories.store') }}" class="mt-3 space-y-3">@csrf
<label class="block"><span class="{{ $lb }}">Kode (4 angka) *</span><input name="code" required pattern="[0-9]{4}" maxlength="4" value="{{ old('code') }}" class="{{ $in }}"></label>
<label class="block"><span class="{{ $lb }}">Nama *</span><input name="name" required maxlength="100" value="{{ old('name') }}" class="{{ $in }}"></label>
<label class="block"><span class="{{ $lb }}">Kelompok</span><select name="group" class="{{ $in }}">@foreach($gl as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
<button class="h-10 rounded-lg bg-[#16A34A] px-4 text-sm font-semibold text-white hover:bg-[#15803D]">Tambah</button>
<p class="text-xs text-[#64748B]">Kode 6xxx = beban operasional, 7xxx = beban lain-lain, 2105 = setoran pajak (mengurangi saldo pajak, bukan laba).</p></form></section>
</div>
@endsection
