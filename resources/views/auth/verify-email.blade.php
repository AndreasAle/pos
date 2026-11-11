@extends('layouts.auth-split')
@section('title', 'Konfirmasi Email')

@section('left-title', 'Satu Langkah Lagi')
@section('left-subtitle', 'Kami mengirim tautan konfirmasi ke email Anda. Setelah dikonfirmasi, kasir langsung bisa dipakai.')

@section('content')
<div class="text-center lg:text-left">
    <div class="w-14 h-14 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center mx-auto lg:mx-0 mb-5">
        <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l9 6 9-6M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
    </div>
    <h3 class="text-2xl font-extrabold text-slate-900 mb-2">Cek email Anda</h3>
    <p class="text-[15px] text-slate-600 leading-relaxed mb-1">Tautan konfirmasi dikirim ke</p>
    <p class="text-[15px] font-bold text-slate-900 mb-4 break-all">{{ $email }}</p>
    <p class="text-sm text-slate-500 mb-6">Belum ada? Cek folder spam, atau kirim ulang tautannya.</p>
</div>

<form method="POST" action="{{ route('verification.send') }}">
    @csrf
    <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-4 rounded-full text-[15px] transition-colors">
        Kirim Ulang Email
    </button>
</form>

<form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
    @csrf
    <button type="submit" class="text-sm text-slate-500 hover:text-slate-700">Salah email? <span class="font-bold text-emerald-700">Keluar dan daftar lagi</span></button>
</form>
@endsection
