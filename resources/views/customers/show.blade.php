@extends('layouts.app')
@section('title', $customer->name)
@section('page-title', $customer->name)
@section('content')
<div class="flex items-center justify-between mb-5">
    <a href="{{ route('customers.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Pelanggan</a>
    <a href="{{ route('customers.edit', $customer) }}"
       class="text-sm font-semibold bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl transition-colors">Edit</a>
</div>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
    <x-stat-card label="Total Transaksi" value="{{ number_format($customer->total_transactions) }}" color="blue"/>
    <x-stat-card label="Total Belanja" value="Rp {{ number_format($customer->total_spending,0,',','.') }}" color="emerald"/>
    <x-stat-card label="Poin Loyalty" value="{{ number_format($customer->loyalty_points) }} poin" color="orange"/>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div>
        <x-card title="Info Pelanggan">
            <dl class="space-y-3 text-sm">
                <div><dt class="text-xs text-gray-500 font-medium uppercase">Nama</dt><dd class="font-semibold text-gray-900 mt-0.5">{{ $customer->name }}</dd></div>
                <div><dt class="text-xs text-gray-500 font-medium uppercase">No. HP</dt><dd class="text-gray-700 mt-0.5">{{ $customer->phone ?: '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500 font-medium uppercase">Email</dt><dd class="text-gray-700 mt-0.5">{{ $customer->email ?: '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500 font-medium uppercase">Bergabung</dt><dd class="text-gray-700 mt-0.5">{{ $customer->created_at->format('d M Y') }}</dd></div>
            </dl>
        </x-card>

        @if($customer->points->count())
        <x-card title="Riwayat Poin" class="mt-4">
            <div class="space-y-2 max-h-60 overflow-y-auto">
                @foreach($customer->points->sortByDesc('created_at')->take(20) as $pt)
                <div class="flex items-center justify-between text-sm py-1.5 border-b border-gray-50 last:border-0">
                    <div>
                        <p class="text-xs text-gray-600">{{ $pt->description ?: ucfirst($pt->type) }}</p>
                        <p class="text-xs text-gray-400">{{ $pt->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                    <span class="font-bold {{ $pt->type === 'redeem' ? 'text-red-600' : 'text-emerald-600' }}">
                        {{ $pt->type === 'redeem' ? '-' : '+' }}{{ number_format($pt->points) }}
                    </span>
                </div>
                @endforeach
            </div>
        </x-card>
        @endif
    </div>

    <div class="lg:col-span-2">
        <x-card title="Riwayat Transaksi" :padding="false">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100">
                        <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase">No. Order</th>
                        <th class="text-left px-3 py-2.5 text-xs font-semibold text-gray-500 uppercase">Pembayaran</th>
                        <th class="text-left px-3 py-2.5 text-xs font-semibold text-gray-500 uppercase">Status</th>
                        <th class="text-right px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase">Total</th>
                        <th class="text-right px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase">Tanggal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($customer->orders->sortByDesc('created_at') as $order)
                    @php $sc=['paid'=>'green','cancelled'=>'red','refunded'=>'blue','draft'=>'yellow']; @endphp
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-5 py-3">
                            <a href="{{ route('orders.show', $order) }}" class="font-mono text-xs text-emerald-700 hover:underline font-semibold">
                                {{ $order->order_number }}
                            </a>
                        </td>
                        <td class="px-3 py-3 capitalize text-gray-600">{{ $order->payment_method }}</td>
                        <td class="px-3 py-3">
                            <x-badge :color="$sc[$order->status]??'gray'">{{ ucfirst($order->status) }}</x-badge>
                        </td>
                        <td class="px-5 py-3 text-right font-semibold text-gray-900">Rp {{ number_format($order->grand_total,0,',','.') }}</td>
                        <td class="px-5 py-3 text-right text-gray-500 text-xs">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-gray-400">Belum ada transaksi</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-card>
    </div>
</div>
@endsection
