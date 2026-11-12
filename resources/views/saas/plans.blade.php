@extends('layouts.app')
@section('title', 'Langganan & Pembayaran')
@section('page-title', 'Langganan & Pembayaran')

@section('content')
@php
    $planData = $plans->map(fn ($plan) => [
        'id' => $plan->id,
        'name' => $plan->name,
        'price' => (int) $plan->price,
        'outlets' => $plan->max_outlets >= 999 ? 'Tanpa batas' : $plan->max_outlets.' outlet',
        'users' => $plan->max_users >= 999 ? 'Tanpa batas' : $plan->max_users.' pengguna',
    ])->values();
    $selectedPlan = $planData->first();
@endphp

<div class="page pb-8" x-data="{
    plans: @js($planData),
    selected: {{ $selectedPlan['id'] ?? 'null' }},
    get plan() { return this.plans.find(plan => plan.id === Number(this.selected)) || this.plans[0] },
    rupiah(value) { return 'Rp ' + new Intl.NumberFormat('id-ID').format(value || 0) }
}">
    <div class="border-b border-slate-200 pb-5 pt-4">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-[#0B8F5B]">Billing Yoka POS</p>
                <h2 class="mt-1 text-2xl font-bold text-[#0B1B3F]">Kelola paket dan pembayaran</h2>
                <p class="mt-1 text-sm text-slate-500">Pilih paket, selesaikan pembayaran, lalu status bisnis diperbarui otomatis.</p>
            </div>
            <div class="border px-3 py-2 text-xs font-semibold {{ $isSandbox ? 'border-amber-300 bg-amber-50 text-amber-800' : 'border-emerald-300 bg-emerald-50 text-emerald-800' }}">
                {{ $isSandbox ? 'Mode pengujian Midtrans Sandbox' : 'Pembayaran Midtrans Production' }}
            </div>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(420px,0.9fr)]">
        <div class="space-y-7">
            <section>
                <h3 class="text-base font-bold text-[#0B1B3F]">Paket saat ini</h3>
                <div class="mt-3 border border-slate-200 bg-white">
                    <div class="grid grid-cols-1 divide-y divide-slate-200 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                        <div class="p-5">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Paket</p>
                            <p class="mt-1 text-lg font-bold text-[#0B1B3F]">{{ $current?->plan?->name ?? 'Belum aktif' }}</p>
                            @if($current)
                                <p class="mt-1 text-xs font-semibold {{ $current->status === 'trial' ? 'text-amber-700' : 'text-[#0B8F5B]' }}">{{ $current->status === 'trial' ? 'Masa coba' : 'Aktif' }}</p>
                            @endif
                        </div>
                        <div class="p-5">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Outlet aktif</p>
                            <p class="mt-1 text-lg font-bold text-[#0B1B3F]">{{ $outletCount }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $business->name }}</p>
                        </div>
                        <div class="p-5">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Berlaku sampai</p>
                            <p class="mt-1 text-lg font-bold text-[#0B1B3F]">{{ $current?->ends_at?->translatedFormat('d M Y') ?? 'Tanpa batas' }}</p>
                            <p class="mt-1 text-xs text-slate-500">Perpanjangan menambah 30 hari</p>
                        </div>
                    </div>
                </div>
            </section>

            <section>
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-base font-bold text-[#0B1B3F]">Riwayat pembayaran</h3>
                    <span class="text-xs text-slate-400">10 transaksi terakhir</span>
                </div>
                <div class="mt-3 overflow-x-auto border border-slate-200 bg-white">
                    <table class="w-full min-w-[680px] text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Tanggal</th>
                                <th class="px-4 py-3">Paket</th>
                                <th class="px-4 py-3">Nominal</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($subs as $sub)
                                @php
                                    $statusLabel = ['trial' => 'Trial', 'active' => 'Lunas', 'pending' => 'Menunggu', 'expired' => 'Berakhir', 'cancelled' => 'Dibatalkan'][$sub->status] ?? ucfirst($sub->status);
                                    $statusClass = ['trial' => 'text-amber-700 bg-amber-50', 'active' => 'text-emerald-700 bg-emerald-50', 'pending' => 'text-blue-700 bg-blue-50', 'expired' => 'text-slate-600 bg-slate-100', 'cancelled' => 'text-red-700 bg-red-50'][$sub->status] ?? 'text-slate-600 bg-slate-100';
                                @endphp
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $sub->created_at->translatedFormat('d M Y') }}</td>
                                    <td class="px-4 py-3 font-semibold text-[#0B1B3F]">{{ $sub->plan?->name ?? 'Paket Yoka' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-slate-600">
                                        {{ ($sub->amount ?? $sub->plan?->price) > 0 ? 'Rp '.number_format($sub->amount ?? $sub->plan?->price, 0, ',', '.') : 'Gratis' }}
                                    </td>
                                    <td class="px-4 py-3"><span class="inline-flex px-2 py-1 text-xs font-semibold {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                    <td class="px-4 py-3 text-right">
                                        @if($sub->status === 'pending' && $sub->payment_url)
                                            <a href="{{ $sub->payment_url }}" class="font-semibold text-[#0B8F5B] hover:underline">Lanjutkan pembayaran</a>
                                        @else
                                            <span class="text-slate-300">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">Belum ada riwayat pembayaran.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <aside>
            <h3 class="text-base font-bold text-[#0B1B3F]">Pembayaran</h3>
            <form method="POST" action="{{ route('saas.checkout') }}" class="mt-3 border border-slate-200 bg-white">
                @csrf
                <div class="border-b border-slate-200 p-5">
                    <label for="billing-plan" class="block text-sm font-bold text-[#0B1B3F]">Pilih paket bulanan</label>
                    @if($plans->isNotEmpty())
                        <select id="billing-plan" name="plan_id" x-model.number="selected" class="mt-3 h-12 w-full border border-slate-300 bg-white px-3 text-sm font-semibold text-[#0B1B3F] focus:border-[#0B8F5B] focus:outline-none focus:ring-2 focus:ring-emerald-100">
                            @foreach($plans as $plan)
                                <option value="{{ $plan->id }}">{{ $plan->name }} — Rp {{ number_format($plan->price, 0, ',', '.') }}/bulan</option>
                            @endforeach
                        </select>
                    @else
                        <p class="mt-3 text-sm text-slate-500">Belum ada paket berbayar yang tersedia.</p>
                    @endif
                </div>

                <template x-if="plan">
                    <div>
                        <div class="border-b border-slate-200 px-5 py-4 text-sm">
                            <div class="flex items-center justify-between gap-4 py-1.5"><span class="text-slate-500">Siklus tagihan</span><span class="font-semibold text-[#0B1B3F]">30 hari</span></div>
                            <div class="flex items-center justify-between gap-4 py-1.5"><span class="text-slate-500">Kapasitas outlet</span><span class="font-semibold text-[#0B1B3F]" x-text="plan.outlets"></span></div>
                            <div class="flex items-center justify-between gap-4 py-1.5"><span class="text-slate-500">Kapasitas pengguna</span><span class="font-semibold text-[#0B1B3F]" x-text="plan.users"></span></div>
                        </div>
                        <div class="p-5">
                            <div class="flex items-end justify-between gap-4">
                                <div><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Total pembayaran</p><p class="mt-1 text-xs text-slate-500">Termasuk akses paket selama 30 hari</p></div>
                                <p class="text-2xl font-bold text-[#0B1B3F]" x-text="rupiah(plan.price)"></p>
                            </div>
                            <button type="submit" @disabled(!$midtransConfigured || $plans->isEmpty()) class="mt-5 h-12 w-full bg-[#0B8F5B] px-5 text-sm font-bold text-white hover:bg-[#08794D] disabled:cursor-not-allowed disabled:bg-slate-300">Bayar dengan Midtrans</button>
                            <p class="mt-3 text-center text-xs text-slate-400">Anda akan diarahkan ke halaman pembayaran resmi Midtrans.</p>
                        </div>
                    </div>
                </template>
            </form>

            @unless($midtransConfigured)
                <div class="border-x border-b border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-800"><p class="font-bold">Checkout belum aktif</p><p class="mt-1 text-xs leading-5">Pasang Server Key dan Client Key Midtrans di konfigurasi server untuk menjalankan pembayaran.</p></div>
            @endunless

            <div class="mt-4 flex items-start gap-3 border border-slate-200 bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-500">
                <svg class="mt-0.5 h-4 w-4 shrink-0 text-[#0B8F5B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-7a2 2 0 00-2-2h-1V7a5 5 0 00-10 0v3H6a2 2 0 00-2 2v7a2 2 0 002 2zm3-11V7a3 3 0 016 0v3H9z"/></svg>
                <p>Paket baru hanya aktif setelah Midtrans mengirim konfirmasi pembayaran yang valid. Kegagalan checkout tidak akan mengaktifkan paket.</p>
            </div>
        </aside>
    </div>
</div>
@endsection
