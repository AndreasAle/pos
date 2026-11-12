@extends('layouts.app')
@section('title', 'Catat Piutang')
@section('page-title', 'Catat Piutang')
@section('content')
@php $in = 'h-10 w-full rounded-lg border-gray-300 text-sm focus:border-[#0C7856] focus:ring-[#0C7856]'; $lb = 'mb-1 block text-xs font-semibold text-gray-500'; @endphp
<div class="max-w-2xl text-[14px] text-gray-800">
@include('finance._flash')
<form method="POST" action="{{ route('receivables.store') }}" class="space-y-4 rounded-lg border border-gray-200 bg-white p-5">@csrf
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <label class="block"><span class="{{ $lb }}">Nama pihak *</span><input name="party_name" required maxlength="255" value="{{ old('party_name') }}" class="{{ $in }}"></label>
    <label class="block"><span class="{{ $lb }}">Pelanggan terdaftar (opsional)</span><select name="customer_id" class="{{ $in }}"><option value="">-</option>@foreach($customers as $c)<option value="{{ $c->id }}" @selected(old('customer_id') == $c->id)>{{ $c->name }}</option>@endforeach</select></label>
    <label class="block"><span class="{{ $lb }}">Tanggal terbit *</span><input type="date" name="issue_date" required value="{{ old('issue_date', now()->toDateString()) }}" class="{{ $in }}"></label>
    <label class="block"><span class="{{ $lb }}">Jatuh tempo</span><input type="date" name="due_date" value="{{ old('due_date') }}" class="{{ $in }}"></label>
    <label class="block"><span class="{{ $lb }}">Jumlah (Rp) *</span><input type="number" name="amount" required min="1" value="{{ old('amount') }}" class="{{ $in }}"></label>
    @if(count($outlets))<label class="block"><span class="{{ $lb }}">Outlet</span><select name="outlet_id" class="{{ $in }}"><option value="">Umum</option>@foreach($outlets as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach</select></label>@endif
</div>
<label class="block"><span class="{{ $lb }}">Keterangan</span><input name="description" maxlength="255" value="{{ old('description') }}" class="{{ $in }}"></label>
<div class="flex gap-2"><button class="h-10 rounded-lg bg-[#0C7856] px-5 text-sm font-semibold text-white hover:bg-[#0a6a4c]">Simpan</button><a href="{{ route('receivables.index') }}" class="inline-flex h-10 items-center px-3 text-sm text-gray-500">Batal</a></div>
</form></div>
@endsection
