@extends('layouts.app')
@section('title', 'Kredit Yoka AI')
@section('page-title', 'Kredit Yoka AI')
@section('focus', '1')

@section('content')
@php
    $labels = [
        'grant_monthly' => 'Jatah gratis bulanan', 'topup' => 'Pembelian kredit', 'usage' => 'Dipakai Tanya Yoka',
        'expire_free' => 'Jatah gratis berakhir', 'expire_paid' => 'Kredit top-up kedaluwarsa', 'adjust' => 'Penyesuaian',
    ];
@endphp

<div class="flex h-[100dvh] flex-col overflow-hidden bg-white" x-data="yokaShell()">
    <header class="relative z-30 flex h-14 shrink-0 items-center justify-between gap-3 border-b border-[#E5E7EB] bg-white px-3 sm:px-5">
        <a href="{{ route('intelligence.index') }}" aria-label="Kembali ke Tanya Yoka" title="Kembali ke Tanya Yoka" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#E5E7EB] bg-white text-[#0B1B3F] transition hover:border-[#16A34A] hover:bg-[#F4FBF7]">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <span class="text-[16px] font-extrabold text-[#0B1B3F]">Kredit Yoka AI</span>
        <button type="button" @click="historyOpen = true" class="flex h-10 w-10 items-center justify-center rounded-xl border border-[#E5E7EB] bg-white text-slate-600 transition hover:bg-slate-50 lg:invisible" aria-label="Menu"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg></button>
    </header>
    <div class="relative flex min-h-0 flex-1">
    @include('intelligence._sidebar')
    <div x-show="historyOpen" x-cloak @click="historyOpen=false" class="absolute inset-0 z-10 bg-black/30 lg:hidden"></div>
    <main class="min-w-0 flex-1 overflow-y-auto">
    @php
        $total = $balance['total'];
        $nextGrant = now()->addMonthNoOverflow()->startOfMonth()->translatedFormat('j F');
        $status = $total < 1 ? ['red', 'Habis'] : ($total <= 5 ? ['amber', 'Hampir habis'] : ['green', 'Aktif']);
    @endphp
    <div class="mx-auto max-w-4xl px-4 pb-12 pt-8 sm:px-8">
        @if(session('error'))<div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>@endif

        {{-- Saldo --}}
        <section class="rounded-xl border border-[#E5E7EB] bg-white">
            <div class="flex flex-wrap items-end justify-between gap-4 px-6 pb-5 pt-6">
                <div>
                    <p class="text-sm text-[#64748B]">Saldo kredit</p>
                    <div class="mt-1 flex items-center gap-3">
                        <p class="text-4xl font-bold tabular-nums text-[#0B1B3F]">{{ number_format($total, 0, ',', '.') }}</p>
                        <x-pill :tone="$status[0]">{{ $status[1] }}</x-pill>
                    </div>
                    <p class="mt-1 text-sm text-[#64748B]">1 kredit cukup untuk 1 pertanyaan biasa.</p>
                </div>
                @if($total > 0)
                    <a href="{{ route('intelligence.index') }}" class="inline-flex h-10 items-center rounded-lg border border-[#E5E7EB] px-4 text-sm font-semibold text-[#0B1B3F] hover:border-[#16A34A]">Mulai bertanya</a>
                @endif
            </div>
            <dl class="grid grid-cols-1 border-t border-[#E5E7EB] text-sm sm:grid-cols-2 sm:divide-x sm:divide-[#E5E7EB]">
                <div class="px-6 py-4">
                    <dt class="text-[#64748B]">Jatah gratis bulan ini</dt>
                    @if($balance['allowance'] > 0)
                        <dd class="mt-0.5 font-semibold text-[#0B1B3F]">{{ $balance['free'] }} dari {{ $balance['allowance'] }} kredit</dd>
                        <dd class="text-xs text-[#64748B]">Terisi ulang {{ $nextGrant }}. Sisa tidak dibawa ke bulan berikutnya.</dd>
                    @else
                        <dd class="mt-0.5 font-semibold text-[#0B1B3F]">Tidak ada</dd>
                        <dd class="text-xs text-[#64748B]">Jatah gratis didapat saat paket langganan aktif.</dd>
                    @endif
                </div>
                <div class="border-t border-[#E5E7EB] px-6 py-4 sm:border-t-0">
                    <dt class="text-[#64748B]">Kredit yang dibeli</dt>
                    <dd class="mt-0.5 font-semibold text-[#0B1B3F]">{{ number_format($balance['paid'], 0, ',', '.') }} kredit</dd>
                    <dd class="text-xs text-[#64748B]">{{ $balance['paid_expires_at'] ? 'Berlaku sampai '.\Illuminate\Support\Carbon::parse($balance['paid_expires_at'])->translatedFormat('j F Y').'.' : 'Belum ada pembelian.' }}</dd>
                </div>
            </dl>
        </section>

        @if($topups->isNotEmpty())
            <div class="mt-4 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                @foreach($topups as $t)
                    <p>Pembelian {{ number_format($t->credits, 0, ',', '.') }} kredit (Rp{{ number_format($t->amount, 0, ',', '.') }}) menunggu pembayaran.
                        @if($t->payment_url)<a href="{{ $t->payment_url }}" class="font-semibold underline">Lanjutkan bayar</a>@endif</p>
                @endforeach
            </div>
        @endif

        {{-- Beli kredit --}}
        <section class="mt-8">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-[#0B1B3F]">Beli kredit</h2>
                @if($isSandbox)<x-pill tone="amber">Mode uji Midtrans</x-pill>@endif
            </div>
            <div class="mt-3 overflow-hidden rounded-xl border border-[#E5E7EB] bg-white">
                @foreach($packs as $key => $pack)
                    <form method="POST" action="{{ route('ai-credits.checkout') }}" class="flex flex-wrap items-center gap-x-6 gap-y-2 px-5 py-4 {{ ! $loop->first ? 'border-t border-[#E5E7EB]' : '' }}">
                        @csrf
                        <input type="hidden" name="pack" value="{{ $key }}">
                        <div class="w-36">
                            <p class="font-semibold text-[#0B1B3F]">{{ number_format($pack['credits'], 0, ',', '.') }} kredit</p>
                            <p class="text-xs text-[#64748B]">Paket {{ $pack['name'] }}</p>
                        </div>
                        <p class="flex-1 text-sm text-[#64748B]">Rp{{ number_format($pack['price'] / $pack['credits'], 0, ',', '.') }} per kredit</p>
                        <p class="w-24 text-right font-semibold tabular-nums text-[#0B1B3F]">Rp{{ number_format($pack['price'], 0, ',', '.') }}</p>
                        <button type="submit" @disabled(! $midtransConfigured)
                                class="h-9 rounded-lg px-4 text-sm font-semibold disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-400 {{ $key === 'ai-300' ? 'bg-[#16A34A] text-white hover:bg-[#15803D]' : 'border border-[#E5E7EB] text-[#0B1B3F] hover:border-[#16A34A]' }}">Beli</button>
                    </form>
                @endforeach
            </div>
            @unless($midtransConfigured)
                <p class="mt-2 text-sm text-amber-700">Pembelian belum bisa karena pembayaran Midtrans belum aktif.</p>
            @endunless
            <p class="mt-3 text-xs leading-5 text-[#64748B]">Pertanyaan dengan lampiran atau audit bisnis lengkap memakai 2 sampai 10 kredit. Sapaan singkat tidak dihitung. Jatah gratis dipakai lebih dulu. Kredit yang dibeli berlaku 12 bulan sejak pembelian terakhir.</p>
        </section>

        {{-- Riwayat --}}
        <section class="mt-8">
            <h2 class="text-base font-semibold text-[#0B1B3F]">Riwayat</h2>
            <div class="mt-3 overflow-hidden rounded-xl border border-[#E5E7EB] bg-white">
                @if($entries->isEmpty())
                    <p class="px-5 py-8 text-center text-sm text-[#64748B]">Belum ada pemakaian atau pembelian kredit.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[520px] text-sm">
                            <thead class="bg-[#F8FAFC] text-left text-xs text-[#64748B]">
                                <tr>
                                    <th class="px-5 py-2.5 font-medium">Tanggal</th>
                                    <th class="px-5 py-2.5 font-medium">Keterangan</th>
                                    <th class="px-5 py-2.5 text-right font-medium">Kredit</th>
                                    <th class="px-5 py-2.5 text-right font-medium">Saldo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#F1F5F9]">
                                @foreach($entries as $e)
                                    @php $c = $e->change(); @endphp
                                    <tr>
                                        <td class="whitespace-nowrap px-5 py-3 text-[#64748B]">{{ $e->created_at->translatedFormat('j M Y, H:i') }}</td>
                                        <td class="px-5 py-3 text-[#0B1B3F]">{{ $labels[$e->kind] ?? $e->kind }}</td>
                                        <td class="px-5 py-3 text-right tabular-nums {{ $c > 0 ? 'font-semibold text-[#15803D]' : 'text-[#0B1B3F]' }}">{{ $c > 0 ? '+' : '' }}{{ $c }}</td>
                                        <td class="px-5 py-3 text-right tabular-nums text-[#64748B]">{{ $e->free_after + $e->paid_after }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>
    </div>
    </main>
    </div>
</div>
@endsection

@push('scripts')
<script>
function yokaShell() {
    return {
        historyOpen: false, search: '', searchOpen: false,
        async deleteConversation(id){
            if(!confirm('Hapus percakapan ini?'))return;
            const r=await fetch('{{ url('/intelligence/conversations') }}/'+id,{method:'DELETE',headers:{'Accept':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'}});
            if(r.ok)document.getElementById('conversation-'+id)?.remove();
        },
        async toggleAgent(id,event){const r=await fetch(`{{ url('/intelligence/agents') }}/${id}/toggle`,{method:'PATCH',headers:{'Accept':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'}});if(r.ok)window.location.reload()},
    }
}
</script>
@endpush
