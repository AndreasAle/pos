@extends('layouts.app')
@section('title', 'Supplier')
@section('page-title', 'Supplier')
@section('content')
@php $in = 'h-10 w-full rounded-lg border-gray-300 text-sm focus:border-[#0C7856] focus:ring-[#0C7856]'; $lb = 'mb-1 block text-xs font-semibold text-gray-500'; @endphp
<div class="grid grid-cols-1 gap-4 text-[14px] text-gray-800 xl:grid-cols-3">
<div class="xl:col-span-3">@include('finance._flash')</div>
<section class="overflow-hidden rounded-lg border border-gray-200 bg-white xl:col-span-2" aria-labelledby="sl"><h2 id="sl" class="border-b border-gray-100 px-4 py-3 text-sm font-semibold">Daftar supplier</h2>
<div class="overflow-x-auto"><table class="w-full min-w-[560px] text-sm"><thead class="bg-gray-50 text-xs font-semibold text-gray-500"><tr><th scope="col" class="px-4 py-2.5 text-left">Nama</th><th scope="col" class="px-4 py-2.5 text-left">Kontak</th><th scope="col" class="px-4 py-2.5 text-right">Tempo</th><th scope="col" class="px-4 py-2.5 text-right">Pembelian</th><th scope="col" class="px-4 py-2.5"><span class="sr-only">Aksi</span></th></tr></thead>
<tbody class="divide-y divide-gray-100">@forelse($suppliers as $s)<tr><td class="px-4 py-2.5 font-medium">{{ $s->name }}@unless($s->is_active)<span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-600">nonaktif</span>@endunless</td><td class="px-4 py-2.5 text-gray-600">{{ $s->phone ?: ($s->email ?: '-') }}</td><td class="px-4 py-2.5 text-right tabular-nums">{{ $s->payment_terms_days }} hari</td><td class="px-4 py-2.5 text-right tabular-nums"><a class="text-[#0C7856] hover:underline" href="{{ route('purchases.index', ['supplier' => $s->id]) }}">{{ $s->purchases_count }}</a></td><td class="px-4 py-2.5 text-right"><a class="text-sm font-semibold text-[#0C7856] hover:underline" href="{{ route('suppliers.edit', $s) }}">Ubah</a></td></tr>@empty<tr><td colspan="5" class="px-4 py-10 text-center text-gray-500">Belum ada supplier.</td></tr>@endforelse</tbody></table></div></section>
<section class="rounded-lg border border-gray-200 bg-white p-4" aria-labelledby="sf"><h2 id="sf" class="text-sm font-semibold">{{ $edit ? 'Ubah supplier' : 'Tambah supplier' }}</h2>
<form method="POST" action="{{ $edit ? route('suppliers.update', $edit) : route('suppliers.store') }}" class="mt-3 space-y-3">@csrf
<label class="block"><span class="{{ $lb }}">Nama *</span><input name="name" required maxlength="255" value="{{ old('name', $edit->name ?? '') }}" class="{{ $in }}"></label>
<label class="block"><span class="{{ $lb }}">Kontak</span><input name="contact_person" maxlength="255" value="{{ old('contact_person', $edit->contact_person ?? '') }}" class="{{ $in }}"></label>
<div class="grid grid-cols-2 gap-3"><label class="block"><span class="{{ $lb }}">Telepon</span><input name="phone" maxlength="30" value="{{ old('phone', $edit->phone ?? '') }}" class="{{ $in }}"></label><label class="block"><span class="{{ $lb }}">Tempo (hari)</span><input type="number" min="0" max="365" name="payment_terms_days" value="{{ old('payment_terms_days', $edit->payment_terms_days ?? 0) }}" class="{{ $in }}"></label></div>
<label class="block"><span class="{{ $lb }}">Email</span><input type="email" name="email" maxlength="255" value="{{ old('email', $edit->email ?? '') }}" class="{{ $in }}"></label>
<label class="block"><span class="{{ $lb }}">NPWP</span><input name="tax_id" maxlength="40" value="{{ old('tax_id', $edit->tax_id ?? '') }}" class="{{ $in }}"></label>
<label class="block"><span class="{{ $lb }}">Alamat</span><textarea name="address" rows="2" maxlength="500" class="w-full rounded-lg border-gray-300 text-sm">{{ old('address', $edit->address ?? '') }}</textarea></label>
@if($edit)<label class="flex min-h-[40px] items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($edit->is_active) class="rounded border-gray-300 text-[#0C7856]"> Aktif</label>@endif
<div class="flex gap-2"><button class="h-10 rounded-lg bg-[#0C7856] px-4 text-sm font-semibold text-white hover:bg-[#0a6a4c]">Simpan</button>@if($edit)<a href="{{ route('suppliers.index') }}" class="inline-flex h-10 items-center px-3 text-sm text-gray-500">Batal</a>@endif</div></form></section>
</div>
@endsection
