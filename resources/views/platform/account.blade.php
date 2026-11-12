@extends('layouts.platform')
@section('title', 'Akun Saya')

@section('content')
@php $input = 'w-full border border-slate-300 bg-white px-4 py-3 text-sm focus:border-emerald-600 focus:outline-none'; @endphp
<div class="mb-7">
    <h1 class="text-3xl font-black">Akun Saya</h1>
    <p class="mt-1 text-sm text-slate-500">{{ $admin->name }} · {{ $admin->email }} · {{ str_replace('_', ' ', $admin->role) }}</p>
</div>

@if($newCodes)
<section class="mb-6 border-2 border-amber-400 bg-amber-50 p-6">
    <h2 class="text-lg font-black text-amber-900">Simpan kode pemulihan ini sekarang</h2>
    <p class="mt-1 text-sm text-amber-900">Hanya tampil sekali. Tiap kode dipakai sekali untuk masuk bila email tidak sampai. Simpan di pengelola password atau tempat aman, jangan di chat.</p>
    <ul class="mt-4 grid grid-cols-2 gap-2 font-mono text-base font-bold sm:grid-cols-4">
        @foreach($newCodes as $code)<li class="bg-white px-3 py-2 text-center tracking-wider">{{ $code }}</li>@endforeach
    </ul>
</section>
@endif

<div class="grid gap-6 lg:grid-cols-2">
    <section class="border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-black">Ganti password</h2>
        <p class="mt-1 text-sm text-slate-500">Minimal 12 karakter dengan huruf dan angka.</p>
        <form method="POST" action="{{ route('platform.account.password') }}" class="mt-5 space-y-4">@csrf
            <div><label class="mb-1.5 block text-sm font-bold">Password saat ini</label><input type="password" name="current_password" required autocomplete="current-password" class="{{ $input }}">@error('current_password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
            <div><label class="mb-1.5 block text-sm font-bold">Password baru</label><input type="password" name="password" required autocomplete="new-password" class="{{ $input }}">@error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
            <div><label class="mb-1.5 block text-sm font-bold">Ulangi password baru</label><input type="password" name="password_confirmation" required autocomplete="new-password" class="{{ $input }}"></div>
            <button class="bg-emerald-700 px-5 py-3 text-sm font-bold text-white hover:bg-emerald-800">Simpan password</button>
        </form>
    </section>

    <section class="border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-black">Kode pemulihan 2FA</h2>
        <p class="mt-1 text-sm text-slate-500">Cadangan bila kode email tidak sampai. Tersisa <strong class="text-slate-900">{{ $remaining }}</strong> kode{{ $remaining === 0 ? ' — buat sekarang supaya tidak terkunci.' : '.' }}</p>
        <form method="POST" action="{{ route('platform.account.recovery') }}" class="mt-5 space-y-4" onsubmit="return confirm('Kode pemulihan lama akan tidak berlaku. Lanjutkan?')">@csrf
            <div><label class="mb-1.5 block text-sm font-bold">Password saat ini</label><input type="password" name="current_password" required autocomplete="current-password" class="{{ $input }}"></div>
            <button class="border border-slate-300 bg-white px-5 py-3 text-sm font-bold hover:bg-slate-50">{{ $remaining ? 'Buat ulang kode pemulihan' : 'Buat kode pemulihan' }}</button>
        </form>
    </section>
</div>
@endsection
