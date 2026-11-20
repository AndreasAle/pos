@extends('layouts.platform')
@section('title', 'Keuangan Yoka')

@section('content')
@php
    $rp = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
    $input = 'w-full border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-emerald-600 focus:outline-none';
    $cashAccounts = $accounts->whereIn('subtype', ['cash', 'bank', 'clearing']);
    $metric = fn ($m, $fmt) => $m['status'] === 'ok' ? $fmt($m['value']) : 'Belum bisa dihitung';
@endphp
<div class="mb-7 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-3xl font-black">Keuangan Yoka</h1>
        <p class="mt-1 text-sm text-slate-500">Pembukuan usaha Yoka sendiri (double-entry). Terpisah dari data toko klien. Nanda dan Luna hanya membaca halaman ini.</p>
    </div>
    <form method="GET" class="flex items-center gap-2">
        <input type="month" name="period" value="{{ $period }}" class="{{ $input }} w-44">
        <button class="border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold">Lihat</button>
    </form>
</div>

@if(session('status'))<div class="mb-5 border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>@endif
@error('finance')<div class="mb-5 border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>@enderror
@if($errors->any() && ! $errors->has('finance'))<div class="mb-5 border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>@endif

<section class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    @foreach([
        ['Kas + Bank', $rp($cashFlow['closing_cash']), 'Saldo Midtrans belum cair: '.$rp($balances['1200']['balance'] ?? 0)],
        ['Laba bersih '.$period, $rp($income['net_income']), 'Pendapatan '.$rp($income['net_revenue']).' · Beban '.$rp($income['total_expenses'])],
        ['MRR', $metric($mrr, $rp), $mrr['status'] === 'ok' ? $mrr['paying_accounts'].' toko berbayar · ARPA '.$rp($mrr['arpa']) : ($mrr['reason'] ?? '')],
        ['Runway', $metric($runway, fn ($v) => number_format($v, 1, ',', '.').' bulan'), $runway['status'] === 'ok' ? 'Burn '.$rp($runway['monthly_net_burn']).'/bulan' : ($runway['reason'] ?? '')],
    ] as [$label, $value, $hint])
    <div class="border border-slate-200 bg-white p-5">
        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $label }}</p>
        <p class="mt-2 text-2xl font-black">{{ $value }}</p>
        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    </div>
    @endforeach
</section>

<div x-data="{ tab: (() => { try { return (location.hash || '').slice(1) || localStorage.getItem('yokaFinanceTab') || 'ringkasan' } catch (e) { return 'ringkasan' } })() }"
     x-init="$watch('tab', v => { try { localStorage.setItem('yokaFinanceTab', v); history.replaceState(null, '', '#' + v) } catch (e) {} })">
    <nav class="mb-6 flex gap-2 overflow-x-auto pb-1">
        <button type="button" @click="tab = 'ringkasan'" :class="tab === 'ringkasan' ? 'bg-[#0B1B3F] text-white' : 'bg-white text-slate-700 hover:bg-slate-50'" class="whitespace-nowrap rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold">Ringkasan</button>
        <button type="button" @click="tab = 'transaksi'" :class="tab === 'transaksi' ? 'bg-[#0B1B3F] text-white' : 'bg-white text-slate-700 hover:bg-slate-50'" class="whitespace-nowrap rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold">Transaksi & tagihan</button>
        <button type="button" @click="tab = 'rekonsiliasi'" :class="tab === 'rekonsiliasi' ? 'bg-[#0B1B3F] text-white' : 'bg-white text-slate-700 hover:bg-slate-50'" class="whitespace-nowrap rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold">Rekonsiliasi</button>
        <button type="button" @click="tab = 'anggaran'" :class="tab === 'anggaran' ? 'bg-[#0B1B3F] text-white' : 'bg-white text-slate-700 hover:bg-slate-50'" class="whitespace-nowrap rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold">Anggaran & forecast</button>
        <button type="button" @click="tab = 'aset'" :class="tab === 'aset' ? 'bg-[#0B1B3F] text-white' : 'bg-white text-slate-700 hover:bg-slate-50'" class="whitespace-nowrap rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold">Aset & pendapatan</button>
        <button type="button" @click="tab = 'tutup'" :class="tab === 'tutup' ? 'bg-[#0B1B3F] text-white' : 'bg-white text-slate-700 hover:bg-slate-50'" class="whitespace-nowrap rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold">Tutup buku</button>
        <button type="button" @click="tab = 'laporan'" :class="tab === 'laporan' ? 'bg-[#0B1B3F] text-white' : 'bg-white text-slate-700 hover:bg-slate-50'" class="whitespace-nowrap rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold">Jurnal, laporan & audit</button>
    </nav>
    @unless($canWrite)<div class="mb-5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">Mode auditor: kamu bisa melihat dan mengekspor, tapi tidak bisa mencatat atau menyetujui.</div>@endunless

