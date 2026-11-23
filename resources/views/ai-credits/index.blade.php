@extends('layouts.app')
@section('title', 'Kredit Yoka AI')
@section('page-title', 'Kredit Yoka AI')
@section('page-subtitle', 'Saldo untuk bertanya di Tanya Yoka, terpisah dari langganan.')

@section('content')
@php
    $labels = [
        'grant_monthly' => 'Jatah gratis bulanan', 'topup' => 'Top-up', 'usage' => 'Dipakai Tanya Yoka',
        'expire_free' => 'Jatah gratis berakhir', 'expire_paid' => 'Kredit top-up kedaluwarsa', 'adjust' => 'Penyesuaian',
    ];
@endphp

<div class="page pb-10">
    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-kpi label="Sisa kredit" :value="number_format($balance['total'], 0, ',', '.')" :tone="$balance['total'] < 1 ? 'danger' : ($balance['total'] <= 5 ? 'warn' : 'brand')"
               :sub="$balance['total'] < 1 ? 'Habis. Top-up untuk lanjut bertanya.' : 'Sekitar '.number_format($balance['total'], 0, ',', '.').' pertanyaan biasa'" />
        <x-kpi label="Jatah gratis bulan ini" :value="$balance['free'].' / '.$balance['allowance']" sub="Terisi lagi tanggal 1, sisa tidak menumpuk" />
        <x-kpi label="Kredit top-up" :value="number_format($balance['paid'], 0, ',', '.')"
               :sub="$balance['paid_expires_at'] ? 'Berlaku sampai '.\Illuminate\Support\Carbon::parse($balance['paid_expires_at'])->translatedFormat('d F Y') : 'Belum ada top-up'" />
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div>
            <div class="flex items-baseline justify-between gap-3">
                <h3 class="text-sm font-semibold text-[#0B1B3F]">Top-up kredit</h3>
                @if($isSandbox)<x-pill tone="amber">Midtrans sandbox</x-pill>@endif
            </div>
            <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
                @foreach($packs as $key => $pack)
                    @php $per = $pack['price'] / $pack['credits']; @endphp
                    <form method="POST" action="{{ route('ai-credits.checkout') }}" class="flex flex-col rounded-xl border bg-white p-4 {{ $key === 'ai-300' ? 'border-[#16A34A] ring-1 ring-[#16A34A]' : 'border-[#E5E7EB]' }}">
                        @csrf
                        <input type="hidden" name="pack" value="{{ $key }}">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-semibold text-[#0B1B3F]">{{ $pack['name'] }}</span>
                            @if($key === 'ai-300')<span class="text-xs font-semibold text-[#15803D]">Paling sering dipilih</span>@endif
                        </div>
                        <p class="mt-2 text-2xl font-bold tabular-nums text-[#0B1B3F]">{{ number_format($pack['credits'], 0, ',', '.') }} <span class="text-sm font-normal text-[#64748B]">kredit</span></p>
                        <p class="mt-1 text-sm text-[#64748B]">Rp{{ number_format($per, 0, ',', '.') }} per kredit</p>
                        <button type="submit" @disabled(! $midtransConfigured)
                                class="mt-4 h-10 w-full rounded-lg text-sm font-semibold disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-400 {{ $key === 'ai-300' ? 'bg-[#16A34A] text-white hover:bg-[#15803D]' : 'border border-[#E5E7EB] text-[#0B1B3F] hover:border-[#16A34A]' }}">
                            Bayar Rp{{ number_format($pack['price'], 0, ',', '.') }}
                        </button>
                    </form>
                @endforeach
            </div>
            @unless($midtransConfigured)
                <p class="mt-3 text-sm text-amber-700">Top-up belum bisa karena pembayaran Midtrans belum aktif.</p>
            @endunless

            @if($topups->isNotEmpty())
                <div class="mt-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                    @foreach($topups as $t)
                        <p>Top-up {{ $t->credits }} kredit (Rp{{ number_format($t->amount, 0, ',', '.') }}) menunggu pembayaran.
                            @if($t->payment_url)<a href="{{ $t->payment_url }}" class="font-semibold underline">Lanjutkan bayar</a>@endif</p>
                    @endforeach
                </div>
            @endif
        </div>

        <aside class="rounded-xl border border-[#E5E7EB] bg-white p-5 text-sm leading-6 text-[#64748B]">
            <h3 class="text-sm font-semibold text-[#0B1B3F]">Cara kredit dihitung</h3>
            <ul class="mt-2 space-y-2">
                <li>Pertanyaan biasa memakai <span class="font-semibold text-[#0B1B3F]">1 kredit</span>.</li>
                <li>Pertanyaan berat, seperti lampiran file atau audit bisnis lengkap, bisa 2 sampai 10 kredit.</li>
                <li>Sapaan singkat tidak memotong kredit.</li>
                <li>Jatah gratis dipakai lebih dulu, baru kredit top-up.</li>
                <li>Kredit top-up berlaku 12 bulan sejak top-up terakhir.</li>
            </ul>
        </aside>
    </div>

    <x-panel title="Riwayat kredit" class="mt-8" flush>
        @if($entries->isEmpty())
            <x-empty title="Belum ada riwayat" text="Pemakaian dan top-up kredit akan muncul di sini." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[560px] text-sm">
                    <thead class="text-left text-xs text-[#64748B]">
                        <tr class="border-b border-[#E5E7EB]">
                            <th class="px-5 py-3 font-medium">Waktu</th>
                            <th class="px-5 py-3 font-medium">Keterangan</th>
                            <th class="px-5 py-3 text-right font-medium">Perubahan</th>
                            <th class="px-5 py-3 text-right font-medium">Sisa</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#F1F5F9]">
                        @foreach($entries as $e)
                            @php $c = $e->change(); @endphp
                            <tr>
                                <td class="whitespace-nowrap px-5 py-3 text-[#64748B]">{{ $e->created_at->translatedFormat('d M Y, H:i') }}</td>
                                <td class="px-5 py-3 text-[#0B1B3F]">{{ $labels[$e->kind] ?? $e->kind }}</td>
                                <td class="px-5 py-3 text-right font-semibold tabular-nums {{ $c >= 0 ? 'text-[#15803D]' : 'text-[#0B1B3F]' }}">{{ $c > 0 ? '+' : '' }}{{ $c }}</td>
                                <td class="px-5 py-3 text-right tabular-nums text-[#64748B]">{{ $e->free_after + $e->paid_after }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-panel>
</div>
@endsection
