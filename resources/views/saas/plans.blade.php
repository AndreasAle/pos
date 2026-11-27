@extends('layouts.app')
@section('title', 'Langganan')
@section('page-title', 'Langganan')
@section('page-subtitle', 'Paket Yoka POS untuk '.auth()->user()->business?->name)

@section('content')
@php
    $planData = $plans->map(fn ($plan) => [
        'id' => $plan->id,
        'name' => $plan->name,
        'price' => (int) $plan->price,
        'periods' => collect($plan->periods())->map(fn ($amount, $months) => [
            'months' => $months,
            'amount' => $amount,
            'perMonth' => (int) round($amount / $months),
            'save' => $months > 1 ? max(0, (int) round((1 - $amount / ($plan->price * $months)) * 100)) : 0,
        ])->values(),
        'outlets' => $plan->max_outlets >= 999 ? 'Outlet tanpa batas' : $plan->max_outlets.' outlet',
        'users' => $plan->max_users >= 999 ? 'Pengguna tanpa batas' : $plan->max_users.' pengguna',
    ])->values();
    $currentPlanId = $current?->subscription_plan_id;
    $initial = $planData->firstWhere('id', $currentPlanId) ?? $planData->first();
    $daysLeft = $current?->ends_at ? (int) max(0, today()->diffInDays($current->ends_at, false)) : null;
    $statusPill = match ($current?->status) {
        'trial' => ['amber', 'Masa coba'],
        'active' => ['green', 'Aktif'],
        default => ['gray', 'Belum berlangganan'],
    };
@endphp

