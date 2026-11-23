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
    <section class="mt-3 rounded-xl border border-[#E5E7EB] bg-white px-5 py-4">
        <div class="flex flex-wrap items-center gap-x-8 gap-y-3">
            <div class="flex items-center gap-3">
                <span class="text-lg font-bold text-[#0B1B3F]">{{ $current?->plan?->name ?? 'Belum ada paket' }}</span>
                <x-pill :tone="$statusPill[0]">{{ $statusPill[1] }}</x-pill>
            </div>
            <p class="text-sm text-[#64748B]">
                @if($current?->ends_at)
                    Berlaku sampai <span class="font-semibold text-[#0B1B3F]">{{ $current->ends_at->translatedFormat('d F Y') }}</span>
                    <span class="{{ $daysLeft <= 3 ? 'font-semibold text-red-600' : '' }}">({{ $daysLeft }} hari lagi)</span>
                @elseif($current)
                    Tanpa tanggal berakhir
                @else
                    Pilih paket di bawah untuk mulai berlangganan.
                @endif
            </p>
            <p class="text-sm text-[#64748B]">
                Outlet terpakai <span class="font-semibold text-[#0B1B3F]">{{ $outletCount }}{{ $current?->plan && $current->plan->max_outlets < 999 ? ' dari '.$current->plan->max_outlets : '' }}</span>
            </p>
            @if($isSandbox)
                <x-pill tone="amber" class="ml-auto">Midtrans sandbox, bukan pembayaran sungguhan</x-pill>
            @endif
        </div>
    </section>

    @if($plans->isEmpty())
        <x-panel class="mt-6"><x-empty title="Belum ada paket yang bisa dibeli" text="Hubungi tim Yoka untuk mengaktifkan langganan." /></x-panel>
    @else
    <form method="POST" action="{{ route('saas.checkout') }}" class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        @csrf
        <input type="hidden" name="plan_id" :value="selected">
        <input type="hidden" name="months" :value="period?.months || 1">

        <div class="space-y-6">
            {{-- Paket --}}
            <div>
                <h3 class="text-sm font-semibold text-[#0B1B3F]">Paket</h3>
                <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
                    <template x-for="p in plans" :key="p.id">
                        <button type="button" @click="pick(p.id)"
                                class="rounded-xl border bg-white p-4 text-left transition-colors"
                                :class="p.id === Number(selected) ? 'border-[#16A34A] ring-1 ring-[#16A34A]' : 'border-[#E5E7EB] hover:border-slate-300'">
                            <div class="flex items-start justify-between gap-2">
                                <span class="font-semibold text-[#0B1B3F]" x-text="p.name"></span>
                                <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border"
                                      :class="p.id === Number(selected) ? 'border-[#16A34A] bg-[#16A34A]' : 'border-slate-300'">
                                    <span class="h-1.5 w-1.5 rounded-full bg-white" x-show="p.id === Number(selected)"></span>
                                </span>
                            </div>
                            <p class="mt-2 text-xl font-bold tabular-nums text-[#0B1B3F]"><span x-text="rupiah(p.price)"></span><span class="text-sm font-normal text-[#64748B]">/bulan</span></p>
                            <p class="mt-2 text-sm text-[#64748B]"><span x-text="p.outlets"></span> &middot; <span x-text="p.users"></span></p>
                            @if($currentPlanId)
                                <p class="mt-2 text-xs font-semibold text-[#15803D]" x-show="p.id === {{ (int) $currentPlanId }}">Paket kamu sekarang</p>
                            @endif
                        </button>
                    </template>
                </div>
            </div>

            {{-- Lama berlangganan --}}
            <div x-show="plan?.periods.length > 1">
                <h3 class="text-sm font-semibold text-[#0B1B3F]">Lama berlangganan</h3>
                <p class="mt-0.5 text-sm text-[#64748B]">Bayar di muka lebih hemat. Harga bulanan tidak pernah didiskon.</p>
                <div class="mt-3 overflow-hidden rounded-xl border border-[#E5E7EB] bg-white">
                    <template x-for="(p, i) in plan.periods" :key="p.months">
                        <label class="flex cursor-pointer items-center gap-4 px-4 py-3.5" :class="{ 'border-t border-[#E5E7EB]': i > 0, 'bg-[#F6FEF9]': p.months === Number(months) }">
                            <input type="radio" class="h-4 w-4 accent-[#16A34A]" :value="p.months" x-model.number="months">
                            <span class="w-20 font-semibold text-[#0B1B3F]" x-text="p.months + ' bulan'"></span>
                            <span class="flex-1 text-sm text-[#64748B]" x-text="p.months > 1 ? rupiah(p.perMonth) + '/bulan' : 'Ditagih tiap bulan'"></span>
                            <span class="text-xs font-semibold text-[#15803D]" x-show="p.save > 0" x-text="'Hemat ' + p.save + '%'"></span>
                            <span class="w-28 text-right font-semibold tabular-nums text-[#0B1B3F]" x-text="rupiah(p.amount)"></span>
                        </label>
                    </template>
                </div>
            </div>
        </div>

        {{-- Ringkasan --}}
        <aside class="xl:sticky xl:top-24 xl:self-start">
            <div class="rounded-xl border border-[#E5E7EB] bg-white">
                <div class="px-5 pt-5">
                    <h3 class="text-sm font-semibold text-[#0B1B3F]">Ringkasan</h3>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-[#64748B]">Paket</dt><dd class="font-medium text-[#0B1B3F]" x-text="plan?.name"></dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-[#64748B]">Durasi</dt><dd class="font-medium text-[#0B1B3F]" x-text="period?.months === 1 ? '1 bulan (30 hari)' : period?.months + ' bulan'"></dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-[#64748B]">Aktif sampai</dt><dd class="font-medium text-[#0B1B3F]" x-text="until"></dd></div>
                    </dl>
                </div>
                <div class="mt-4 flex items-baseline justify-between border-t border-dashed border-[#E5E7EB] px-5 py-4">
                    <span class="text-sm text-[#64748B]">Total</span>
                    <span class="text-2xl font-bold tabular-nums text-[#0B1B3F]" x-text="rupiah(period?.amount)"></span>
                </div>
                <div class="px-5 pb-5">
                    <button type="submit" @disabled(! $midtransConfigured)
                            class="h-11 w-full rounded-lg bg-[#16A34A] text-sm font-semibold text-white hover:bg-[#15803D] disabled:cursor-not-allowed disabled:bg-slate-300">
                        <span x-text="'Bayar ' + rupiah(period?.amount)"></span>
                    </button>
                    @if($midtransMismatch)
                        <p class="mt-3 text-xs leading-5 text-red-600">Checkout dimatikan: Midtrans diset ke production tapi Server Key masih key sandbox. Ganti key di .env server.</p>
                    @elseif(! $midtransConfigured)
                        <p class="mt-3 text-xs leading-5 text-amber-700">Checkout belum aktif karena kunci Midtrans belum dipasang di server.</p>
                    @else
                        <p class="mt-3 text-xs leading-5 text-[#64748B]">Kamu akan diarahkan ke halaman Midtrans. Paket aktif otomatis begitu pembayaran dikonfirmasi.</p>
                    @endif
                </div>
            </div>
            <p class="mt-3 px-1 text-xs leading-5 text-[#64748B]">Butuh paket khusus atau bantuan pembayaran? Hubungi tim Yoka lewat WhatsApp.</p>
        </aside>
    </form>
    @endif

    {{-- Riwayat --}}
    <x-panel title="Riwayat pembayaran" class="mt-8" flush>
        @if($subs->isEmpty())
            <x-empty title="Belum ada pembayaran" text="Pembayaran langganan kamu akan muncul di sini." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-sm">
                    <thead class="text-left text-xs text-[#64748B]">
                        <tr class="border-b border-[#E5E7EB]">
                            <th class="px-5 py-3 font-medium">Tanggal</th>
                            <th class="px-5 py-3 font-medium">Paket</th>
                            <th class="px-5 py-3 font-medium">Durasi</th>
                            <th class="px-5 py-3 text-right font-medium">Nominal</th>
                            <th class="px-5 py-3 font-medium">Status</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#F1F5F9]">
                        @foreach($subs as $sub)
                            @php
                                [$tone, $label] = ['trial' => ['amber', 'Masa coba'], 'active' => ['green', 'Lunas'], 'pending' => ['blue', 'Menunggu bayar'], 'expired' => ['gray', 'Berakhir'], 'cancelled' => ['red', 'Dibatalkan']][$sub->status] ?? ['gray', ucfirst($sub->status)];
                                $amount = $sub->amount ?? $sub->plan?->price;
                            @endphp
                            <tr>
                                <td class="whitespace-nowrap px-5 py-3 text-[#64748B]">{{ $sub->created_at->translatedFormat('d M Y') }}</td>
                                <td class="px-5 py-3 font-medium text-[#0B1B3F]">{{ $sub->plan?->name ?? 'Paket Yoka' }}</td>
                                <td class="px-5 py-3 text-[#64748B]">{{ $sub->months ? $sub->months.' bulan' : '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right tabular-nums text-[#0B1B3F]">{{ $amount > 0 ? 'Rp'.number_format($amount, 0, ',', '.') : 'Gratis' }}</td>
                                <td class="px-5 py-3"><x-pill :tone="$tone">{{ $label }}</x-pill></td>
                                <td class="px-5 py-3 text-right">
                                    @if($sub->status === 'pending' && $sub->payment_url)
                                        <a href="{{ $sub->payment_url }}" class="font-semibold text-[#16A34A] hover:underline">Bayar sekarang</a>
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
