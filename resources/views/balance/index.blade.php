@extends('layouts.app')
@section('title', 'Saldo Merchant')
@section('page-title', 'Saldo & Penarikan')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

    {{-- Alert messages --}}
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif

    {{-- Balance cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <p class="text-xs text-gray-500 mb-1">Saldo Tersedia</p>
            <p class="text-2xl font-bold text-green-600">Rp {{ number_format($business->balance - $pendingWd, 0, ',', '.') }}</p>
            @if($pendingWd > 0)
                <p class="text-xs text-amber-600 mt-1">Rp {{ number_format($pendingWd, 0, ',', '.') }} sedang diproses</p>
            @endif
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <p class="text-xs text-gray-500 mb-1">Total Pendapatan</p>
            <p class="text-2xl font-bold text-blue-600">Rp {{ number_format($business->total_earned, 0, ',', '.') }}</p>
            <p class="text-xs text-gray-400 mt-1">Sejak bergabung</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <p class="text-xs text-gray-500 mb-1">Total Ditarik</p>
            <p class="text-2xl font-bold text-gray-700">Rp {{ number_format($business->total_withdrawn, 0, ',', '.') }}</p>
            <p class="text-xs text-gray-400 mt-1">Sudah cair ke rekening</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Withdrawal Form --}}
        <div class="lg:col-span-1">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <h3 class="font-semibold text-gray-800 mb-4">Ajukan Penarikan</h3>

                @php
                    $available = $business->balance - $pendingWd;
                @endphp

                @if($available < 50000)
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-sm text-amber-700">
                        Saldo minimum untuk penarikan Rp 50.000. Saldo tersedia Rp {{ number_format($available, 0, ',', '.') }}.
                    </div>
                @else
                    <form method="POST" action="{{ route('balance.withdraw') }}" class="space-y-3">
                        @csrf
                        <div>
                            <label class="text-xs text-gray-600 font-medium">Jumlah (min. Rp 50.000)</label>
                            <input type="number" name="amount" min="50000" max="{{ $available }}" step="1000"
                                   value="{{ old('amount') }}"
                                   class="mt-1 w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-400"
                                   placeholder="Rp ..." required>
                        </div>
                        <div>
                            <label class="text-xs text-gray-600 font-medium">Nama Bank</label>
                            <select name="bank_name" class="mt-1 w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-400" required>
                                <option value="">-- Pilih Bank --</option>
                                @foreach(['BCA','BNI','BRI','Mandiri','CIMB Niaga','Danamon','Permata','Jenius','Jago','OVO','GoPay','DANA','ShopeePay'] as $bank)
                                    <option value="{{ $bank }}" @selected(old('bank_name') === $bank)>{{ $bank }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-gray-600 font-medium">Nomor Rekening / Akun</label>
                            <input type="text" name="account_number" value="{{ old('account_number') }}"
                                   class="mt-1 w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-400"
                                   placeholder="08xx / 1234567890" required>
                        </div>
                        <div>
                            <label class="text-xs text-gray-600 font-medium">Nama Pemilik Rekening</label>
                            <input type="text" name="account_name" value="{{ old('account_name') }}"
                                   class="mt-1 w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-400"
                                   placeholder="Sesuai KTP" required>
                        </div>
                        <div>
                            <label class="text-xs text-gray-600 font-medium">Catatan (opsional)</label>
                            <textarea name="notes" rows="2"
                                      class="mt-1 w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-400"
                                      placeholder="Catatan tambahan...">{{ old('notes') }}</textarea>
                        </div>
                        <button type="submit"
                                class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-2.5 rounded-xl text-sm transition">
                            Ajukan Penarikan
                        </button>
                    </form>
                @endif

                {{-- Pending WD list --}}
                @php
                    $pendingWdList = $business->withdrawalRequests()->whereIn('status',['pending','processing'])->latest()->get();
                @endphp
                @if($pendingWdList->count())
                    <div class="mt-5 border-t pt-4">
                        <p class="text-xs font-semibold text-gray-500 mb-2">Penarikan Aktif</p>
                        @foreach($pendingWdList as $wd)
                            <div class="flex items-center justify-between py-2 border-b border-gray-50">
                                <div>
                                    <p class="text-sm font-medium text-gray-800">Rp {{ number_format($wd->amount, 0, ',', '.') }}</p>
                                    <p class="text-xs text-gray-500">{{ $wd->bank_name }} · {{ $wd->account_number }}</p>
                                </div>
                                <div class="text-right">
                                    <span class="inline-block text-xs px-2 py-0.5 rounded-full
                                        {{ $wd->status === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700' }}">
                                        {{ $wd->status === 'pending' ? 'Menunggu' : 'Diproses' }}
                                    </span>
                                    @if($wd->isPending())
                                        <form method="POST" action="{{ route('balance.cancel-withdrawal', $wd) }}" class="mt-1">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs text-red-500 hover:underline">Batalkan</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Transaction history --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <h3 class="font-semibold text-gray-800 mb-4">Riwayat Transaksi</h3>

                @if($transactions->isEmpty())
                    <p class="text-sm text-gray-400 text-center py-8">Belum ada transaksi.</p>
                @else
                    <div class="space-y-1">
                        @foreach($transactions as $tx)
                            <div class="flex items-center justify-between py-2.5 border-b border-gray-50">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm
                                        {{ $tx->type === 'credit' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                        {{ $tx->type === 'credit' ? '+' : '-' }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-800">{{ $tx->description }}</p>
                                        <p class="text-xs text-gray-400">{{ $tx->created_at->format('d M Y, H:i') }}
                                            @if($tx->reference) · #{{ $tx->reference }} @endif
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-semibold text-sm {{ $tx->type === 'credit' ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $tx->type === 'credit' ? '+' : '-' }}Rp {{ number_format($tx->net_amount, 0, ',', '.') }}
                                    </p>
                                    @if($tx->platform_fee > 0)
                                        <p class="text-xs text-gray-400">Fee: Rp {{ number_format($tx->platform_fee, 0, ',', '.') }}</p>
                                    @endif
                                    <p class="text-xs text-gray-400">Saldo: Rp {{ number_format($tx->balance_after, 0, ',', '.') }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4">{{ $transactions->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
