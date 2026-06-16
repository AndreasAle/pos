@extends('layouts.guest')
@section('title', 'Masa Trial Berakhir')

@section('content')
<div class="text-center">
    <div class="w-16 h-16 mx-auto rounded-2xl bg-amber-100 flex items-center justify-center text-3xl mb-4">⏰</div>
    <h3 class="text-xl font-bold text-gray-900 mb-1">
        @if($sub && $sub->status === 'trial')
            Masa Trial Anda Telah Berakhir
        @else
            Langganan Anda Telah Berakhir
        @endif
    </h3>
    <p class="text-sm text-gray-500 mb-6">
        Bisnis <span class="font-semibold text-gray-700">{{ $business->name }}</span> sudah tidak memiliki akses aktif ke FNB POS.
    </p>
</div>

<div class="rounded-xl bg-emerald-50 border border-emerald-100 px-4 py-3 mb-6 text-sm text-emerald-800 flex gap-3">
    <span class="text-lg shrink-0">🔒</span>
    <p>Tenang, <span class="font-semibold">seluruh data transaksi, produk, stok, dan laporan Anda tetap kami simpan dengan aman</span>. Begitu Anda upgrade ke paket berbayar, semua data langsung bisa diakses kembali seperti semula.</p>
</div>

@if($sub && $sub->ends_at)
<p class="text-center text-xs text-gray-400 mb-6">
    {{ $sub->status === 'trial' ? 'Trial' : 'Langganan' }} berakhir pada {{ $sub->ends_at->format('d M Y') }} ({{ $sub->ends_at->diffForHumans() }})
</p>
@endif

@if(auth()->user()->isOwner())
    <p class="text-sm font-semibold text-gray-700 mb-3 text-center">Pilih paket untuk melanjutkan dan mengaktifkan kembali bisnis Anda:</p>

    <div class="space-y-3 mb-6">
        @foreach($plans as $plan)
            <div class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 px-4 py-3 hover:border-emerald-400 transition-colors">
                <div>
                    <p class="font-semibold text-sm text-gray-900">{{ $plan->name }}</p>
                    <p class="text-xs text-gray-500">
                        Rp {{ number_format($plan->price, 0, ',', '.') }}/bulan ·
                        {{ $plan->max_outlets >= 999 ? 'Outlet tanpa batas' : $plan->max_outlets . ' outlet' }} ·
                        {{ $plan->max_users >= 999 ? 'User tanpa batas' : $plan->max_users . ' user' }}
                    </p>
                </div>
                <form method="POST" action="{{ route('saas.subscribe', $plan) }}">
                    @csrf
                    <button type="submit" class="shrink-0 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold transition-colors">
                        Pilih Paket
                    </button>
                </form>
            </div>
        @endforeach
    </div>

    <div class="flex items-center justify-center gap-4 text-sm">
        <a href="{{ route('saas.plans') }}" class="text-emerald-600 font-medium hover:underline">Lihat detail semua paket</a>
        <span class="text-gray-300">•</span>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-gray-500 hover:text-gray-700 hover:underline">Keluar</button>
        </form>
    </div>
@else
    <div class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-3 mb-6 text-sm text-amber-800 text-center">
        Hubungi <span class="font-semibold">pemilik bisnis Anda</span> untuk memperpanjang atau mengupgrade paket langganan agar akses dapat diaktifkan kembali.
    </div>
    <div class="text-center">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm text-gray-500 hover:text-gray-700 hover:underline">Keluar</button>
        </form>
    </div>
@endif
@endsection
