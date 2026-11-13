@extends('layouts.app')
@section('title','Pergerakan Stok')
@section('page-title','Pergerakan Stok')

@section('content')
<div class="page text-[#0B1B3F] pt-2">

    <div class="fade-up flex flex-wrap items-center justify-between gap-3 mb-4" style="--i:0">
        @include('inventory._tabs', ['active' => 'movements'])
        <span class="inline-flex items-center gap-1.5 h-8 px-3 rounded-full bg-[#ECFDF3] text-[#15803D] text-sm font-semibold">
            <span class="w-1.5 h-1.5 rounded-full bg-[#16A34A]"></span>{{ number_format($movements->total()) }} catatan
        </span>
    </div>

    <section class="fade-up rounded-xl border border-[#E5E7EB] bg-white overflow-hidden" style="--i:1">
        @if($movements->count())
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap">
                <thead>
                    <tr class="bg-slate-50 border-b border-[#E5E7EB]">
                        <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Bahan</th>
                        <th class="text-left px-4 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Tipe</th>
                        <th class="text-right px-4 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Qty</th>
                        <th class="text-right px-4 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Sebelum</th>
                        <th class="text-right px-4 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Sesudah</th>
                        <th class="text-left px-4 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">User</th>
                        <th class="text-left px-4 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Catatan</th>
                        <th class="text-right px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Waktu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E5E7EB]">
                    @foreach($movements as $mv)
                    <tr class="hover:bg-[#ECFDF3]/50 transition-colors">
                        <td class="px-6 py-4 font-semibold">{{ $mv->ingredient->name }}</td>
                        <td class="px-4 py-4"><x-movement-type :type="$mv->type" /></td>
                        <td class="px-4 py-4 text-right font-bold tabular-nums {{ $mv->qty >= 0 ? 'text-[#15803D]' : 'text-red-600' }}">{{ $mv->qty >= 0 ? '+' : '' }}{{ number_format($mv->qty, 3) }}</td>
                        <td class="px-4 py-4 text-right tabular-nums text-[#64748B]">{{ number_format($mv->stock_before, 3) }}</td>
                        <td class="px-4 py-4 text-right font-semibold tabular-nums">{{ number_format($mv->stock_after, 3) }}</td>
                        <td class="px-4 py-4">
                            <span class="flex items-center gap-2 text-slate-700">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-[#ECFDF3] text-[11px] font-bold text-[#16A34A]">{{ strtoupper(mb_substr($mv->user->name ?? '-', 0, 1)) }}</span>
                                {{ $mv->user->name ?? '-' }}
                            </span>
                        </td>
                        <td class="max-w-[14rem] truncate px-4 py-4 text-xs text-[#64748B]">{{ $mv->notes ?: '-' }}</td>
                        <td class="px-6 py-4 text-right text-xs text-[#64748B]">{{ $mv->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <ul class="md:hidden divide-y divide-[#E5E7EB]">
            @foreach($movements as $mv)
            <li class="px-5 py-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate font-semibold">{{ $mv->ingredient->name }}</p>
                        <p class="mt-0.5 text-xs text-[#64748B]">{{ $mv->user->name ?? '-' }} &middot; {{ $mv->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                    <x-movement-type :type="$mv->type" />
                </div>
                <p class="mt-2 text-sm">
                    <span class="font-bold tabular-nums {{ $mv->qty >= 0 ? 'text-[#15803D]' : 'text-red-600' }}">{{ $mv->qty >= 0 ? '+' : '' }}{{ number_format($mv->qty, 3) }}</span>
                    <span class="text-xs text-[#64748B]">&nbsp;({{ number_format($mv->stock_before, 3) }} &rarr; {{ number_format($mv->stock_after, 3) }})</span>
                </p>
                @if($mv->notes)<p class="mt-1 text-xs text-[#64748B]">{{ $mv->notes }}</p>@endif
            </li>
            @endforeach
        </ul>
        @else
        <x-empty title="Belum ada pergerakan stok" text="Setiap stok masuk, penjualan, dan penyesuaian akan tercatat di sini." icon="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
        @endif
    </section>

    <div class="mt-5">{{ $movements->links() }}</div>
</div>
@endsection
