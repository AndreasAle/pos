@extends('layouts.app')
@section('title','Integrasi')
@section('page-title','Integrasi')

@section('content')
@php
    $statusTone  = ['pending' => 'amber', 'connected' => 'green', 'disconnected' => 'gray'];
    $statusLabel = ['pending' => 'Menunggu aktivasi', 'connected' => 'Tersambung', 'disconnected' => 'Terputus'];
    $consentItems = [
        ['Library menu', 'Item, kategori, diskon, modifier, dan pajak dikirim ke platform.'],
        ['Akses pesanan', 'Pesanan dari platform dibuat, dibaca, dan diperbarui di Yoka POS.'],
        ['Harga per tipe penjualan', 'Harga menu di platform boleh berbeda dari harga di kasir.'],
    ];
@endphp

<div class="page text-[#0B1B3F]" x-data="{ open: null, outlet: '' }">
    <p class="fade-up mb-5 text-sm text-[#64748B]">Hubungkan outlet ke GoFood dan ShopeeFood supaya menu sinkron dan pesanan masuk ke kasir.</p>

    @if($outlets->isEmpty())
    <x-panel>
        <x-empty title="Belum ada outlet" text="Tambahkan outlet dulu sebelum menghubungkan GoFood atau ShopeeFood." />
    </x-panel>
    @else
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        @foreach($providers as $key => $p)
        @php $rows = $links->get($key, collect()); @endphp
        <x-panel class="fade-up" style="--i:{{ $loop->index }}">
            <div class="flex items-start justify-between gap-4">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $p['color'] }} text-lg font-bold text-white">{{ substr($p['name'], 0, 1) }}</span>
                    <div class="min-w-0">
                        <h3 class="text-base font-semibold">{{ $p['name'] }}</h3>
                        <p class="text-sm text-[#64748B]">Pesan antar online</p>
                    </div>
                </div>
                <x-btn size="sm" @click="open = '{{ $key }}'; outlet = ''">Hubungkan</x-btn>
            </div>

            <p class="mt-4 text-sm text-[#64748B]">Menu sinkron otomatis dan pesanan {{ $p['name'] }} masuk langsung ke Yoka POS, tanpa input manual.</p>

            <div class="mt-4 divide-y divide-[#E5E7EB] rounded-lg border border-[#E5E7EB]">
                @forelse($rows as $row)
                <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold">{{ $row->outlet->name ?? '-' }}</p>
                        <p class="truncate text-[13px] text-[#64748B]">{{ $row->merchant_name ? $row->merchant_name . ' · ' : '' }}{{ $row->merchant_id }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-pill :tone="$statusTone[$row->status] ?? 'gray'">{{ $statusLabel[$row->status] ?? $row->status }}</x-pill>
                        <form method="POST" action="{{ route('integrations.disconnect', $row) }}" onsubmit="return confirm('Putuskan integrasi ini?')">
                            @csrf @method('DELETE')
                            <x-btn type="submit" size="sm" variant="danger-outline">Putuskan</x-btn>
                        </form>
                    </div>
                </div>
                @empty
                <p class="px-4 py-4 text-sm text-[#64748B]">Belum ada outlet yang terhubung.</p>
                @endforelse
            </div>
        </x-panel>
        @endforeach
    </div>

    <p class="mt-4 text-[13px] text-[#64748B]">Sambungan data langsung ke platform diaktifkan oleh tim Yoka setelah izin partner dari GoFood dan ShopeeFood keluar. Sampai saat itu, outlet yang kamu daftarkan berstatus menunggu aktivasi.</p>

    {{-- Modal consent + penautan outlet --}}
    @foreach($providers as $key => $p)
    <div x-show="open === '{{ $key }}'" x-cloak x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B1B3F]/50 p-4"
         @keydown.escape.window="open = null" @click.self="open = null">
        <form method="POST" action="{{ route('integrations.connect', $key) }}"
              class="w-full max-w-lg overflow-hidden rounded-xl border border-[#E5E7EB] bg-white shadow-xl">
            @csrf
            <div class="bg-[#0B1B3F] px-5 py-4 text-white">
                <h3 class="text-base font-semibold">Hubungkan Yoka dengan {{ $p['name'] }}</h3>
            </div>

            <div class="space-y-4 p-5">
                <div>
                    <label class="mb-1 block text-sm font-semibold">Outlet Yoka</label>
                    <select name="outlet_id" required class="h-10 w-full rounded-lg border border-[#E5E7EB] bg-white px-3 text-sm">
                        <option value="">Pilih outlet</option>
                        @foreach($outlets as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold">{{ $p['id_label'] }}</label>
                    <input name="merchant_id" required maxlength="100" placeholder="{{ $p['id_hint'] }}" class="h-10 w-full rounded-lg border border-[#E5E7EB] px-3 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold">Nama toko di {{ $p['name'] }} <span class="font-normal text-[#64748B]">(opsional)</span></label>
                    <input name="merchant_name" maxlength="150" class="h-10 w-full rounded-lg border border-[#E5E7EB] px-3 text-sm">
                </div>

                <div class="rounded-lg border border-[#E5E7EB] bg-slate-50 p-3">
                    <p class="text-sm font-semibold">Saya mengizinkan Yoka mengirim dan menerima:</p>
                    <ul class="mt-2 space-y-1.5">
                        @foreach($consentItems as [$t, $d])
                        <li class="text-[13px]"><span class="font-semibold">{{ $t }}.</span> <span class="text-[#64748B]">{{ $d }}</span></li>
                        @endforeach
                    </ul>
                </div>

                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="consent" value="1" required class="mt-0.5 h-4 w-4 rounded border-[#E5E7EB] accent-[#16A34A]">
                    <span>Saya setuju data di atas dibagikan ke {{ $p['name'] }}.</span>
                </label>
            </div>

            <div class="flex justify-end gap-2 border-t border-[#E5E7EB] px-5 py-3">
                <x-btn variant="secondary" @click="open = null">Batalkan</x-btn>
                <x-btn type="submit">Setuju dan hubungkan</x-btn>
            </div>
        </form>
    </div>
    @endforeach
    @endif
</div>
@endsection
