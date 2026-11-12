@extends('layouts.app')
@section('title', 'Biaya')
@section('page-title', 'Biaya Operasional')
@section('content')
@php $card = 'min-w-0 rounded-lg border border-gray-200 bg-white'; $rp = fn ($v) => \App\Support\Money::rp($v); $showVoid = request('status') === 'void'; @endphp
<div class="text-[14px] text-gray-800">
@include('finance._flash')
@include('reports.fin._period', ['exports' => null, 'extra' => '<label class="block"><span class="mb-1 block text-xs font-semibold text-gray-500">Kategori</span><select name="category" class="h-10 rounded-lg border-gray-300 text-sm"><option value="">Semua</option>'.$categories->map(fn ($c) => '<option value="'.$c->id.'"'.(request('category') == $c->id ? ' selected' : '').'>'.e($c->code.' '.$c->name).'</option>')->implode('').'</select></label><label class="block"><span class="mb-1 block text-xs font-semibold text-gray-500">Status</span><select name="status" class="h-10 rounded-lg border-gray-300 text-sm"><option value="">Tercatat</option><option value="void"'.($showVoid ? ' selected' : '').'>Dibatalkan</option></select></label>'])

<div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
<section class="{{ $card }} overflow-hidden xl:col-span-2" aria-labelledby="el">
    <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3"><h2 id="el" class="text-sm font-semibold">{{ $showVoid ? 'Biaya dibatalkan' : 'Daftar biaya' }}</h2>
        <div class="flex gap-2"><a href="{{ route('expense-categories.index') }}" class="inline-flex h-9 items-center rounded-lg border border-gray-300 px-3 text-sm font-semibold text-gray-700 hover:border-[#0C7856]">Kategori</a><a href="{{ route('expenses.create') }}" class="inline-flex h-9 items-center rounded-lg bg-[#0C7856] px-3 text-sm font-semibold text-white hover:bg-[#0a6a4c]">Catat biaya</a></div></div>
    <div class="overflow-x-auto"><table class="w-full min-w-[680px] text-sm"><thead class="bg-gray-50 text-xs font-semibold text-gray-500"><tr><th scope="col" class="px-4 py-2.5 text-left">No.</th><th scope="col" class="px-4 py-2.5 text-left">Tanggal</th><th scope="col" class="px-4 py-2.5 text-left">Kategori</th><th scope="col" class="px-4 py-2.5 text-left">Penerima / catatan</th><th scope="col" class="px-4 py-2.5 text-left">Outlet</th><th scope="col" class="px-4 py-2.5 text-right">Jumlah</th><th scope="col" class="px-4 py-2.5"><span class="sr-only">Aksi</span></th></tr></thead>
    <tbody class="divide-y divide-gray-100">
    @forelse($expenses as $e)
        <tr class="{{ $e->status === 'void' ? 'text-gray-400' : '' }}"><td class="whitespace-nowrap px-4 py-2.5 font-medium">{{ $e->expense_number }}</td><td class="whitespace-nowrap px-4 py-2.5">{{ $e->expense_date->format('d M Y') }}</td><td class="px-4 py-2.5">{{ $e->category?->code }} {{ $e->category?->name }}</td>
            <td class="px-4 py-2.5">{{ $e->payee ?: '-' }}@if($e->notes)<span class="block text-xs text-gray-500">{{ \Illuminate\Support\Str::limit($e->notes, 60) }}</span>@endif @if($e->status === 'void')<span class="block text-xs">Batal: {{ $e->void_reason }}</span>@endif</td>
            <td class="px-4 py-2.5">{{ $e->outlet?->name ?? 'Umum' }}</td><td class="px-4 py-2.5 text-right tabular-nums {{ $e->status === 'void' ? 'line-through' : 'font-semibold' }}">{{ $rp((float) $e->amount) }}</td>
            <td class="px-4 py-2.5 text-right">@if($e->status === 'posted')<form method="POST" action="{{ route('expenses.void', $e) }}" x-data x-on:submit="const r = prompt('Alasan pembatalan biaya ini?'); if (!r) { $event.preventDefault(); return } $el.reason.value = r">@csrf<input type="hidden" name="reason"><button class="text-xs font-semibold text-red-600 hover:underline">Batalkan</button></form>@endif</td></tr>
    @empty<tr><td colspan="7" class="px-4 py-10 text-center text-gray-500">Belum ada biaya pada filter ini. Catat biaya agar laba rugi akurat.</td></tr>@endforelse
    </tbody></table></div>
    <div class="border-t border-gray-100 px-4 py-3">{{ $expenses->links() }}</div>
</section>
<section class="{{ $card }} p-4" aria-labelledby="et"><h2 id="et" class="text-sm font-semibold">Total per kategori</h2><p class="text-xs text-gray-500">Biaya tercatat pada periode dan outlet terpilih (biaya umum hanya muncul pada semua outlet).</p>
    <ul class="mt-3 divide-y divide-gray-100 text-sm">@forelse($totals['rows'] as $t)<li class="flex justify-between gap-3 py-2"><span>{{ $t['code'] }} {{ $t['name'] }} <span class="text-xs text-gray-400">({{ $t['entries'] }})</span></span><span class="tabular-nums">{{ $rp($t['amount']) }}</span></li>@empty<li class="py-6 text-center text-gray-400">Tidak ada data</li>@endforelse</ul>
    @if($totals['rows'])<p class="mt-2 flex justify-between border-t-2 border-[#0C7856]/30 pt-2 text-sm font-semibold"><span>Total</span><span class="tabular-nums">{{ $rp($totals['total']) }}</span></p>@endif
    <a href="{{ route('reports.profit-loss', $queryString) }}" class="mt-3 inline-block text-xs font-semibold text-[#0C7856] hover:underline">Lihat Laba Rugi →</a></section>
</div></div>
@endsection
