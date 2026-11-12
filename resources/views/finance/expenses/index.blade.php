@extends('layouts.app')
@section('title', 'Biaya')
@section('page-title', 'Biaya Operasional')
@section('page-subtitle', 'Pengeluaran rutin toko: sewa, listrik, gaji, dan lainnya. Dicatat di sini agar laba rugi akurat.')
@section('content')
@php $card = 'min-w-0 rounded-xl border border-[#E5E7EB] bg-white'; $rp = fn ($v) => \App\Support\Money::rp($v); $showVoid = request('status') === 'void'; @endphp
<div class="y-report text-[14px] text-[#0B1B3F]">
@include('finance._flash')
@include('reports.fin._period', ['exports' => null, 'extra' => '<label class="block"><span class="mb-1 block text-xs font-semibold text-[#64748B]">Kategori</span><select name="category" class="h-10 rounded-lg text-sm"><option value="">Semua</option>'.$categories->map(fn ($c) => '<option value="'.$c->id.'"'.(request('category') == $c->id ? ' selected' : '').'>'.e($c->code.' '.$c->name).'</option>')->implode('').'</select></label><label class="block"><span class="mb-1 block text-xs font-semibold text-[#64748B]">Status</span><select name="status" class="h-10 rounded-lg text-sm"><option value="">Tercatat</option><option value="void"'.($showVoid ? ' selected' : '').'>Dibatalkan</option></select></label>'])


@php
    $topCat = $totals['rows'][0] ?? null;
    $entryCount = collect($totals['rows'])->sum('entries');
    $dayCount = max(1, \Carbon\Carbon::parse($f->dateFrom())->diffInDays(\Carbon\Carbon::parse($f->dateTo())) + 1);
@endphp
<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    <x-kpi label="Total biaya" :value="$rp($totals['total'])" :sub="$entryCount.' catatan pada periode ini'" tone="brand"/>
    <x-kpi label="Rata-rata per hari" :value="$rp($totals['total'] / $dayCount)" :sub="$dayCount.' hari'"/>
    <x-kpi label="Kategori terbesar" :value="$topCat ? $topCat['name'] : '-'" :sub="$topCat ? $rp($topCat['amount']).' · '.($totals['total'] > 0 ? round($topCat['amount'] / $totals['total'] * 100) : 0).'% dari total' : 'Belum ada data'"/>
    <x-kpi label="Laba rugi" value="Lihat laporan" sub="Dampak biaya ke laba →" :href="route('reports.profit-loss', $queryString)"/>