<div x-show="tab === 'ringkasan'" x-cloak class="space-y-6">
    <div class="grid gap-6 lg:grid-cols-2">
    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-black">Perbandingan</h2>
        <p class="mt-1 text-xs text-slate-500">{{ $comparison['labels']['mom'] }} · {{ $comparison['labels']['ytd'] }}</p>
        <table class="mt-3 w-full text-sm">
            <thead class="text-left text-xs uppercase tracking-wider text-slate-500"><tr><th class="py-1">Pos</th><th class="text-right">Bulan ini</th><th class="text-right">vs bln lalu</th><th class="text-right">YTD</th><th class="text-right">vs thn lalu</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @foreach(['net_revenue' => 'Pendapatan bersih', 'gross_profit' => 'Laba kotor', 'marketing' => 'Marketing', 'total_expenses' => 'Total beban', 'net_income' => 'Laba bersih'] as $k => $label)
                @php $m = $comparison['mom'][$k]; $y = $comparison['ytd'][$k]; @endphp
                <tr><td class="py-1.5">{{ $label }}</td><td class="text-right">{{ $rp($m['current']) }}</td>
                    <td class="text-right text-xs">{{ $m['change_pct'] === null ? '-' : ($m['change_pct'] >= 0 ? '+' : '').number_format($m['change_pct'] * 100, 1, ',', '.').'%' }}</td>
                    <td class="text-right">{{ $rp($y['current']) }}</td>
                    <td class="text-right text-xs">{{ $y['change_pct'] === null ? '-' : ($y['change_pct'] >= 0 ? '+' : '').number_format($y['change_pct'] * 100, 1, ',', '.').'%' }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </section>
    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-black">Neraca per {{ $balanceSheet['as_of'] }}</h2>
        <dl class="mt-3 space-y-1.5 text-sm">
            <div class="flex justify-between"><dt>Total aset</dt><dd class="font-bold">{{ $rp($balanceSheet['total_assets']) }}</dd></div>
            <div class="flex justify-between"><dt>Total kewajiban</dt><dd>{{ $rp($balanceSheet['total_liabilities']) }}</dd></div>
            <div class="flex justify-between"><dt>Total ekuitas (termasuk laba berjalan)</dt><dd>{{ $rp($balanceSheet['total_equity']) }}</dd></div>
            <div class="flex justify-between border-t border-slate-200 pt-1.5"><dt>Seimbang</dt><dd class="font-bold {{ $balanceSheet['balanced'] ? 'text-[#16A34A]' : 'text-red-600' }}">{{ $balanceSheet['balanced'] ? 'Ya' : 'Tidak' }}</dd></div>
            <div class="flex justify-between"><dt>Pendapatan diterima di muka</dt><dd>{{ $rp($deferred) }}</dd></div>
        </dl>
    </section>
    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-black">Retensi pendapatan {{ $period }}</h2>
        @if($retention['status'] === 'ok')
        <dl class="mt-3 grid grid-cols-3 gap-3 text-sm">
            <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs text-slate-500">NRR</dt><dd class="font-black">{{ number_format($retention['nrr'] * 100, 1, ',', '.') }}%</dd></div>
            <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs text-slate-500">GRR</dt><dd class="font-black">{{ number_format($retention['grr'] * 100, 1, ',', '.') }}%</dd></div>
            <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs text-slate-500">Revenue churn</dt><dd class="font-black">{{ number_format($retention['revenue_churn'] * 100, 1, ',', '.') }}%</dd></div>
        </dl>
        <p class="mt-2 text-xs text-slate-500">MRR awal {{ $rp($retention['start_mrr']) }} · naik {{ $rp($retention['expansion']) }} · turun {{ $rp($retention['contraction']) }} · hilang {{ $rp($retention['churned_mrr']) }} · baru {{ $rp($retention['new_mrr']) }}</p>
        @else<p class="mt-2 text-sm text-slate-500">{{ $retention['reason'] }}</p>@endif
    </section>
    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-black">Beban per cost center {{ $period }}</h2>
        <ul class="mt-3 divide-y divide-slate-100 text-sm">
            @forelse($costCenters['totals'] as $k => $v)<li class="flex justify-between py-1.5"><span>{{ $costCenters['labels'][$k] ?? $k }}</span><span class="font-bold">{{ $rp($v) }}</span></li>
            @empty<li class="py-1.5 text-slate-500">Belum ada beban.</li>@endforelse
        </ul>
    </section>
    </div>
</div>

<div x-show="tab === 'transaksi'" x-cloak>
<div class="grid gap-6 lg:grid-cols-[1.1fr_1fr]">
<section class="border border-slate-200 bg-white p-6" x-data="{ kind: '{{ old('kind', 'expense') }}' }">
    <h2 class="text-lg font-black">Catat transaksi</h2>
    <p class="mt-1 text-sm text-slate-500">Pilih jenisnya; sistem membuat jurnal debit-kredit yang benar. Pembayaran langganan Midtrans tercatat otomatis.</p>
    <form method="POST" action="{{ route('platform.finance.store') }}" enctype="multipart/form-data" class="mt-5 grid gap-4 sm:grid-cols-2">
        @csrf
        <label class="sm:col-span-2 text-sm font-semibold">Jenis
            <select name="kind" x-model="kind" class="{{ $input }} mt-1">
                <option value="expense">Biaya (iklan, server, AI, gaji, tools...)</option>
                <option value="payout">Pencairan Midtrans ke bank</option>
                <option value="capital">Setoran modal pemilik</option>
                <option value="transfer">Pindah dana antar akun Yoka</option>
                <option value="income">Pendapatan lain (jasa setup, dll.)</option>
                <option value="refund">Refund ke pelanggan</option>
                <option value="drawing">Penarikan pemilik (prive)</option>
            </select>
        </label>
        <label class="text-sm font-semibold">Tanggal<input type="date" name="date" value="{{ old('date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" class="{{ $input }} mt-1" required></label>
        <label class="text-sm font-semibold"><span x-text="kind === 'payout' ? 'Jumlah yang keluar dari saldo Midtrans' : 'Jumlah (Rp)'">Jumlah (Rp)</span><input type="number" name="amount" min="1" step="0.01" value="{{ old('amount') }}" class="{{ $input }} mt-1" required></label>

        <label class="text-sm font-semibold" x-show="kind === 'expense' || kind === 'income'">Akun
            <select name="account" class="{{ $input }} mt-1">
                @foreach($accounts->whereIn('type', ['expense', 'revenue'])->where('subtype', '!=', 'contra_revenue') as $a)
                <option value="{{ $a->code }}" x-show="(kind === 'expense') === {{ $a->type === 'expense' ? 'true' : 'false' }}" @selected(old('account') === $a->code)>{{ $a->code }} · {{ $a->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm font-semibold" x-show="kind !== 'payout'"><span x-text="kind === 'expense' ? 'Dibayar dari' : (kind === 'capital' || kind === 'income' ? 'Diterima di' : 'Dari akun')">Dibayar dari</span>
            <select name="paid_from" class="{{ $input }} mt-1">
                @foreach($cashAccounts as $a)<option value="{{ $a->code }}" @selected(old('paid_from') === $a->code)>{{ $a->name }}</option>@endforeach
                <option value="capital" x-show="kind === 'expense'">Uang pribadi pemilik, dihitung modal</option>
                <option value="loan" x-show="kind === 'expense'">Uang pribadi pemilik, pinjaman ke Yoka</option>
                <option value="reimbursement" x-show="kind === 'expense'">Uang pribadi pemilik, nanti diganti Yoka</option>
                <option value="payable" x-show="kind === 'expense'">Belum dibayar (tagihan, jadi hutang usaha)</option>
            </select>
        </label>
        <label class="text-sm font-semibold" x-show="kind === 'transfer' || kind === 'payout'">Ke akun
            <select name="to" class="{{ $input }} mt-1">
                @foreach($cashAccounts->where('subtype', '!=', 'clearing') as $a)<option value="{{ $a->code }}">{{ $a->name }}</option>@endforeach
            </select>
        </label>
        <label class="text-sm font-semibold" x-show="kind === 'payout'">Fee Midtrans dipotong (Rp)<input type="number" name="fee" min="0" step="0.01" value="{{ old('fee', 0) }}" class="{{ $input }} mt-1"></label>
        <label class="text-sm font-semibold" x-show="kind === 'payout'">No. settlement<input name="reference" value="{{ old('reference') }}" class="{{ $input }} mt-1" placeholder="Mencegah pencatatan ganda"></label>
        <label class="sm:col-span-2 text-sm font-semibold">Keterangan<input name="description" value="{{ old('description') }}" class="{{ $input }} mt-1" required maxlength="200" placeholder="Contoh: Iklan Meta kampanye trial November"></label>
        <template x-if="kind === 'expense'"><div class="sm:col-span-2 grid gap-4 sm:grid-cols-2">
            <label class="text-sm font-semibold">Vendor<input name="vendor" list="vendor-list" value="{{ old('vendor') }}" class="{{ $input }} mt-1" maxlength="120" placeholder="Meta, OpenAI, Hostinger..."></label>
            <label class="text-sm font-semibold">Cost center<select name="cost_center" class="{{ $input }} mt-1"><option value="">-</option>@foreach($costCenterLabels as $k => $l)<option value="{{ $k }}" @selected(old('cost_center') === $k)>{{ $l }}</option>@endforeach</select></label>
            <label class="text-sm font-semibold">Jatuh tempo (bila belum dibayar)<input type="date" name="due_date" value="{{ old('due_date') }}" class="{{ $input }} mt-1"></label>
            <label class="text-sm font-semibold">No. invoice<input name="invoice_no" value="{{ old('invoice_no') }}" class="{{ $input }} mt-1" maxlength="80" placeholder="Mencegah invoice dicatat dua kali"></label>
            <label class="text-sm font-semibold sm:col-span-2">File bukti (PDF/JPG/PNG/WebP, maks 5 MB)<input type="file" name="evidence_file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="mt-1 block w-full text-sm"></label>
            <label class="sm:col-span-2 flex items-center gap-2 text-sm"><input type="checkbox" name="approve_now" value="1" class="h-4 w-4"> Langsung setujui &amp; posting (tercatat di audit sebagai persetujuan sendiri)</label>
        </div></template>
        <datalist id="vendor-list">@foreach($vendors as $v)<option value="{{ $v->name }}">@endforeach</datalist>
        <label class="sm:col-span-2 text-sm font-semibold" x-show="kind !== 'expense'">Bukti (no. dokumen atau tautan)<input name="evidence" value="{{ old('evidence') }}" class="{{ $input }} mt-1" maxlength="255" placeholder="Nanda menandai transaksi tanpa bukti"></label>
        <button class="sm:col-span-2 bg-emerald-600 px-5 py-3 text-sm font-black text-white hover:bg-emerald-700">Simpan transaksi</button>
    </form>
</section>

<div class="space-y-6">
<section class="border border-slate-200 bg-white p-6">
    <h2 class="text-lg font-black">Menunggu persetujuan <span class="ml-1 bg-slate-100 px-2 py-0.5 text-sm">{{ $pending->count() }}</span></h2>
    <p class="mt-1 text-sm text-slate-500">Biaya yang diajukan (termasuk draf dari Nanda) belum masuk buku besar sampai disetujui.</p>
    <ul class="mt-4 space-y-3 text-sm">
        @forelse($pending as $x)
        <li class="border border-slate-200 p-3">
            <div class="flex justify-between gap-3"><p class="font-bold">{{ $x->description }}</p><p class="font-black">{{ $rp($x->amount) }}</p></div>
            <p class="text-xs text-slate-500">{{ $x->expense_date->format('d/m/Y') }} · akun {{ $x->account_code }} · dari {{ $x->paid_from }}{{ $x->vendor ? ' · '.$x->vendor->name : '' }}{{ $x->invoice_no ? ' · inv '.$x->invoice_no : '' }} · diajukan {{ $x->created_by_type === 'agent' ? 'Nanda (AI)' : 'admin' }}</p>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                @if($x->evidence_path)<a href="{{ route('platform.finance.expenses.evidence', $x) }}" class="text-xs font-bold text-emerald-700 hover:underline">Lihat bukti</a>@else<span class="text-xs font-bold text-amber-700">Tanpa bukti</span>@endif
                <form method="POST" action="{{ route('platform.finance.expenses.approve', $x) }}">@csrf<button class="bg-emerald-600 px-3 py-1.5 text-xs font-black text-white">Setujui &amp; posting</button></form>
                <form method="POST" action="{{ route('platform.finance.expenses.reject', $x) }}" onsubmit="const r = prompt('Alasan penolakan?'); if (!r) return false; this.reason.value = r;">@csrf<input type="hidden" name="reason"><button class="text-xs font-bold text-red-700 hover:underline">Tolak</button></form>
            </div>
        </li>
        @empty
        <li class="text-slate-500">Tidak ada yang menunggu.</li>
        @endforelse
    </ul>
</section>

<section class="border border-slate-200 bg-white p-6">
    <h2 class="text-lg font-black">Hutang usaha <span class="ml-1 bg-slate-100 px-2 py-0.5 text-sm">Rp {{ number_format($payables['total'], 0, ',', '.') }}</span></h2>
    @if($payables['overdue'] > 0)<p class="mt-1 text-sm font-semibold text-red-600">Rp {{ number_format($payables['overdue'], 0, ',', '.') }} lewat jatuh tempo.</p>@endif
    <ul class="mt-4 space-y-3 text-sm">
        @forelse($payables['bills'] as $b)
        <li class="border-b border-slate-100 pb-3">
            <p class="font-bold">{{ $b['description'] }}{{ $b['vendor'] ? ' · '.$b['vendor'] : '' }} <span class="float-right">Rp {{ number_format($b['amount'], 0, ',', '.') }}</span></p>
            <p class="text-xs {{ $b['days_overdue'] > 0 ? 'text-red-600' : 'text-slate-500' }}">Jatuh tempo {{ \Illuminate\Support\Carbon::parse($b['due_date'])->format('d/m/Y') }}{{ $b['days_overdue'] > 0 ? ' · telat '.$b['days_overdue'].' hari' : '' }}{{ $b['invoice_no'] ? ' · inv '.$b['invoice_no'] : '' }}</p>
            <form method="POST" action="{{ route('platform.finance.expenses.pay', $b['id']) }}" class="mt-2 flex flex-wrap gap-2" onsubmit="return confirm('Catat pembayaran tagihan ini?')">
                @csrf
                <select name="paid_with" class="{{ $input }} w-40">
                    @foreach($cashAccounts as $a)<option value="{{ $a->code }}">{{ $a->name }}</option>@endforeach
                    <option value="reimbursement">Ditalangi pemilik</option>
                </select>
                <input type="date" name="date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" class="{{ $input }} w-40">
                <button class="border border-slate-300 px-3 text-sm font-bold">Bayar</button>
            </form>
        </li>
        @empty
        <li class="text-slate-500">Tidak ada tagihan belum dibayar.</li>
        @endforelse
    </ul>
</section>

<section class="border border-slate-200 bg-white p-6">
    <h2 class="text-lg font-black">Temuan Nanda</h2>
    <p class="mt-1 text-sm text-slate-500">Neraca saldo {{ $trial['balanced'] ? 'seimbang' : 'TIDAK seimbang' }} per {{ $trial['as_of'] }}.</p>
    <ul class="mt-4 space-y-2 text-sm">
        @forelse($review['findings'] as $f)
        <li class="border-l-4 {{ $f['severity'] === 'high' ? 'border-red-500' : ($f['severity'] === 'medium' ? 'border-amber-500' : 'border-slate-300') }} bg-slate-50 px-3 py-2"><p class="font-bold">{{ $f['issue'] }}</p><p class="text-slate-600">{{ $f['detail'] }}</p></li>
        @empty
        <li class="text-slate-500">Tidak ada temuan.</li>
        @endforelse
    </ul>
</section>

<section class="border border-slate-200 bg-white p-6">
    <h2 class="text-lg font-black">Laba rugi {{ $period }}</h2>
    <dl class="mt-4 space-y-1.5 text-sm">
        @foreach([['Pendapatan bersih', $income['net_revenue']], ['Biaya pokok layanan (server, AI, fee)', -$income['cost_of_revenue']], ['Laba kotor', $income['gross_profit']], ['Marketing', -$income['marketing']], ['Operasional', -$income['operating_expenses']], ['Laba bersih', $income['net_income']]] as [$l, $v])
        <div class="flex justify-between {{ in_array($l, ['Laba kotor', 'Laba bersih']) ? 'border-t border-slate-200 pt-1.5 font-black' : '' }}"><dt>{{ $l }}</dt><dd class="{{ $v < 0 ? 'text-red-700' : '' }}">{{ $rp($v) }}</dd></div>
        @endforeach
    </dl>
    <p class="mt-3 text-xs text-slate-500">CAC: {{ $metric($cac, $rp) }}{{ $cac['status'] !== 'ok' ? ' · '.$cac['reason'] : '' }}</p>
</section>

<section class="border border-slate-200 bg-white p-6">
    <h2 class="text-lg font-black">Anggaran {{ $period }}</h2>
    @if($budget['status'] === 'ok')
    <table class="mt-3 w-full text-sm"><tbody class="divide-y divide-slate-100">
        @foreach($budget['rows'] as $r)<tr><td class="py-1.5">{{ $r['name'] }}</td><td class="text-right">{{ $rp($r['inputs']['actual']) }} / {{ $rp($r['inputs']['budget']) }}</td><td class="pl-3 text-right text-xs font-bold {{ $r['favorable'] ? 'text-emerald-700' : 'text-red-700' }}">{{ $r['label'] }}</td></tr>@endforeach
    </tbody></table>
    @else<p class="mt-2 text-sm text-slate-500">{{ $budget['reason'] }}</p>@endif
    <form method="POST" action="{{ route('platform.finance.budget') }}" class="mt-4 grid grid-cols-[1fr_120px_auto] gap-2">
        @csrf<input type="hidden" name="period" value="{{ $period }}">
        <select name="account" class="{{ $input }}">@foreach($accounts->where('type', 'expense') as $a)<option value="{{ $a->code }}">{{ $a->name }}</option>@endforeach</select>
        <input type="number" name="amount" min="0" step="1000" class="{{ $input }}" placeholder="Rp" required>
        <button class="border border-slate-300 px-3 text-sm font-bold">Set</button>
    </form>
</section>
</div>
</div>
</div>

<div x-show="tab === 'rekonsiliasi'" x-cloak>
<section class="mt-6 rounded-xl border border-slate-200 bg-white p-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-black">Rekonsiliasi rekening</h2>
            <p class="text-sm text-slate-500">Impor mutasi, cocokkan ke jurnal. Selisih dicatat dulu sebagai jurnal, tidak dipaksa cocok.</p>
        </div>
        <form method="GET" action="{{ route('platform.finance') }}" class="flex flex-wrap items-center gap-2">
            <select name="recon" class="{{ $input }} w-44">
                @foreach(['1100' => 'Bank', '1200' => 'Saldo Midtrans', '1000' => 'Kas'] as $code => $label)<option value="{{ $code }}" @selected($reconAccount === $code)>{{ $label }}</option>@endforeach
            </select>
            <input type="number" step="0.01" name="closing" value="{{ request('closing') }}" placeholder="Saldo akhir di rekening" class="{{ $input }} w-48">
            <button class="border border-slate-300 px-3 py-2.5 text-sm font-bold">Cek</button>
        </form>
    </div>
    <div class="mt-4 grid gap-3 text-sm sm:grid-cols-4">
        <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Saldo buku</p><p class="font-black">Rp {{ number_format($recon['ledger_balance'], 0, ',', '.') }}</p></div>
        <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Mutasi cocok</p><p class="font-black">{{ $recon['matched'] }} / {{ $recon['statement_lines'] }}</p></div>
        <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Di rekening, belum di buku</p><p class="font-black">{{ count($recon['not_in_books']) }} · Rp {{ number_format($recon['not_in_books_net'], 0, ',', '.') }}</p></div>
        <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Selisih</p><p class="font-black {{ $recon['reconciled'] === false ? 'text-red-600' : ($recon['reconciled'] ? 'text-[#16A34A]' : '') }}">{{ $recon['difference'] === null ? 'isi saldo akhir' : 'Rp '.number_format($recon['difference'], 0, ',', '.') }}</p></div>
    </div>
    @if($recon['not_in_books'])
    <ul class="mt-4 divide-y divide-slate-100 text-sm">
        @foreach($recon['not_in_books'] as $s)
        <li class="flex flex-wrap items-center gap-2 py-2">
            <span class="w-24 text-slate-500">{{ \Illuminate\Support\Carbon::parse($s['date'])->format('d/m/Y') }}</span>
            <span class="flex-1 font-semibold">{{ $s['description'] }}</span>
            <span class="w-32 text-right font-bold {{ $s['amount'] < 0 ? 'text-red-600' : '' }}">{{ number_format($s['amount'], 0, ',', '.') }}</span>
            @if(($reconCandidates[$s['id']] ?? collect())->isNotEmpty())
            <form method="POST" action="{{ route('platform.finance.statements.reconcile', $s['id']) }}" class="flex gap-2">
                @csrf<input type="hidden" name="action" value="match">
                <select name="line_id" class="{{ $input }} w-56">@foreach($reconCandidates[$s['id']] as $l)<option value="{{ $l->id }}">{{ $l->entry->entry_date->format('d/m') }} · {{ \Illuminate\Support\Str::limit($l->entry->description, 30) }}</option>@endforeach</select>
                <button class="border border-slate-300 px-3 text-sm font-bold">Cocokkan</button>
            </form>
            @else
            <span class="text-xs text-amber-600">Belum ada jurnalnya, catat dulu</span>
            @endif
            <form method="POST" action="{{ route('platform.finance.statements.reconcile', $s['id']) }}" class="flex gap-2">
                @csrf<input type="hidden" name="action" value="ignore">
                <input name="reason" minlength="5" required placeholder="Alasan abaikan" class="{{ $input }} w-36">
                <button class="border border-slate-300 px-3 text-sm font-bold">Abaikan</button>
            </form>
        </li>
        @endforeach
    </ul>
    @endif
    @if($recon['not_in_bank'])
    <p class="mt-4 text-xs font-bold uppercase tracking-wider text-slate-500">Di buku, belum muncul di rekening</p>
    <ul class="mt-1 text-sm text-slate-600">@foreach(array_slice($recon['not_in_bank'], 0, 10) as $l)<li>{{ \Illuminate\Support\Carbon::parse($l['date'])->format('d/m/Y') }} · {{ $l['description'] }} · {{ number_format($l['amount'], 0, ',', '.') }}</li>@endforeach</ul>
    @endif
    <form method="POST" action="{{ route('platform.finance.statements.import') }}" enctype="multipart/form-data" class="mt-5 grid gap-2 border-t border-slate-100 pt-4">
        @csrf<input type="hidden" name="account" value="{{ $reconAccount }}">
        <label class="text-sm font-semibold">Impor mutasi (CSV: tanggal, keterangan, nominal +masuk/-keluar, referensi)</label>
        <textarea name="csv" rows="3" class="{{ $input }}" placeholder="2026-11-18,Transfer masuk Midtrans,1250000,STL-001"></textarea>
        <div class="flex flex-wrap items-center gap-3"><input type="file" name="file" accept=".csv,.txt" class="text-sm"><button class="rounded-lg bg-[#0B1B3F] px-4 py-2.5 text-sm font-bold text-white">Impor &amp; cocokkan otomatis</button></div>
    </form>
</section>

</div>

<div x-show="tab === 'anggaran'" x-cloak class="space-y-6">
<section class="rounded-xl border border-slate-200 bg-white p-6">
    <h2 class="text-lg font-black">Skenario 6 bulan ke depan</h2>
    <p class="mt-1 text-sm text-slate-500">Dari data nyata 3 bulan terakhir ({{ $scenarios['base']['assumptions']['basis'] }}). Churn: {{ $scenarios['base']['assumptions']['churn_source'] }}.</p>
    <div class="mt-4 grid gap-3 sm:grid-cols-3">
        @foreach(['conservative' => 'Konservatif', 'base' => 'Dasar', 'growth' => 'Growth'] as $k => $label)
        <div class="rounded-xl border border-slate-200 p-4">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $label }}</p>
            <p class="mt-1 text-xl font-black {{ $scenarios[$k]['totals']['net'] < 0 ? 'text-red-600' : 'text-[#16A34A]' }}">{{ $rp($scenarios[$k]['totals']['net']) }}</p>
            <p class="text-xs text-slate-500">Pendapatan {{ $rp($scenarios[$k]['totals']['revenue']) }} · beban {{ $rp($scenarios[$k]['totals']['expenses']) }}</p>
            <p class="mt-1 text-xs text-slate-500">Pelanggan bulan ke-6: {{ number_format($scenarios[$k]['last_month']['customers'] ?? 0, 1, ',', '.') }} · baru/bln {{ $scenarios[$k]['assumptions']['new_customers_per_month'] }} · churn {{ number_format($scenarios[$k]['assumptions']['churn_rate'] * 100, 1, ',', '.') }}%</p>
        </div>
        @endforeach
    </div>
    @if($canWrite)
    <form method="POST" action="{{ route('platform.finance.budget-versions.store') }}" class="mt-5 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-4">
        @csrf
        <label class="text-sm font-semibold">Skenario<select name="scenario" class="{{ $input }} mt-1">@foreach(['base' => 'Dasar', 'conservative' => 'Konservatif', 'growth' => 'Growth'] as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
        <label class="text-sm font-semibold">Mulai<input type="month" name="period_from" value="{{ today()->addMonth()->format('Y-m') }}" class="{{ $input }} mt-1" required></label>
        <label class="text-sm font-semibold">Bulan<input type="number" name="months" min="1" max="12" value="3" class="{{ $input }} mt-1" required></label>
        <label class="text-sm font-semibold">Nama<input name="name" class="{{ $input }} mt-1" maxlength="120" placeholder="Anggaran Q1"></label>
        <label class="text-sm font-semibold">ARPA (opsional)<input type="number" step="1" min="0" name="arpa" class="{{ $input }} mt-1"></label>
        <label class="text-sm font-semibold">Pelanggan baru/bln<input type="number" step="0.1" min="0" name="new_customers_per_month" class="{{ $input }} mt-1"></label>
        <label class="text-sm font-semibold">Churn/bln (0-1)<input type="number" step="0.001" min="0" max="1" name="churn_rate" class="{{ $input }} mt-1"></label>
        <label class="text-sm font-semibold">CAC<input type="number" step="1" min="0" name="cac" class="{{ $input }} mt-1"></label>
        <button class="sm:col-span-4 rounded-lg bg-[#0B1B3F] px-4 py-2.5 text-sm font-bold text-white">Buat draf anggaran</button>
    </form>
    @endif
</section>

<section class="rounded-xl border border-slate-200 bg-white p-6">
    <h2 class="text-lg font-black">Versi anggaran</h2>
    <ul class="mt-3 divide-y divide-slate-100 text-sm">
        @forelse($versions as $v)
        <li class="flex flex-wrap items-center gap-3 py-2">
            <span class="flex-1"><span class="font-bold">{{ $v->name }}</span> · {{ \App\Models\PlatformFinance\BudgetVersion::SCENARIOS[$v->scenario] ?? $v->scenario }} · {{ $v->period_from }} ({{ $v->months }} bln) · dibuat {{ $v->created_by_type === 'agent' ? 'Luna (AI)' : 'admin' }}</span>
            <x-pill :tone="['approved' => 'green', 'draft' => 'amber', 'rejected' => 'red'][$v->status] ?? 'slate'">{{ ['approved' => 'Aktif', 'draft' => 'Draf', 'rejected' => 'Ditolak', 'archived' => 'Arsip'][$v->status] ?? $v->status }}</x-pill>
            @if($v->status === 'draft' && $canWrite)
            <form method="POST" action="{{ route('platform.finance.budget-versions.decide', $v) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="rounded-lg bg-[#16A34A] px-3 py-1.5 text-xs font-bold text-white">Setujui</button></form>
            <form method="POST" action="{{ route('platform.finance.budget-versions.decide', $v) }}" class="flex gap-1">@csrf<input type="hidden" name="decision" value="reject"><input name="reason" required placeholder="Alasan" class="{{ $input }} w-32 py-1.5"><button class="border border-slate-300 px-2 text-xs font-bold">Tolak</button></form>
            @endif
        </li>
        @empty<li class="py-2 text-slate-500">Belum ada versi anggaran.</li>@endforelse
    </ul>
</section>

<div class="grid gap-6 lg:grid-cols-2">
<section class="rounded-xl border border-slate-200 bg-white p-6">
    <div class="flex items-center justify-between"><h2 class="text-lg font-black">Peramalan kas</h2>
        <form method="GET" class="flex gap-2">@foreach(['base' => 'Dasar', 'conservative' => 'Konservatif', 'growth' => 'Growth'] as $k => $l)<button name="scenario" value="{{ $k }}" class="rounded-lg border border-slate-200 px-2 py-1 text-xs font-bold {{ request('scenario', 'base') === $k ? 'bg-[#0B1B3F] text-white' : '' }}">{{ $l }}</button>@endforeach</form></div>
    @if($forecast['first_negative_month'])<p class="mt-2 text-sm font-bold text-red-600">Kas diperkirakan minus mulai {{ $forecast['first_negative_month'] }}.</p>@endif
    <table class="mt-3 w-full text-sm"><thead class="text-left text-xs uppercase tracking-wider text-slate-500"><tr><th class="py-1">Bulan</th><th class="text-right">Masuk</th><th class="text-right">Keluar</th><th class="text-right">Tagihan</th><th class="text-right">Saldo akhir</th></tr></thead>
        <tbody class="divide-y divide-slate-100">@foreach($forecast['rows'] as $r)<tr><td class="py-1.5">{{ $r['period'] }}</td><td class="text-right">{{ $rp($r['inflow']) }}</td><td class="text-right">{{ $rp($r['outflow']) }}</td><td class="text-right">{{ $rp($r['bills']) }}</td><td class="text-right font-bold {{ $r['closing'] < 0 ? 'text-red-600' : '' }}">{{ $rp($r['closing']) }}</td></tr>@endforeach</tbody></table>
    <p class="mt-2 text-xs text-slate-500">{{ $forecast['note'] }} Saldo Midtrans belum cair dihitung masuk bulan pertama.</p>
</section>
<section class="rounded-xl border border-slate-200 bg-white p-6">
    <h2 class="text-lg font-black">Rolling forecast 12 bulan</h2>
    <table class="mt-3 w-full text-sm"><thead class="text-left text-xs uppercase tracking-wider text-slate-500"><tr><th class="py-1">Bulan</th><th>Jenis</th><th class="text-right">Pendapatan</th><th class="text-right">Beban</th><th class="text-right">Laba</th></tr></thead>
        <tbody class="divide-y divide-slate-100">@foreach($rolling['rows'] as $r)<tr class="{{ $r['type'] === 'aktual' ? '' : 'text-slate-500' }}"><td class="py-1.5">{{ $r['period'] }}</td><td class="text-xs">{{ $r['type'] }}</td><td class="text-right">{{ $rp($r['revenue']) }}</td><td class="text-right">{{ $rp($r['expenses']) }}</td><td class="text-right font-bold">{{ $rp($r['net']) }}</td></tr>@endforeach</tbody></table>
    <p class="mt-2 text-xs text-slate-500">{{ $rolling['note'] }}</p>
</section>
</div>
</div>

<div x-show="tab === 'aset'" x-cloak class="space-y-6">
<section class="rounded-xl border border-slate-200 bg-white p-6">
    <h2 class="text-lg font-black">Aset tetap</h2>
    <p class="mt-1 text-sm text-slate-500">Garis lurus, mulai bulan setelah dibeli. Penyusutan bulan lalu juga diposting otomatis tiap hari oleh cron (idempoten).</p>
    <table class="mt-3 w-full text-sm"><thead class="text-left text-xs uppercase tracking-wider text-slate-500"><tr><th class="py-1">Aset</th><th>Dibeli</th><th class="text-right">Harga</th><th class="text-right">Akumulasi</th><th class="text-right">Nilai buku</th><th class="text-right">/bulan</th></tr></thead>
        <tbody class="divide-y divide-slate-100">@forelse($assets as $a)<tr><td class="py-1.5 font-semibold">{{ $a['name'] }}</td><td>{{ \Illuminate\Support\Carbon::parse($a['acquired_at'])->format('d/m/Y') }}</td><td class="text-right">{{ $rp($a['cost']) }}</td><td class="text-right">{{ $rp($a['accumulated']) }}</td><td class="text-right font-bold">{{ $rp($a['book_value']) }}</td><td class="text-right">{{ $rp($a['monthly']) }}</td></tr>@empty<tr><td colspan="6" class="py-2 text-slate-500">Belum ada aset.</td></tr>@endforelse</tbody></table>
    @if($canWrite)
    <form method="POST" action="{{ route('platform.finance.assets.store') }}" class="mt-5 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-4">
        @csrf
        <label class="sm:col-span-2 text-sm font-semibold">Nama aset<input name="name" required maxlength="150" class="{{ $input }} mt-1" placeholder="Laptop kerja"></label>
        <label class="text-sm font-semibold">Tanggal beli<input type="date" name="acquired_at" max="{{ today()->toDateString() }}" value="{{ today()->toDateString() }}" required class="{{ $input }} mt-1"></label>
        <label class="text-sm font-semibold">Harga<input type="number" name="cost" min="1" step="1" required class="{{ $input }} mt-1"></label>
        <label class="text-sm font-semibold">Nilai sisa<input type="number" name="salvage_value" min="0" step="1" value="0" class="{{ $input }} mt-1"></label>
        <label class="text-sm font-semibold">Umur (bulan)<input type="number" name="useful_life_months" min="1" max="600" value="48" required class="{{ $input }} mt-1"></label>
        <label class="text-sm font-semibold">Dibayar dari<select name="paid_from" class="{{ $input }} mt-1">@foreach($cashAccounts as $c)<option value="{{ $c->code }}">{{ $c->name }}</option>@endforeach<option value="capital">Uang pribadi, modal</option><option value="reimbursement">Uang pribadi, diganti</option><option value="payable">Belum dibayar</option></select></label>
        <label class="text-sm font-semibold">Cost center<select name="cost_center" class="{{ $input }} mt-1"><option value="">-</option>@foreach($costCenterLabels as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
        <button class="sm:col-span-4 rounded-lg bg-[#0B1B3F] px-4 py-2.5 text-sm font-bold text-white">Catat aset</button>
    </form>
    <form method="POST" action="{{ route('platform.finance.depreciate') }}" class="mt-3 flex flex-wrap items-center gap-2">
        @csrf<input type="month" name="period" value="{{ today()->subMonth()->format('Y-m') }}" max="{{ today()->subMonth()->format('Y-m') }}" class="{{ $input }} w-44">
        <button class="border border-slate-300 px-3 py-2.5 text-sm font-bold">Posting penyusutan</button>
    </form>
    @endif
</section>
<section class="rounded-xl border border-slate-200 bg-white p-6">
    <h2 class="text-lg font-black">Pendapatan diterima di muka</h2>
    <p class="mt-1 text-sm text-slate-500">Paket bulanan diakui saat dibayar. Paket lebih dari 1 bulan (mis. tahunan) dicatat sebagai kewajiban lalu diakui merata per bulan layanan secara otomatis.</p>
    <p class="mt-3 text-2xl font-black">{{ $rp($deferred) }}</p>
    @if($canWrite)<form method="POST" action="{{ route('platform.finance.recognize') }}" class="mt-3">@csrf<button class="border border-slate-300 px-3 py-2.5 text-sm font-bold">Akui pendapatan yang jatuh waktu sekarang</button></form>@endif
</section>
</div>

<div x-show="tab === 'tutup'" x-cloak>
<section class="mt-6 rounded-xl border border-slate-200 bg-white p-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-black">Tutup buku bulanan</h2>
            <p class="text-sm text-slate-500">Merah memblokir penutupan. Kuning boleh ditutup setelah kamu tinjau.</p>
        </div>
        <form method="GET" action="{{ route('platform.finance') }}" class="flex items-center gap-2">
            <input type="hidden" name="period" value="{{ $period }}">
            <input type="month" name="close" value="{{ $closePeriod }}" max="{{ today()->subMonth()->format('Y-m') }}" class="{{ $input }} w-40">
            <button class="border border-slate-300 px-3 py-2.5 text-sm font-bold">Cek</button>
        </form>
    </div>
    <ul class="mt-4 divide-y divide-slate-100 text-sm">
        @foreach($closeCheck['items'] as $item)
            <li class="flex items-start gap-3 py-2">
                <span class="mt-0.5 inline-block h-2.5 w-2.5 shrink-0 rounded-full {{ ['ok' => 'bg-[#16A34A]', 'warn' => 'bg-amber-400', 'fail' => 'bg-red-500'][$item['status']] }}"></span>
                <span class="font-semibold">{{ $item['label'] }}</span>
                @if($item['detail'])<span class="text-slate-500">{{ $item['detail'] }}</span>@endif
            </li>
        @endforeach
    </ul>
    <div class="mt-4 flex flex-wrap items-center gap-4">
        @if($closeCheck['closed'])
            <span class="text-sm font-bold text-[#16A34A]">Periode {{ $closePeriod }} sudah ditutup.</span>
        @elseif($closeCheck['can_close'])
            <form method="POST" action="{{ route('platform.finance.close') }}" class="flex items-center gap-3" onsubmit="return confirm('Tutup periode {{ $closePeriod }}? Transaksi di periode itu tidak bisa ditambah lagi.')">
                @csrf<input type="hidden" name="period" value="{{ $closePeriod }}">
                @if($closeCheck['has_warnings'])<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="acknowledge" value="1" required> Peringatan sudah saya tinjau</label>@endif
                <button class="rounded-lg bg-[#0B1B3F] px-4 py-2.5 text-sm font-bold text-white">Tutup periode {{ $closePeriod }}</button>
            </form>
        @else
            <span class="text-sm font-bold text-red-600">Belum bisa ditutup.</span>
        @endif
        @if($lastClosed && auth('platform')->user()->role === 'super_admin')
            <form method="POST" action="{{ route('platform.finance.reopen') }}" class="flex flex-wrap items-center gap-2" onsubmit="return confirm('Buka ulang periode {{ $lastClosed->period }}? Tercatat di audit.')">
                @csrf<input type="hidden" name="period" value="{{ $lastClosed->period }}">
                <input name="reason" minlength="10" required placeholder="Alasan buka ulang {{ $lastClosed->period }}" class="{{ $input }} w-64">
                <button class="border border-slate-300 px-3 py-2.5 text-sm font-bold">Buka ulang</button>
            </form>
        @endif
    </div>
</section>

</div>

<div x-show="tab === 'laporan'" x-cloak>
<section class="mt-6 rounded-xl border border-slate-200 bg-white p-5">
    <h2 class="text-lg font-black">Ekspor laporan {{ $period }} (CSV, buka di Excel)</h2>
    <div class="mt-3 flex flex-wrap gap-2">
        <a href="{{ route('platform.finance.export', ['report' => 'income', 'period' => $period]) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold hover:bg-slate-50">Laba rugi</a>
        <a href="{{ route('platform.finance.export', ['report' => 'balance', 'period' => $period]) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold hover:bg-slate-50">Neraca</a>
        <a href="{{ route('platform.finance.export', ['report' => 'trial', 'period' => $period]) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold hover:bg-slate-50">Neraca saldo</a>
        <a href="{{ route('platform.finance.export', ['report' => 'journal', 'period' => $period]) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold hover:bg-slate-50">Jurnal</a>
        <a href="{{ route('platform.finance.export', ['report' => 'comparison', 'period' => $period]) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold hover:bg-slate-50">MoM & YTD</a>
        <a href="{{ route('platform.finance.export', ['report' => 'budget', 'period' => $period]) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold hover:bg-slate-50">Anggaran vs aktual</a>
        <a href="{{ route('platform.finance.export', ['report' => 'cash_forecast', 'period' => $period]) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold hover:bg-slate-50">Peramalan kas</a>
        <a href="{{ route('platform.finance.export', ['report' => 'payables', 'period' => $period]) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold hover:bg-slate-50">Hutang usaha</a>
        <a href="{{ route('platform.finance.export', ['report' => 'cost_centers', 'period' => $period]) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold hover:bg-slate-50">Cost center</a>
        <a href="{{ route('platform.finance.export', ['report' => 'assets', 'period' => $period]) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold hover:bg-slate-50">Aset</a>
    </div>
</section>

<section class="mt-6 overflow-x-auto border border-slate-200 bg-white">
    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
        <h2 class="text-lg font-black">Jurnal</h2>
        @if($closed->isNotEmpty())<span class="text-xs text-slate-500">Ditutup: {{ $closed->take(3)->implode(', ') }}</span>@endif
    </div>
    <table class="w-full min-w-[820px] text-sm">
        <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-3">Tanggal</th><th class="px-5 py-3">Keterangan</th><th class="px-5 py-3">Akun</th><th class="px-5 py-3 text-right">Debit</th><th class="px-5 py-3 text-right">Kredit</th><th class="px-5 py-3"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse($entries as $e)
            <tr class="{{ $e->reversed_by_id || $e->reversal_of_id ? 'text-slate-400' : '' }} align-top">
                <td class="px-5 py-3 whitespace-nowrap">{{ $e->entry_date->format('d/m/Y') }}</td>
                <td class="px-5 py-3"><p class="font-semibold">{{ $e->description }}</p><p class="text-xs text-slate-500">#{{ $e->id }} · {{ $e->source }}{{ $e->evidence ? ' · bukti: '.$e->evidence : ' · tanpa bukti' }}{{ $e->reversed_by_id ? ' · dibalik' : '' }}</p></td>
                <td class="px-5 py-3">@foreach($e->lines as $l)<p>{{ $l->account->code }} {{ $l->account->name }}</p>@endforeach</td>
                <td class="px-5 py-3 text-right">@foreach($e->lines as $l)<p>{{ $l->debit > 0 ? $rp($l->debit) : '' }}&nbsp;</p>@endforeach</td>
                <td class="px-5 py-3 text-right">@foreach($e->lines as $l)<p>{{ $l->credit > 0 ? $rp($l->credit) : '' }}&nbsp;</p>@endforeach</td>
                <td class="px-5 py-3 text-right">
                    @unless($e->reversed_by_id || $e->reversal_of_id)
                    <form method="POST" action="{{ route('platform.finance.reverse', $e) }}" onsubmit="const r = prompt('Alasan pembalikan jurnal #{{ $e->id }}?'); if (!r) return false; this.reason.value = r;">
                        @csrf<input type="hidden" name="reason"><button class="text-xs font-bold text-red-700 hover:underline">Balik</button>
                    </form>
                    @endunless
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">Belum ada transaksi. Mulai dari setoran modal atau biaya pertama.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="px-5 py-4">{{ $entries->withQueryString()->links() }}</div>
</section>

<section class="mt-6 border border-slate-200 bg-white p-6">
    <h2 class="text-lg font-black">Jejak audit keuangan</h2>
    <p class="mt-1 text-sm text-slate-500">Tidak bisa diubah atau dihapus.</p>
    <ul class="mt-3 divide-y divide-slate-100 text-sm">
        @forelse($audit as $a)
        <li class="flex flex-wrap justify-between gap-2 py-2"><span><span class="font-bold">{{ $a->action }}</span> · {{ $a->subject_type }} #{{ $a->subject_id }}{{ $a->reason ? ' · '.$a->reason : '' }}</span><span class="text-xs text-slate-500">{{ $a->actor_type === 'agent' ? 'AI' : 'admin' }} #{{ $a->actor_id }} · {{ $a->created_at->format('d/m/Y H:i') }}</span></li>
        @empty
        <li class="py-2 text-slate-500">Belum ada aktivitas.</li>
        @endforelse
    </ul>
</section>
</div>
</div>
@endsection
