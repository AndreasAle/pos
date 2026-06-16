@extends('layouts.app')
@section('title','Paket Langganan')
@section('page-title','Paket Langganan')
@section('content')
@if($current)
<div class="mb-5 bg-emerald-50 border border-emerald-200 rounded-2xl p-4 flex items-center justify-between">
    <div>
        <p class="text-sm font-semibold text-emerald-800">
            Paket Aktif: <span class="font-bold">{{ $current->plan->name }}</span>
            <x-badge :color="$current->status === 'trial' ? 'yellow' : 'green'" class="ml-2">{{ ucfirst($current->status) }}</x-badge>
        </p>
        @if($current->ends_at)
        <p class="text-xs text-emerald-600 mt-0.5">
            Berlaku hingga {{ $current->ends_at->format('d M Y') }}
            ({{ $current->ends_at->diffForHumans() }})
        </p>
        @endif
    </div>
    <a href="{{ route('saas.current') }}" class="text-xs font-medium text-emerald-700 hover:underline">Riwayat Langganan →</a>
</div>
@endif

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
    @foreach($plans as $plan)
    @php $isCurrent = $current && $current->subscription_plan_id === $plan->id && $current->isActive(); @endphp
    <div class="bg-white rounded-2xl border-2 {{ $isCurrent ? 'border-emerald-500' : 'border-gray-200' }} shadow-sm overflow-hidden relative">
        @if($isCurrent)
        <div class="absolute top-3 right-3">
            <x-badge color="green">Paket Aktif</x-badge>
        </div>
        @endif
        @if($plan->slug === 'business')
        <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-500 to-blue-500"></div>
        @endif

        <div class="p-5">
            <h3 class="text-lg font-bold text-gray-900">{{ $plan->name }}</h3>
            <p class="mt-1 text-3xl font-extrabold text-gray-900">
                @if($plan->price == 0)
                <span class="text-emerald-600">Gratis</span>
                @else
                Rp {{ number_format($plan->price,0,',','.') }}
                @endif
            </p>
            @if($plan->price > 0)
            <p class="text-xs text-gray-500">/bulan</p>
            @endif

            <ul class="mt-4 space-y-2 text-sm text-gray-600">
                <li class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                    {{ $plan->max_outlets == 999 ? 'Unlimited' : $plan->max_outlets }} outlet
                </li>
                <li class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                    {{ $plan->max_users == 999 ? 'Unlimited' : $plan->max_users }} user
                </li>
                @foreach($plan->features ?? [] as $feature)
                <li class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                    {{ Str::title(str_replace('_', ' ', $feature)) }}
                </li>
                @endforeach
            </ul>
        </div>

        <div class="px-5 pb-5">
            @if($isCurrent)
            <div class="w-full text-center text-sm font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 py-2.5 rounded-xl">
                ✓ Paket Saat Ini
            </div>
            @else
            <form method="POST" action="{{ route('saas.subscribe', $plan) }}">
                @csrf
                <button type="submit"
                        class="w-full text-sm font-semibold {{ $plan->slug === 'free' ? 'text-gray-700 bg-gray-100 hover:bg-gray-200' : 'text-white bg-emerald-600 hover:bg-emerald-700' }} py-2.5 rounded-xl transition-colors">
                    {{ $plan->price == 0 ? 'Pilih Paket Ini' : 'Berlangganan' }}
                </button>
            </form>
            @endif
        </div>
    </div>
    @endforeach
</div>

<div class="mt-6 bg-blue-50 border border-blue-200 rounded-2xl p-4 text-sm text-blue-700">
    <p class="font-semibold mb-1">ℹ️ Informasi Pembayaran</p>
    <p>Saat ini pembayaran dilakukan secara manual. Hubungi tim kami untuk mengaktifkan paket berbayar. Setelah konfirmasi pembayaran, akun Anda akan diupgrade otomatis.</p>
</div>
@endsection
