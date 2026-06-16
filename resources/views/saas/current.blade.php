@extends('layouts.app')
@section('title','Status Langganan')
@section('page-title','Status Langganan')
@section('content')
<div class="flex items-center justify-between mb-5">
    <a href="{{ route('saas.plans') }}" class="text-sm font-medium text-emerald-600 border border-emerald-200 hover:bg-emerald-50 px-4 py-2 rounded-xl">
        Lihat Semua Paket →
    </a>
</div>

@if($current)
<div class="bg-white rounded-2xl border-2 border-emerald-400 shadow-sm p-5 mb-5 max-w-lg">
    <div class="flex items-start justify-between">
        <div>
            <p class="text-xs text-gray-500 uppercase font-medium tracking-wide">Paket Aktif</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $current->plan->name }}</p>
        </div>
        <x-badge :color="$current->status === 'trial' ? 'yellow' : ($current->status === 'active' ? 'green' : 'red')">
            {{ ucfirst($current->status) }}
        </x-badge>
    </div>
    <div class="grid grid-cols-2 gap-4 mt-4 text-sm">
        <div>
            <p class="text-gray-500 text-xs">Mulai</p>
            <p class="font-semibold text-gray-900">{{ $current->starts_at->format('d M Y') }}</p>
        </div>
        <div>
            <p class="text-gray-500 text-xs">Berakhir</p>
            <p class="font-semibold {{ $current->ends_at && $current->ends_at->isPast() ? 'text-red-600' : 'text-gray-900' }}">
                {{ $current->ends_at?->format('d M Y') ?? 'Tidak terbatas' }}
            </p>
        </div>
        <div>
            <p class="text-gray-500 text-xs">Max Outlet</p>
            <p class="font-semibold text-gray-900">{{ $current->plan->max_outlets == 999 ? 'Unlimited' : $current->plan->max_outlets }}</p>
        </div>
        <div>
            <p class="text-gray-500 text-xs">Max User</p>
            <p class="font-semibold text-gray-900">{{ $current->plan->max_users == 999 ? 'Unlimited' : $current->plan->max_users }}</p>
        </div>
    </div>
    @if($current->ends_at && $current->ends_at->diffInDays(now()) <= 7 && !$current->ends_at->isPast())
    <div class="mt-4 bg-yellow-50 border border-yellow-200 rounded-xl p-3 text-xs text-yellow-800 font-medium">
        ⚠ Langganan akan berakhir {{ $current->ends_at->diffForHumans() }}. Segera perpanjang!
    </div>
    @endif
</div>
@else
<div class="bg-orange-50 border border-orange-200 rounded-2xl p-5 max-w-lg mb-5 text-sm text-orange-700">
    <p class="font-semibold mb-1">Tidak ada langganan aktif</p>
    <p>Pilih paket langganan untuk terus menggunakan fitur penuh aplikasi.</p>
    <a href="{{ route('saas.plans') }}" class="inline-block mt-3 text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 px-4 py-2 rounded-xl">
        Pilih Paket
    </a>
</div>
@endif

{{-- Subscription History --}}
<x-card title="Riwayat Langganan" :padding="false">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-50 border-b border-gray-100">
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Paket</th>
                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-500 uppercase">Mulai</th>
                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-500 uppercase">Berakhir</th>
                <th class="text-center px-3 py-3 text-xs font-semibold text-gray-500 uppercase">Status</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Harga</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($subs as $sub)
            @php $sc = ['trial'=>'yellow','active'=>'green','expired'=>'gray','cancelled'=>'red']; @endphp
            <tr class="hover:bg-gray-50/50">
                <td class="px-5 py-3 font-semibold text-gray-900">{{ $sub->plan->name }}</td>
                <td class="px-3 py-3 text-gray-600">{{ $sub->starts_at->format('d M Y') }}</td>
                <td class="px-3 py-3 text-gray-600">{{ $sub->ends_at?->format('d M Y') ?? '∞' }}</td>
                <td class="px-3 py-3 text-center"><x-badge :color="$sc[$sub->status]??'gray'">{{ ucfirst($sub->status) }}</x-badge></td>
                <td class="px-5 py-3 text-right font-semibold {{ $sub->plan->price > 0 ? 'text-gray-900' : 'text-emerald-600' }}">
                    {{ $sub->plan->price > 0 ? 'Rp '.number_format($sub->plan->price,0,',','.') : 'Gratis' }}
                </td>
            </tr>
            @empty
            <tr><td colspan="5" class="px-5 py-10 text-center text-sm text-gray-400">Tidak ada riwayat</td></tr>
            @endforelse
        </tbody>
    </table>
</x-card>
<div class="mt-4">{{ $subs->links() }}</div>
@endsection
