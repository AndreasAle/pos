@extends('layouts.app')
@section('title', 'Catat Pembelian')
@section('page-title', 'Catat Pembelian')
@section('page-subtitle', 'Catat belanja dari supplier. Stok bahan baku bertambah otomatis dan hutang tercatat bila belum lunas.')
@section('content')
@php $in = 'h-10 w-full rounded-lg text-sm'; $lb = 'mb-1 block text-xs font-semibold text-[#64748B]'; $ml = ['cash' => 'Tunai', 'transfer' => 'Transfer', 'qris' => 'QRIS', 'ewallet' => 'E-wallet', 'debit' => 'Debit', 'other' => 'Lainnya']; @endphp
<div class="y-report max-w-5xl text-[14px] text-[#0B1B3F]" x-data="purchaseForm(@js($ingredients->map(fn ($i) => ['id' => $i->id, 'name' => $i->name, 'unit' => $i->unit, 'cost' => (float) $i->average_cost])->values()), @js($suppliers->pluck('payment_terms_days', 'id')))">
@include('finance._flash')
@if($suppliers->isEmpty())<div class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Belum ada supplier. <a href="{{ route('suppliers.index') }}" class="font-semibold underline">Tambah supplier dulu</a>.</div>@endif
<form method="POST" action="{{ route('purchases.store') }}" class="space-y-4 rounded-xl border border-[#E5E7EB] bg-white p-5">@csrf
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <label class="block"><span class="{{ $lb }}">Supplier *</span><select name="supplier_id" required x-model="supplier" x-on:change="applyTerms()" class="{{ $in }}"><option value="">Pilih supplier</option>@foreach($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></label>
        <label class="block"><span class="{{ $lb }}">Tanggal pembelian *</span><input type="date" name="purchase_date" required x-model="date" x-on:change="applyTerms()" class="{{ $in }}"></label>
        <label class="block"><span class="{{ $lb }}">Jatuh tempo</span><input type="date" name="due_date" x-model="due" class="{{ $in }}"></label>
        <label class="block"><span class="{{ $lb }}">No. faktur supplier</span><input name="supplier_invoice_no" maxlength="100" value="{{ old('supplier_invoice_no') }}" class="{{ $in }}"></label>
        @if(count($outlets))<label class="block"><span class="{{ $lb }}">Outlet penerima</span><select name="outlet_id" class="{{ $in }}"><option value="">Umum</option>@foreach($outlets as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach</select></label>@endif
    </div>
    <div class="overflow-x-auto"><table class="w-full min-w-[720px] text-sm"><thead class="text-xs font-semibold text-[#64748B]"><tr><th class="px-2 py-2 text-left">Bahan baku (opsional)</th><th class="px-2 py-2 text-left">Deskripsi *</th><th class="px-2 py-2 text-left">Qty *</th><th class="px-2 py-2 text-left">Satuan</th><th class="px-2 py-2 text-left">Harga satuan *</th><th class="px-2 py-2 text-right">Subtotal</th><th></th></tr></thead>
    <tbody><template x-for="(it, i) in items" :key="i"><tr>
        <td class="px-2 py-1"><select :name="`items[${i}][ingredient_id]`" x-model="it.ingredient_id" x-on:change="pick(it)" class="{{ $in }}"><option value="">- tanpa stok -</option>@foreach($ingredients as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach</select></td>
        <td class="px-2 py-1"><input :name="`items[${i}][description]`" x-model="it.description" required maxlength="255" class="{{ $in }}"></td>
        <td class="px-2 py-1"><input type="number" step="0.001" min="0.001" :name="`items[${i}][qty]`" x-model.number="it.qty" required class="{{ $in }} w-24"></td>
        <td class="px-2 py-1"><input :name="`items[${i}][unit]`" x-model="it.unit" maxlength="20" class="{{ $in }} w-20"></td>
        <td class="px-2 py-1"><input type="number" step="1" min="0" :name="`items[${i}][unit_cost]`" x-model.number="it.unit_cost" required class="{{ $in }} w-32"></td>
        <td class="px-2 py-1 text-right tabular-nums" x-text="rp(it.qty * it.unit_cost)"></td>
        <td class="px-2 py-1"><button type="button" x-show="items.length > 1" x-on:click="items.splice(i, 1)" class="h-10 w-10 rounded text-[#94A3B8] hover:text-red-600" aria-label="Hapus baris">✕</button></td></tr></template></tbody></table></div>
    <button type="button" x-on:click="items.push(blank())" class="h-10 rounded-lg border border-[#E5E7EB] px-3 text-sm font-semibold text-[#334155] hover:border-[#16A34A] hover:text-[#15803D]">+ Tambah baris</button>
    <div class="grid grid-cols-1 gap-4 border-t border-[#E5E7EB] pt-4 sm:grid-cols-2">
        <div class="space-y-3">
            <label class="flex min-h-[40px] items-center gap-2 text-sm"><input type="checkbox" name="receive_stock" value="1" checked class="rounded border-[#E5E7EB] text-[#16A34A]"> Terima barang: tambah stok bahan baku &amp; perbarui harga rata-rata</label>
            <label class="block"><span class="{{ $lb }}">Dibayar sekarang (Rp)</span><input type="number" min="0" name="paid_now" x-model.number="paidNow" class="{{ $in }}"></label>
            <label class="block"><span class="{{ $lb }}">Metode bayar</span><select name="payment_method" class="{{ $in }}">@foreach($ml as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
            <label class="block"><span class="{{ $lb }}">Catatan</span><textarea name="notes" rows="2" maxlength="1000" class="w-full rounded-lg text-sm"></textarea></label>
        </div>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between"><span class="text-[#64748B]">Subtotal</span><span class="tabular-nums" x-text="rp(subtotal())"></span></div>
            <label class="flex items-center justify-between gap-3"><span class="text-[#64748B]">Diskon</span><input type="number" min="0" name="discount_amount" x-model.number="discount" class="{{ $in }} w-36 text-right"></label>
            <label class="flex items-center justify-between gap-3"><span class="text-[#64748B]">PPN masukan / pajak</span><input type="number" min="0" name="tax_amount" x-model.number="tax" class="{{ $in }} w-36 text-right"></label>
            <label class="flex items-center justify-between gap-3"><span class="text-[#64748B]">Ongkos kirim</span><input type="number" min="0" name="shipping_amount" x-model.number="shipping" class="{{ $in }} w-36 text-right"></label>
            <div class="flex justify-between border-t border-gray-200 pt-2 text-base font-bold"><span>Total tagihan</span><span class="tabular-nums" x-text="rp(total())"></span></div>
            <div class="flex justify-between text-[#475569]"><span>Sisa hutang</span><span class="tabular-nums" x-text="rp(Math.max(0, total() - paidNow))"></span></div>
        </div>
    </div>
    <div class="flex gap-2"><button class="h-10 rounded-lg bg-[#16A34A] px-5 text-sm font-semibold text-white hover:bg-[#15803D]">Simpan pembelian</button><a href="{{ route('purchases.index') }}" class="inline-flex h-10 items-center px-3 text-sm text-[#64748B]">Batal</a></div>
</form></div>
@push('scripts')
<script>
function purchaseForm(ingredients, terms) {
    return {
        supplier: '', date: new Date().toISOString().slice(0, 10), due: '', discount: 0, tax: 0, shipping: 0, paidNow: 0,
        items: [{ ingredient_id: '', description: '', qty: 1, unit: '', unit_cost: 0 }],
        blank() { return { ingredient_id: '', description: '', qty: 1, unit: '', unit_cost: 0 }; },
        rp(v) { return 'Rp ' + Math.round(v || 0).toLocaleString('id-ID'); },
        subtotal() { return this.items.reduce((s, i) => s + (i.qty || 0) * (i.unit_cost || 0), 0); },
        total() { return Math.max(0, this.subtotal() - (this.discount || 0) + (this.tax || 0) + (this.shipping || 0)); },
        pick(it) { const g = ingredients.find(x => String(x.id) === String(it.ingredient_id)); if (g) { it.description = it.description || g.name; it.unit = g.unit || ''; if (!it.unit_cost) it.unit_cost = Math.round(g.cost); } },
        applyTerms() { const d = terms[this.supplier]; if (d !== undefined && this.date) { const t = new Date(this.date); t.setDate(t.getDate() + Number(d)); this.due = t.toISOString().slice(0, 10); } },
    };
}
</script>
@endpush
@endsection
