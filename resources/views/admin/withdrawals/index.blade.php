@extends('layouts.app')
@section('title', 'Kelola Penarikan')
@section('page-title', 'Kelola Penarikan Merchant')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
            <p class="text-xs text-gray-500">Menunggu Persetujuan</p>
            <p class="text-2xl font-bold text-amber-600">{{ $stats['pending'] }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
            <p class="text-xs text-gray-500">Sedang Diproses</p>
            <p class="text-2xl font-bold text-blue-600">{{ $stats['processing'] }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
            <p class="text-xs text-gray-500">Total Pending (Rp)</p>
            <p class="text-2xl font-bold text-red-600">{{ number_format($stats['total_pending_amount'], 0, ',', '.') }}</p>
        </div>
    </div>

    {{-- Filter tabs --}}
    <div class="flex gap-2 flex-wrap">
        @foreach([''=>'Semua','pending'=>'Pending','processing'=>'Diproses','completed'=>'Selesai','rejected'=>'Ditolak'] as $val => $label)
            <a href="{{ route('admin.withdrawals.index', $val ? ['status'=>$val] : []) }}"
               class="px-4 py-1.5 rounded-full text-sm font-medium border transition
                {{ request('status', '') === $val ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-400' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">#</th>
                    <th class="px-4 py-3 text-left">Merchant</th>
                    <th class="px-4 py-3 text-left">Tujuan Transfer</th>
                    <th class="px-4 py-3 text-right">Jumlah</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-left">Tanggal</th>
                    <th class="px-4 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($withdrawals as $wd)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-400">{{ $wd->id }}</td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-800">{{ $wd->business->name }}</p>
                            @if($wd->notes)
                                <p class="text-xs text-gray-400">{{ $wd->notes }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ $wd->bank_name }}</p>
                            <p class="text-xs text-gray-500">{{ $wd->account_number }}</p>
                            <p class="text-xs text-gray-500">a.n. {{ $wd->account_name }}</p>
                        </td>
                        <td class="px-4 py-3 text-right font-semibold text-gray-800">
                            Rp {{ number_format($wd->amount, 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $colors = ['pending'=>'amber','processing'=>'blue','completed'=>'green','rejected'=>'red'];
                                $labels = ['pending'=>'Pending','processing'=>'Diproses','completed'=>'Selesai','rejected'=>'Ditolak'];
                                $c = $colors[$wd->status] ?? 'gray';
                            @endphp
                            <span class="inline-block text-xs px-2 py-0.5 rounded-full bg-{{ $c }}-100 text-{{ $c }}-700">
                                {{ $labels[$wd->status] ?? $wd->status }}
                            </span>
                            @if($wd->admin_notes)
                                <p class="text-xs text-gray-400 mt-0.5">{{ Str::limit($wd->admin_notes, 40) }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            {{ $wd->created_at->format('d M Y') }}<br>
                            {{ $wd->created_at->format('H:i') }}
                        </td>
                        <td class="px-4 py-3 text-center" x-data="{ showReject: false }">
                            @if($wd->status === 'pending')
                                <form method="POST" action="{{ route('admin.withdrawals.approve', $wd) }}" class="inline">
                                    @csrf @method('PATCH')
                                    <button class="text-xs bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg transition">
                                        Proses
                                    </button>
                                </form>
                                <button @click="showReject=true" class="text-xs text-red-500 hover:text-red-700 px-2 py-1.5 transition">
                                    Tolak
                                </button>
                                <div x-show="showReject" x-cloak class="mt-2 text-left">
                                    <form method="POST" action="{{ route('admin.withdrawals.reject', $wd) }}">
                                        @csrf @method('PATCH')
                                        <textarea name="admin_notes" rows="2" required placeholder="Alasan penolakan..."
                                            class="w-full text-xs border border-red-200 rounded-lg p-2 focus:outline-none"></textarea>
                                        <button class="mt-1 text-xs bg-red-500 text-white px-3 py-1 rounded-lg w-full">Konfirmasi Tolak</button>
                                    </form>
                                </div>

                            @elseif($wd->status === 'processing')
                                <div x-data="{ showComplete: false }">
                                    <button @click="showComplete=true"
                                        class="text-xs bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg transition">
                                        Selesaikan
                                    </button>
                                    <button @click="showReject=true" class="text-xs text-red-500 hover:text-red-700 px-2 py-1.5 transition">
                                        Tolak
                                    </button>
                                    <div x-show="showComplete" x-cloak class="mt-2 text-left">
                                        <form method="POST" action="{{ route('admin.withdrawals.complete', $wd) }}">
                                            @csrf @method('PATCH')
                                            <textarea name="admin_notes" rows="2" placeholder="Bukti transfer / catatan (opsional)"
                                                class="w-full text-xs border border-green-200 rounded-lg p-2 focus:outline-none"></textarea>
                                            <button class="mt-1 text-xs bg-green-600 text-white px-3 py-1 rounded-lg w-full">Konfirmasi Selesai</button>
                                        </form>
                                    </div>
                                    <div x-show="showReject" x-cloak class="mt-2 text-left">
                                        <form method="POST" action="{{ route('admin.withdrawals.reject', $wd) }}">
                                            @csrf @method('PATCH')
                                            <textarea name="admin_notes" rows="2" required placeholder="Alasan penolakan..."
                                                class="w-full text-xs border border-red-200 rounded-lg p-2 focus:outline-none"></textarea>
                                            <button class="mt-1 text-xs bg-red-500 text-white px-3 py-1 rounded-lg w-full">Konfirmasi Tolak</button>
                                        </form>
                                    </div>
                                </div>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-gray-400 text-sm">
                            Tidak ada permintaan penarikan.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-50">{{ $withdrawals->links() }}</div>
    </div>
</div>
@endsection
