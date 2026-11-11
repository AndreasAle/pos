@extends('layouts.platform')
@section('title', 'Semua Toko')
@section('content')
<div class="mb-7"><p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-700">Tenant directory</p><h1 class="mt-1 text-3xl font-black">Semua Toko</h1><p class="mt-1 text-sm text-slate-500">Cari tenant berdasarkan nama toko atau email owner.</p></div>

<form method="GET" class="mb-5 grid gap-3 border border-slate-200 bg-white p-4 md:grid-cols-[1fr_220px_auto]">
    <input type="search" name="q" value="{{ request('q') }}" class="border border-slate-300 px-4 py-3 text-sm focus:border-emerald-600 focus:outline-none" placeholder="Nama toko atau email owner">
    <select name="status" class="border border-slate-300 px-4 py-3 text-sm focus:border-emerald-600 focus:outline-none"><option value="">Semua status</option>@foreach(['active'=>'Aktif','trial'=>'Trial','overdue'=>'Tertunggak','disabled'=>'Dinonaktifkan'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select>
    <button class="bg-slate-900 px-6 py-3 text-sm font-bold text-white hover:bg-slate-800">Terapkan</button>
</form>

<section class="overflow-hidden border border-slate-200 bg-white">
    <div class="overflow-x-auto"><table class="w-full min-w-[900px] text-sm"><thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-3">Toko & owner</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Paket</th><th class="px-5 py-3">Data</th><th class="px-5 py-3">Terdaftar</th><th class="px-5 py-3">Aktivitas terakhir</th></tr></thead><tbody class="divide-y divide-slate-100">
    @forelse($businesses as $business)
        @php $status=$business->platformStatus(); $labels=['active'=>'Aktif','trial'=>'Trial','overdue'=>'Tertunggak','disabled'=>'Dinonaktifkan','stopped'=>'Berhenti']; @endphp
        <tr class="hover:bg-slate-50"><td class="px-5 py-4"><a href="{{ route('platform.businesses.show',$business) }}" class="font-bold text-slate-900 hover:text-emerald-700">{{ $business->name }}</a><p class="mt-1 text-xs text-slate-500">{{ $business->owner?->email ?? $business->email ?? 'Owner belum ada' }}</p></td><td class="px-5 py-4"><span class="border border-slate-300 px-2 py-1 text-xs font-bold">{{ $labels[$status] ?? $status }}</span></td><td class="px-5 py-4"><p class="font-semibold">{{ $business->latestSubscription?->plan?->name ?? 'Tanpa paket' }}</p><p class="text-xs text-slate-500">{{ $business->latestSubscription?->ends_at?->format('d M Y') ?? 'Tanpa batas' }}</p></td><td class="px-5 py-4 text-xs text-slate-600">{{ $business->outlets_count }} outlet · {{ $business->users_count }} user<br>{{ $business->products_count }} produk · {{ $business->orders_count }} order</td><td class="px-5 py-4 text-xs text-slate-500">{{ $business->created_at->format('d M Y') }}</td><td class="px-5 py-4 text-xs text-slate-500">{{ $business->last_order_at ? \Carbon\Carbon::parse($business->last_order_at)->diffForHumans() : 'Belum transaksi' }}</td></tr>
    @empty<tr><td colspan="6" class="px-5 py-14 text-center text-slate-400">Toko tidak ditemukan.</td></tr>@endforelse
    </tbody></table></div>
    <div class="border-t border-slate-200 px-5 py-4">{{ $businesses->links() }}</div>
</section>
@endsection