<div class="page pb-10" x-data="{
    plans: @js($planData),
    selected: {{ $initial['id'] ?? 'null' }},
    months: 1,
    base: '{{ $periodBase->toDateString() }}',
    get plan() { return this.plans.find(p => p.id === Number(this.selected)) || this.plans[0] },
    get period() { return this.plan?.periods.find(p => p.months === Number(this.months)) || this.plan?.periods[0] },
    pick(id) { this.selected = id; if (!this.plan.periods.some(p => p.months === Number(this.months))) this.months = 1 },
    rupiah(v) { return 'Rp' + new Intl.NumberFormat('id-ID').format(v || 0) },
    get until() {
        const m = Number(this.months), d = new Date(this.base + 'T00:00:00');
        if (m === 1) { d.setDate(d.getDate() + 30) } else {
            const day = d.getDate(); d.setDate(1); d.setMonth(d.getMonth() + m);
            d.setDate(Math.min(day, new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate()));
        }
        return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
    }
}">

    {{-- Status langganan sekarang --}}
    <section class="mt-3 grid grid-cols-2 overflow-hidden rounded-xl border border-[#E5E7EB] bg-white md:grid-cols-4">
        <div class="border-b border-r border-[#E5E7EB] px-5 py-4 md:border-b-0">
            <p class="text-xs text-[#64748B]">Paket aktif</p>
            <div class="mt-1 flex items-center gap-2">
                <span class="text-base font-semibold text-[#0B1B3F]">{{ $current?->plan?->name ?? 'Belum ada' }}</span>
                <x-pill :tone="$statusPill[0]">{{ $statusPill[1] }}</x-pill>
            </div>
        </div>
        <div class="border-b border-[#E5E7EB] px-5 py-4 md:border-b-0 md:border-r">
            <p class="text-xs text-[#64748B]">Berlaku sampai</p>
            <p class="mt-1 text-base font-semibold text-[#0B1B3F]">{{ $current?->ends_at?->translatedFormat('d F Y') ?? '—' }}</p>
        </div>
        <div class="border-r border-[#E5E7EB] px-5 py-4">
            <p class="text-xs text-[#64748B]">Sisa masa aktif</p>
            <p class="mt-1 text-base font-semibold {{ $daysLeft !== null && $daysLeft <= 3 ? 'text-red-600' : 'text-[#0B1B3F]' }}">{{ $daysLeft !== null ? $daysLeft.' hari' : '—' }}</p>
        </div>
        <div class="px-5 py-4">
            <p class="text-xs text-[#64748B]">Outlet terpakai</p>
            <p class="mt-1 text-base font-semibold text-[#0B1B3F]">{{ $outletCount }}{{ $current?->plan && $current->plan->max_outlets < 999 ? ' / '.$current->plan->max_outlets : '' }}</p>
        </div>
    </section>
    @if($isSandbox)
        <p class="mt-2 text-xs text-amber-700">Mode uji Midtrans (sandbox): pembayaran di halaman ini bukan transaksi sungguhan.</p>
    @endif

    @if($plans->isEmpty())
        <x-panel class="mt-6"><x-empty title="Belum ada paket yang bisa dibeli" text="Hubungi tim Yoka untuk mengaktifkan langganan." /></x-panel>
    @else
    <form method="POST" action="{{ route('saas.checkout') }}" class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        @csrf
        <input type="hidden" name="plan_id" :value="selected">
        <input type="hidden" name="months" :value="period?.months || 1">

        <div class="space-y-6">
            {{-- Paket --}}
            <div>
                <h3 class="text-sm font-semibold text-[#0B1B3F]">Pilih paket</h3>
                <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
                    <template x-for="p in plans" :key="p.id">
                        <button type="button" @click="pick(p.id)"
                                class="rounded-xl border bg-white p-4 text-left transition-colors"
                                :class="p.id === Number(selected) ? 'border-[#0B1B3F] ring-1 ring-[#0B1B3F]' : 'border-[#E5E7EB] hover:border-slate-300'">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-semibold text-[#0B1B3F]" x-text="p.name"></span>
                                @if($currentPlanId)
                                    <span class="text-xs text-[#64748B]" x-show="p.id === {{ (int) $currentPlanId }}">Paket saat ini</span>
                                @endif
                            </div>
                            <p class="mt-2 text-xl font-bold tabular-nums text-[#0B1B3F]"><span x-text="rupiah(p.price)"></span><span class="text-sm font-normal text-[#64748B]"> / bulan</span></p>
                            <p class="mt-1 text-sm text-[#64748B]"><span x-text="p.outlets"></span>, <span x-text="p.users"></span></p>
                        </button>
                    </template>
                </div>
            </div>

            {{-- Lama berlangganan --}}
            <div x-show="plan?.periods.length > 1">
                <h3 class="text-sm font-semibold text-[#0B1B3F]">Periode tagihan</h3>
                <div class="mt-3 overflow-hidden rounded-xl border border-[#E5E7EB] bg-white">
                    <template x-for="(p, i) in plan.periods" :key="p.months">
                        <label class="flex cursor-pointer items-center gap-4 px-4 py-3" :class="{ 'border-t border-[#E5E7EB]': i > 0, 'bg-[#F8FAFC]': p.months === Number(months) }">
                            <input type="radio" class="h-4 w-4 accent-[#0B1B3F]" :value="p.months" x-model.number="months">
                            <span class="w-20 text-sm font-medium text-[#0B1B3F]" x-text="p.months + ' bulan'"></span>
                            <span class="flex-1 text-sm text-[#64748B]" x-text="p.months > 1 ? rupiah(p.perMonth) + ' / bulan' : 'Tagihan bulanan'"></span>
                            <span class="text-xs text-[#15803D]" x-show="p.save > 0" x-text="'hemat ' + p.save + '%'"></span>
                            <span class="w-28 text-right text-sm font-semibold tabular-nums text-[#0B1B3F]" x-text="rupiah(p.amount)"></span>
                        </label>
                    </template>
                </div>
            </div>
        </div>

        {{-- Ringkasan --}}
        <aside class="xl:sticky xl:top-24 xl:self-start">
            <div class="rounded-xl border border-[#E5E7EB] bg-white">
                <div class="border-b border-[#E5E7EB] px-5 py-3">
                    <h3 class="text-sm font-semibold text-[#0B1B3F]">Ringkasan tagihan</h3>
                </div>
                <dl class="space-y-2 px-5 py-4 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-[#64748B]">Paket</dt><dd class="font-medium text-[#0B1B3F]" x-text="plan?.name"></dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-[#64748B]">Periode</dt><dd class="font-medium text-[#0B1B3F]" x-text="period?.months === 1 ? '1 bulan (30 hari)' : period?.months + ' bulan'"></dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-[#64748B]">Aktif sampai</dt><dd class="font-medium text-[#0B1B3F]" x-text="until"></dd></div>
                </dl>
                <div class="flex items-baseline justify-between border-t border-[#E5E7EB] px-5 py-4">
                    <span class="text-sm font-medium text-[#0B1B3F]">Total</span>
                    <span class="text-xl font-bold tabular-nums text-[#0B1B3F]" x-text="rupiah(period?.amount)"></span>
                </div>
                <div class="px-5 pb-5">
                    <button type="submit" @disabled(! $midtransConfigured)
                            class="h-10 w-full rounded-lg bg-[#16A34A] text-sm font-semibold text-white hover:bg-[#15803D] disabled:cursor-not-allowed disabled:bg-slate-300">
                        Lanjut ke pembayaran
                    </button>
                    @if($midtransMismatch)
                        <p class="mt-3 text-xs leading-5 text-red-600">Checkout dimatikan: Midtrans diset ke production tapi Server Key masih key sandbox. Ganti key di .env server.</p>
                    @elseif(! $midtransConfigured)
                        <p class="mt-3 text-xs leading-5 text-amber-700">Checkout belum aktif karena kunci Midtrans belum dipasang di server.</p>
                    @else
                        <p class="mt-3 text-xs leading-5 text-[#64748B]">Pembayaran diproses oleh Midtrans. Paket aktif setelah pembayaran terkonfirmasi; invoice tersedia di riwayat transaksi.</p>
                    @endif
                </div>
            </div>
        </aside>
    </form>
    @endif

    {{-- Riwayat --}}
    <x-panel title="Riwayat transaksi" class="mt-8" flush>
        @if($subs->isEmpty())
            <x-empty title="Belum ada transaksi" text="Tagihan dan pembayaran langganan akan tercatat di sini." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-sm">
                    <thead class="bg-[#F8FAFC] text-left text-xs text-[#64748B]">
                        <tr class="border-b border-[#E5E7EB]">
                            <th class="px-5 py-2.5 font-medium">No. invoice</th>
                            <th class="px-5 py-2.5 font-medium">Tanggal</th>
                            <th class="px-5 py-2.5 font-medium">Paket</th>
                            <th class="px-5 py-2.5 text-right font-medium">Jumlah</th>
                            <th class="px-5 py-2.5 font-medium">Status</th>
                            <th class="px-5 py-2.5 text-right font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#F1F5F9]">
                        @foreach($subs as $sub)
                            @php
                                [$tone, $label] = ['trial' => ['amber', 'Masa coba'], 'active' => ['green', 'Lunas'], 'pending' => ['amber', 'Belum dibayar'], 'expired' => ['gray', 'Lunas, berakhir'], 'cancelled' => ['red', 'Gagal / batal']][$sub->status] ?? ['gray', ucfirst($sub->status)];
                                $amount = $sub->amount ?? $sub->plan?->price;
                            @endphp
                            <tr class="hover:bg-[#F8FAFC]">
                                <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-[#0B1B3F]">{{ \App\Http\Controllers\SubscriptionController::invoiceNumber($sub) }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-[#64748B]">{{ $sub->created_at->translatedFormat('d M Y, H:i') }}</td>
                                <td class="px-5 py-3 text-[#0B1B3F]">{{ $sub->plan?->name ?? 'Paket Yoka' }} <span class="text-[#64748B]">&middot; {{ $sub->months ? $sub->months.' bln' : '—' }}</span></td>
                                <td class="whitespace-nowrap px-5 py-3 text-right tabular-nums text-[#0B1B3F]">{{ $amount > 0 ? 'Rp'.number_format($amount, 0, ',', '.') : 'Gratis' }}</td>
                                <td class="px-5 py-3"><x-pill :tone="$tone">{{ $label }}</x-pill></td>
                                <td class="whitespace-nowrap px-5 py-3 text-right">
                                    <a href="{{ route('saas.invoice', $sub) }}" target="_blank" class="inline-flex h-8 items-center rounded-md border border-[#E5E7EB] px-3 text-xs font-medium text-[#0B1B3F] hover:bg-[#F1F5F9]">Invoice</a>
                                    @if($sub->status === 'pending' && $sub->payment_url)
                                        <a href="{{ $sub->payment_url }}" class="ml-1.5 inline-flex h-8 items-center rounded-md bg-[#16A34A] px-3 text-xs font-medium text-white hover:bg-[#15803D]">Bayar</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-panel>
</div>
@endsection
