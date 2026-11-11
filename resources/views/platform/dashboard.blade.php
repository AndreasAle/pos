@extends('layouts.platform')
@section('title', 'Ringkasan Platform')

@section('content')
<div class="mb-7 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
    <div><p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-700">Operations overview</p><h1 class="mt-1 text-3xl font-black">Ringkasan Platform</h1><p class="mt-1 text-sm text-slate-500">Kondisi seluruh tenant Yoka per {{ now()->translatedFormat('d F Y, H:i') }} WIB.</p></div>
    <a href="{{ route('platform.businesses.index') }}" class="bg-emerald-700 px-4 py-3 text-center text-sm font-bold text-white hover:bg-emerald-800">Buka daftar toko</a>
</div>

<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach([
        ['Toko aktif', $stats['active_businesses'], $stats['new_this_week'].' toko baru minggu ini', 'bg-emerald-500'],
        ['Sedang trial', $stats['trial_businesses'], $stats['overdue_businesses'].' pembayaran tertunggak', 'bg-sky-500'],
        ['Berhenti', $stats['stopped_businesses'], $stats['disabled_businesses'].' dinonaktifkan tim', 'bg-red-500'],
        ['Transaksi platform', number_format($stats['orders'], 0, ',', '.'), 'Dari '.$stats['businesses'].' tenant terdaftar', 'bg-violet-500'],
    ] as [$label, $value, $note, $toneClass])
        <article class="border border-slate-200 bg-white p-5 shadow-sm"><div class="mb-5 h-1 w-10 {{ $toneClass }}"></div><p class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $label }}</p><p class="mt-2 text-3xl font-black">{{ $value }}</p><p class="mt-2 text-xs text-slate-500">{{ $note }}</p></article>
    @endforeach
</section>

@if($admin->canViewFinancials())
<section class="mt-4 grid gap-4 md:grid-cols-3">
    <article class="border border-slate-200 bg-slate-900 p-5 text-white"><p class="text-xs font-bold uppercase tracking-wider text-slate-300">Omzet seluruh toko</p><p class="mt-2 text-2xl font-black">Rp {{ number_format($stats['gross_revenue'], 0, ',', '.') }}</p></article>
    <article class="border border-emerald-800 bg-emerald-700 p-5 text-white"><p class="text-xs font-bold uppercase tracking-wider text-emerald-100">Pendapatan langganan bulan ini</p><p class="mt-2 text-2xl font-black">Rp {{ number_format($stats['subscription_revenue'], 0, ',', '.') }}</p></article>
    <a href="{{ route('platform.withdrawals.index') }}" class="border border-amber-300 bg-amber-50 p-5 text-amber-950 hover:bg-amber-100"><p class="text-xs font-bold uppercase tracking-wider">Antrean penarikan</p><p class="mt-2 text-2xl font-black">{{ $stats['pending_withdrawals'] }} permintaan</p></a>
</section>
@endif

<div class="mt-7 grid gap-6 xl:grid-cols-3">
    <section class="border border-slate-200 bg-white xl:col-span-2">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4"><div><h2 class="font-black">Toko terbaru</h2><p class="text-xs text-slate-500">Pendaftaran dan aktivitas terakhir</p></div><a href="{{ route('platform.businesses.index') }}" class="text-xs font-bold text-emerald-700">Lihat semua →</a></div>
        <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-3">Toko</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Paket</th><th class="px-5 py-3">Aktivitas</th></tr></thead><tbody class="divide-y divide-slate-100">
        @forelse($recentBusinesses as $business)
            @php $status = $business->platformStatus(); $statusLabel = ['active'=>'Aktif','trial'=>'Trial','overdue'=>'Tertunggak','disabled'=>'Dinonaktifkan','stopped'=>'Berhenti'][$status] ?? $status; @endphp
            <tr class="hover:bg-slate-50"><td class="px-5 py-4"><a href="{{ route('platform.businesses.show', $business) }}" class="font-bold hover:text-emerald-700">{{ $business->name }}</a><p class="text-xs text-slate-500">{{ $business->owner?->email ?? $business->email ?? 'Owner belum ada' }}</p></td><td class="px-5 py-4"><span class="border border-slate-200 px-2 py-1 text-xs font-bold">{{ $statusLabel }}</span></td><td class="px-5 py-4 text-slate-600">{{ $business->latestSubscription?->plan?->name ?? '—' }}</td><td class="px-5 py-4 text-xs text-slate-500">{{ $business->last_order_at ? \Carbon\Carbon::parse($business->last_order_at)->diffForHumans() : 'Belum transaksi' }}</td></tr>
        @empty<tr><td colspan="4" class="px-5 py-10 text-center text-slate-400">Belum ada toko.</td></tr>@endforelse
        </tbody></table></div>
    </section>

    <section class="border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-black">Trial segera berakhir</h2><p class="text-xs text-slate-500">7 hari ke depan</p></div>
        <div class="divide-y divide-slate-100">
            @forelse($expiringTrials as $trial)
                <a href="{{ route('platform.businesses.show', $trial->business) }}" class="block px-5 py-4 hover:bg-slate-50"><div class="flex justify-between gap-3"><div><p class="text-sm font-bold">{{ $trial->business->name }}</p><p class="mt-1 text-xs text-slate-500">{{ $trial->business->owner?->email ?? 'Owner belum ada' }}</p></div><span class="text-xs font-black text-amber-700">{{ $trial->ends_at->diffForHumans() }}</span></div></a>
            @empty<div class="px-5 py-10 text-center text-sm text-slate-400">Tidak ada trial yang segera habis.</div>@endforelse
        </div>
    </section>
</div>

<section class="mt-6 border border-slate-200 bg-white">
    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4"><div><h2 class="font-black">Aktivitas tim terbaru</h2><p class="text-xs text-slate-500">Aksi sensitif dan sesi bantuan</p></div><a href="{{ route('platform.activity') }}" class="text-xs font-bold text-emerald-700">Audit lengkap →</a></div>
    <div class="divide-y divide-slate-100">@forelse($recentActivity as $log)<div class="flex items-start justify-between gap-4 px-5 py-4"><div><p class="text-sm font-semibold">{{ $log->description }}</p><p class="mt-1 text-xs text-slate-500">{{ $log->admin?->name ?? 'Sistem' }} · {{ $log->business?->name ?? 'Platform' }}</p></div><time class="whitespace-nowrap text-xs text-slate-400">{{ $log->created_at->diffForHumans() }}</time></div>@empty<div class="px-5 py-10 text-center text-sm text-slate-400">Belum ada aktivitas.</div>@endforelse</div>
</section>
@endsection
