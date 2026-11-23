@extends('layouts.platform')
@section('title', 'Kredit AI')

@section('content')
@php
    $rp = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
@endphp

<div class="mb-7 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
    <div>
        <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-700">Tanya Yoka</p>
        <h1 class="mt-1 text-3xl font-black">Kredit AI</h1>
        <p class="mt-1 text-sm text-slate-500">Saldo, pembelian, dan pemakaian kredit Yoka AI di semua toko, per {{ now()->translatedFormat('d F Y, H:i') }} WIB.</p>
    </div>
    @unless($facts['aktif_di_server'])
        <span class="border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-bold text-amber-900">FEATURE_AI_CREDITS masih mati: toko belum dipotong kredit</span>
    @endunless
</div>

<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach([
        ['Pembelian bulan ini', $rp($stats['topup_rupiah_bulan_ini']), $stats['topup_transaksi_bulan_ini'].' transaksi lunas', 'bg-emerald-500'],
        ['Kredit terpakai bulan ini', number_format($stats['kredit_terpakai_bulan_ini'], 0, ',', '.'), number_format($stats['kredit_terpakai_dari_berbayar'], 0, ',', '.').' dari kredit berbayar', 'bg-sky-500'],
        ['Kredit berbayar belum terpakai', number_format($stats['saldo_kredit_berbayar_belum_terpakai'], 0, ',', '.'), 'Masih jadi hak pelanggan', 'bg-violet-500'],
        ['Toko pakai AI bulan ini', $stats['toko_memakai_ai_bulan_ini'], $stats['toko_kredit_habis'].' toko kreditnya habis', 'bg-amber-500'],
    ] as [$label, $value, $note, $tone])
        <article class="border border-slate-200 bg-white p-5 shadow-sm"><div class="mb-5 h-1 w-10 {{ $tone }}"></div><p class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $label }}</p><p class="mt-2 text-3xl font-black">{{ $value }}</p><p class="mt-2 text-xs text-slate-500">{{ $note }}</p></article>
    @endforeach
</section>

