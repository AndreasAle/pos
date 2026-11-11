@extends('layouts.auth-split')
@section('title', 'Lupa Password')

@section('left-title', 'Lupa Password? Tenang.')
@section('left-subtitle', 'Masukkan email akun Anda. Kami kirim tautan untuk membuat password baru.')

@section('content')
<h3 class="text-xl font-bold text-gray-900 mb-1 text-center lg:text-left">Atur Ulang Password</h3>
<p class="text-sm text-gray-500 mb-6 text-center lg:text-left">Tautan akan dikirim ke email yang Anda pakai saat mendaftar.</p>

<form method="POST" action="{{ route('password.email') }}" class="space-y-4">
    @csrf
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
        <input type="email" name="email" value="{{ old('email') }}" required autofocus
               class="w-full px-3 border border-gray-300 text-sm" placeholder="email@bisnis.com">
    </div>
    <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-4 rounded-full text-[15px] transition-colors">
        Kirim Tautan
    </button>
</form>

<p class="mt-5 text-center text-sm text-gray-500">
    Ingat password?
    <a href="{{ route('login') }}" class="text-emerald-700 font-bold hover:underline">Masuk</a>
</p>
@endsection
