@extends('layouts.platform')
@section('title', 'Sistem & Integrasi')

@section('content')
@php
    $tone = ['ok' => ['bg-emerald-100 text-emerald-800', 'OK'], 'warn' => ['bg-amber-100 text-amber-800', 'Perhatian'], 'fail' => ['bg-red-100 text-red-800', 'Gagal'], 'info' => ['bg-slate-100 text-slate-600', 'Info']];
    $overall = $summary['fail'] ? 'fail' : ($summary['warn'] ? 'warn' : 'ok');
@endphp
<div class="mb-7 flex flex-col justify-between gap-4 md:flex-row md:items-end">
    <div>
        <h1 class="text-3xl font-black">Sistem &amp; Integrasi</h1>
        <p class="mt-1 text-sm text-slate-500">Satu layar untuk memeriksa aplikasi, database, cron, email, Telegram, AI, Google, dan keamanan. Diperiksa {{ $checkedAt->format('d M Y H:i:s') }}.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('platform.system') }}" class="border border-slate-300 bg-white px-4 py-2 text-sm font-bold hover:bg-slate-50">Periksa ulang</a>
        <form method="POST" action="{{ route('platform.system.test-mail') }}" onsubmit="return confirm('Kirim email tes ke {{ auth('platform')->user()->email }}?')">@csrf
            <button class="bg-emerald-700 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-800">Kirim email tes ke saya</button>
        </form>
    </div>
</div>

@error('mail')<div class="mb-5 border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>@enderror

<div class="mb-7 grid gap-3 sm:grid-cols-4">
    <div class="border-2 p-5 {{ $overall === 'ok' ? 'border-emerald-500 bg-emerald-50' : ($overall === 'warn' ? 'border-amber-400 bg-amber-50' : 'border-red-400 bg-red-50') }}">
        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Status keseluruhan</p>
        <p class="mt-1 text-2xl font-black">{{ $overall === 'ok' ? 'Sehat' : ($overall === 'warn' ? 'Perlu perhatian' : 'Ada yang gagal') }}</p>
    </div>
    @foreach(['ok' => 'OK', 'warn' => 'Perhatian', 'fail' => 'Gagal'] as $k => $label)
    <div class="border border-slate-200 bg-white p-5"><p class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $label }}</p><p class="mt-1 text-3xl font-black">{{ $summary[$k] }}</p></div>
    @endforeach
</div>

<div class="space-y-6">
@foreach($report as $group)
    <section class="border border-slate-200 bg-white">
        <h2 class="border-b border-slate-200 px-5 py-3.5 text-sm font-black uppercase tracking-wider text-slate-700">{{ $group['title'] }}</h2>
        <ul class="divide-y divide-slate-100">
            @foreach($group['checks'] as $c)
            <li class="flex flex-col gap-1.5 px-5 py-3.5 sm:flex-row sm:items-start sm:gap-4">
                <span class="w-24 shrink-0"><span class="inline-block px-2 py-0.5 text-[12px] font-bold {{ $tone[$c['status']][0] }}">{{ $tone[$c['status']][1] }}</span></span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-slate-900">{{ $c['name'] }}</p>
                    <p class="break-words text-sm text-slate-600">{{ $c['detail'] }}</p>
                    @if(!empty($c['hint']))<p class="mt-0.5 text-[13px] {{ in_array($c['status'], ['warn','fail']) ? 'font-semibold text-amber-700' : 'text-slate-400' }}">{{ $c['hint'] }}</p>@endif
                </div>
            </li>
            @endforeach
        </ul>
    </section>
@endforeach
</div>

<p class="mt-6 text-xs text-slate-400">Halaman ini hanya menampilkan status. Nilai rahasia (kunci, password, token) tidak pernah ditampilkan. Setara dengan <code class="bg-slate-100 px-1">php artisan yoka:health</code> di server.</p>
@endsection
