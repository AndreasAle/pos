@extends('layouts.app')
@section('title', 'Catat Biaya')
@section('page-title', 'Catat Biaya')
@section('content')
@php $in = 'h-10 w-full rounded-lg border-gray-300 text-sm focus:border-[#0C7856] focus:ring-[#0C7856]'; $lb = 'mb-1 block text-xs font-semibold text-gray-500'; $ml = ['cash' => 'Tunai', 'transfer' => 'Transfer', 'qris' => 'QRIS', 'ewallet' => 'E-wallet', 'debit' => 'Debit', 'other' => 'Lainnya']; @endphp
<div class="max-w-2xl text-[14px] text-gray-800">
@include('finance._flash')
<form method="POST" action="{{ route('expenses.store') }}" class="space-y-4 rounded-lg border border-gray-200 bg-white p-5">@csrf
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <label class="block"><span class="{{ $lb }}">Kategori (akun) *</span><select name="expense_category_id" required class="{{ $in }}"><option value="">Pilih kategori</option>@foreach($categories->groupBy('group') as $g => $cs)<optgroup label="{{ ['operating' => 'Beban operasional', 'other_expense' => 'Beban lain-lain', 'tax' => 'Setoran pajak'][$g] }}">@foreach($cs as $c)<option value="{{ $c->id }}" @selected(old('expense_category_id') == $c->id)>{{ $c->code }} · {{ $c->name }}</option>@endforeach</optgroup>@endforeach</select></label>
        <label class="block"><span class="{{ $lb }}">Tanggal biaya *</span><input type="date" name="expense_date" required max="{{ now()->toDateString() }}" value="{{ old('expense_date', now()->toDateString()) }}" class="{{ $in }}"></label>
        <label class="block"><span class="{{ $lb }}">Jumlah (Rp) *</span><input type="number" name="amount" required min="1" step="1" value="{{ old('amount') }}" class="{{ $in }}"></label>
        <label class="block"><span class="{{ $lb }}">Metode bayar</span><select name="payment_method" class="{{ $in }}">@foreach($ml as $k => $l)<option value="{{ $k }}" @selected(old('payment_method', 'cash') === $k)>{{ $l }}</option>@endforeach</select></label>
        <label class="block"><span class="{{ $lb }}">Dibayar kepada</span><input name="payee" maxlength="255" value="{{ old('payee') }}" class="{{ $in }}"></label>
        <label class="block"><span class="{{ $lb }}">No. referensi / kuitansi</span><input name="reference_no" maxlength="100" value="{{ old('reference_no') }}" class="{{ $in }}"></label>
        @if(count($outlets) > 0)<label class="block"><span class="{{ $lb }}">Outlet</span><select name="outlet_id" class="{{ $in }}"><option value="">Umum (semua outlet)</option>@foreach($outlets as $o)<option value="{{ $o->id }}" @selected(old('outlet_id') == $o->id)>{{ $o->name }}</option>@endforeach</select></label>@endif
    </div>
    <label class="block"><span class="{{ $lb }}">Catatan</span><textarea name="notes" rows="2" maxlength="1000" class="w-full rounded-lg border-gray-300 text-sm">{{ old('notes') }}</textarea></label>
    <p class="text-xs text-gray-500">Biaya dicatat pada tanggal biaya (basis kas). Pembelian stok bahan baku dicatat di menu Pembelian, bukan di sini.</p>
    <div class="flex gap-2"><button class="h-10 rounded-lg bg-[#0C7856] px-5 text-sm font-semibold text-white hover:bg-[#0a6a4c]">Simpan biaya</button><a href="{{ route('expenses.index') }}" class="inline-flex h-10 items-center px-3 text-sm text-gray-500">Batal</a></div>
</form></div>
@endsection