</div>
<div class="mt-4"></div>
<div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
<section class="{{ $card }} overflow-hidden xl:col-span-2" aria-labelledby="el">
    <div class="flex items-center justify-between border-b border-[#E5E7EB] px-4 py-3"><h2 id="el" class="text-base font-semibold text-[#0B1B3F]">{{ $showVoid ? 'Biaya dibatalkan' : 'Daftar biaya' }}</h2>
        <div class="flex gap-2"><a href="{{ route('expense-categories.index') }}" class="inline-flex h-10 items-center rounded-lg border border-[#E5E7EB] px-3 text-sm font-semibold text-[#334155] hover:border-[#16A34A] hover:text-[#15803D]">Kategori</a><a href="{{ route('expenses.create') }}" class="inline-flex h-10 items-center rounded-lg bg-[#16A34A] px-3 text-sm font-semibold text-white hover:bg-[#15803D]">Catat biaya</a></div></div>
    <div class="overflow-x-auto"><table class="w-full min-w-[680px] text-sm"><thead class="text-xs"><tr><th scope="col" class="px-4 py-2.5 text-left">No.</th><th scope="col" class="px-4 py-2.5 text-left">Tanggal</th><th scope="col" class="px-4 py-2.5 text-left">Kategori</th><th scope="col" class="px-4 py-2.5 text-left">Penerima / catatan</th><th scope="col" class="px-4 py-2.5 text-left">Outlet</th><th scope="col" class="px-4 py-2.5 text-right">Jumlah</th><th scope="col" class="px-4 py-2.5"><span class="sr-only">Aksi</span></th></tr></thead>
    <tbody class="divide-y divide-[#E5E7EB]">
    @forelse($expenses as $e)
        <tr class="{{ $e->status === 'void' ? 'text-[#94A3B8]' : '' }}"><td class="whitespace-nowrap px-4 py-2.5 font-medium">{{ $e->expense_number }}</td><td class="whitespace-nowrap px-4 py-2.5">{{ $e->expense_date->format('d M Y') }}</td><td class="px-4 py-2.5">{{ $e->category?->code }} {{ $e->category?->name }}</td>
            <td class="px-4 py-2.5">{{ $e->payee ?: '-' }}@if($e->notes)<span class="block text-xs text-[#64748B]">{{ \Illuminate\Support\Str::limit($e->notes, 60) }}</span>@endif @if($e->status === 'void')<span class="block text-xs">Batal: {{ $e->void_reason }}</span>@endif</td>
            <td class="px-4 py-2.5">{{ $e->outlet?->name ?? 'Umum' }}</td><td class="px-4 py-2.5 text-right tabular-nums {{ $e->status === 'void' ? 'line-through' : 'font-semibold' }}">{{ $rp((float) $e->amount) }}</td>
            <td class="px-4 py-2.5 text-right">@if($e->status === 'posted')<form method="POST" action="{{ route('expenses.void', $e) }}" x-data x-on:submit="const r = prompt('Alasan pembatalan biaya ini?'); if (!r) { $event.preventDefault(); return } $el.reason.value = r">@csrf<input type="hidden" name="reason"><button class="text-xs font-semibold text-red-600 hover:underline">Batalkan</button></form>@endif</td></tr>
    @empty<tr><td colspan="7" class="p-0"><x-empty title="Belum ada biaya pada filter ini" text="Catat pengeluaran toko agar laporan laba rugi mencerminkan keadaan sebenarnya."><a href="{{ route('expenses.create') }}" class="inline-flex h-10 items-center rounded-lg bg-[#16A34A] px-4 text-sm font-semibold text-white hover:bg-[#15803D]">Catat biaya</a></x-empty></td></tr>@endforelse
    </tbody></table></div>
    <div class="border-t border-[#E5E7EB] px-4 py-3">{{ $expenses->links() }}</div>
</section>
<section class="{{ $card }} p-5" aria-labelledby="et"><h2 id="et" class="text-base font-semibold text-[#0B1B3F]">Total per kategori</h2><p class="text-xs text-[#64748B]">Biaya tercatat pada periode dan outlet terpilih (biaya umum hanya muncul pada semua outlet).</p>
    <ul class="mt-3 divide-y divide-[#E5E7EB] text-sm">@forelse($totals['rows'] as $t)<li class="py-2.5"><div class="flex justify-between gap-3"><span>{{ $t['name'] }} <span class="text-xs text-[#94A3B8]">{{ $t['code'] }} · {{ $t['entries'] }}x</span></span><span class="font-semibold tabular-nums">{{ $rp($t['amount']) }}</span></div><div class="mt-1.5 h-1.5 rounded-full bg-slate-100"><div class="h-1.5 rounded-full bg-[#16A34A]" style="width: {{ $totals['total'] > 0 ? round($t['amount'] / $totals['total'] * 100) : 0 }}%"></div></div></li>@empty<li class="py-6 text-center text-[#94A3B8]">Tidak ada data</li>@endforelse</ul>
    @if($totals['rows'])<p class="mt-2 flex justify-between border-t-2 border-[#16A34A]/30 pt-2 text-sm font-semibold"><span>Total</span><span class="tabular-nums">{{ $rp($totals['total']) }}</span></p>@endif
    <a href="{{ route('reports.profit-loss', $queryString) }}" class="mt-3 inline-block text-xs font-semibold text-[#16A34A] hover:underline">Lihat Laba Rugi →</a></section>
</div></div>
@endsection