<div class="mt-7 grid gap-6 xl:grid-cols-3">
    <section class="border border-slate-200 bg-white xl:col-span-2">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
            <div><h2 class="font-black">Saldo per toko</h2><p class="text-xs text-slate-500">Diurutkan dari pemakaian terbanyak bulan ini</p></div>
            <div class="flex gap-1 text-xs font-bold">
                @foreach([null => 'Semua', 'empty' => 'Habis', 'low' => 'Hampir habis'] as $key => $label)
                    <a href="{{ route('platform.ai-credits', array_filter(['filter' => $key])) }}" class="border px-3 py-1.5 {{ $filter === $key ? 'border-emerald-700 bg-emerald-700 text-white' : 'border-slate-200 text-slate-600 hover:bg-slate-50' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>
        <div class="overflow-x-auto"><table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-3">Toko</th><th class="px-5 py-3 text-right">Saldo</th><th class="px-5 py-3 text-right">Gratis / dibeli</th><th class="px-5 py-3 text-right">Terpakai bulan ini</th><th class="px-5 py-3 text-right">Total beli</th><th class="px-5 py-3">Terakhir pakai</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($rows as $r)
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3"><a href="{{ route('platform.businesses.show', $r['business_id']) }}" class="font-bold hover:text-emerald-700">{{ $r['toko'] ?? 'Toko #'.$r['business_id'] }}</a></td>
                    <td class="px-5 py-3 text-right font-black {{ $r['saldo'] === 0 ? 'text-red-600' : '' }}">{{ number_format($r['saldo'], 0, ',', '.') }}</td>
                    <td class="px-5 py-3 text-right text-slate-500">{{ $r['gratis'] }} / {{ number_format($r['berbayar'], 0, ',', '.') }}</td>
                    <td class="px-5 py-3 text-right">{{ number_format($r['terpakai_bulan_ini'], 0, ',', '.') }}</td>
                    <td class="px-5 py-3 text-right text-slate-600">{{ $r['total_beli_rupiah'] > 0 ? $rp($r['total_beli_rupiah']) : '—' }}</td>
                    <td class="px-5 py-3 text-xs text-slate-500">{{ $r['terakhir_pakai'] ? \Illuminate\Support\Carbon::parse($r['terakhir_pakai'])->diffForHumans() : 'Belum dipakai bulan ini' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-10 text-center text-slate-400">Belum ada toko di daftar ini.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>

    <div class="space-y-6">
        @if($canAdjust)
        <section class="border border-slate-200 bg-white">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-black">Sesuaikan kredit</h2><p class="text-xs text-slate-500">Bonus, kompensasi, koreksi, atau tes. Tidak ada uang yang tercatat.</p></div>
            <form method="POST" action="{{ route('platform.ai-credits.adjust') }}" class="space-y-3 p-5 text-sm">
                @csrf
                <label class="block"><span class="text-xs font-bold text-slate-600">Toko</span>
                    <select name="business_id" required class="mt-1 w-full border border-slate-300 px-3 py-2">
                        <option value="">Pilih toko</option>
                        @foreach($businesses as $b)<option value="{{ $b->id }}" @selected(old('business_id') == $b->id)>{{ $b->name }}</option>@endforeach
                    </select></label>
                <label class="block"><span class="text-xs font-bold text-slate-600">Jumlah kredit (minus untuk mengurangi)</span>
                    <input type="number" name="credits" value="{{ old('credits') }}" required min="-10000" max="10000" class="mt-1 w-full border border-slate-300 px-3 py-2" placeholder="mis. 50 atau -10"></label>
                <label class="block"><span class="text-xs font-bold text-slate-600">Alasan</span>
                    <input type="text" name="reason" value="{{ old('reason') }}" required minlength="5" maxlength="200" class="mt-1 w-full border border-slate-300 px-3 py-2" placeholder="mis. Kompensasi gangguan 24 Nov"></label>
                @if($errors->any())<p class="text-xs text-red-600">{{ $errors->first() }}</p>@endif
                <button class="w-full bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-800">Simpan penyesuaian</button>
                <p class="text-xs text-slate-500">Tambahan masuk ke kredit berbayar dan berlaku 12 bulan. Tercatat di riwayat toko dan Aktivitas Tim.</p>
            </form>
        </section>
        @endif

        <section class="border border-slate-200 bg-white">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-black">Aturan yang berlaku</h2><p class="text-xs text-slate-500">Sama dengan yang dipahami Adit dan tim AI</p></div>
            <dl class="space-y-3 p-5 text-xs leading-5 text-slate-600">
                <div><dt class="font-bold text-slate-900">Pemakaian</dt><dd>{{ $facts['pemakaian'] }}</dd></div>
                <div><dt class="font-bold text-slate-900">Jatah gratis per bulan</dt><dd>Masa coba {{ $facts['jatah_gratis_per_bulan']['masa_coba'] }}, YokaBasic {{ $facts['jatah_gratis_per_bulan']['YokaBasic'] }}, Business {{ $facts['jatah_gratis_per_bulan']['Business'] }}, Custom {{ $facts['jatah_gratis_per_bulan']['Custom'] }}. {{ $facts['jatah_gratis_per_bulan']['aturan'] }}</dd></div>
                <div><dt class="font-bold text-slate-900">Paket beli</dt><dd>{{ implode(' · ', $facts['paket_beli']) }}</dd></div>
                <div><dt class="font-bold text-slate-900">Masa berlaku</dt><dd>{{ $facts['masa_berlaku_kredit_dibeli'] }}</dd></div>
            </dl>
        </section>
    </div>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <section class="border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-black">Pembelian terbaru</h2><p class="text-xs text-slate-500">{{ $stats['topup_menunggu_bayar'] }} menunggu bayar dalam 24 jam terakhir</p></div>
        <div class="overflow-x-auto"><table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-3">Toko</th><th class="px-5 py-3 text-right">Kredit</th><th class="px-5 py-3 text-right">Nominal</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Waktu</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($topups as $t)
                <tr><td class="px-5 py-3 font-bold">{{ $t->business?->name }}</td><td class="px-5 py-3 text-right">{{ number_format($t->credits, 0, ',', '.') }}</td><td class="px-5 py-3 text-right">{{ $rp($t->amount) }}</td>
                    <td class="px-5 py-3"><span class="border px-2 py-1 text-xs font-bold {{ ['paid' => 'border-emerald-300 text-emerald-800', 'pending' => 'border-sky-300 text-sky-800', 'cancelled' => 'border-slate-200 text-slate-500'][$t->status] ?? '' }}">{{ ['paid' => 'Lunas', 'pending' => 'Menunggu', 'cancelled' => 'Batal'][$t->status] ?? $t->status }}</span></td>
                    <td class="px-5 py-3 text-xs text-slate-500">{{ $t->created_at->diffForHumans() }}</td></tr>
            @empty
                <tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">Belum ada pembelian kredit.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>

    <section class="border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-black">Penyesuaian terakhir</h2><p class="text-xs text-slate-500">Kredit yang ditambah atau dikurangi manual</p></div>
        <div class="divide-y divide-slate-100">
            @forelse($adjustments as $a)
                <div class="flex items-start justify-between gap-4 px-5 py-4">
                    <div><p class="text-sm font-semibold">{{ $a->business?->name }}: {{ $a->change() > 0 ? '+' : '' }}{{ $a->change() }} kredit</p><p class="mt-1 text-xs text-slate-500">{{ $a->meta['reason'] ?? '' }}</p></div>
                    <time class="whitespace-nowrap text-xs text-slate-400">{{ $a->created_at->diffForHumans() }}</time>
                </div>
            @empty
                <div class="px-5 py-10 text-center text-sm text-slate-400">Belum ada penyesuaian.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection
