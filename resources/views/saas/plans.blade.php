@extends('layouts.app')
@section('title', 'Paket Langganan')
@section('page-title', 'Paket Langganan')

@section('content')
<div class="flex items-center justify-center px-6 py-16" style="min-height: calc(100vh - 11rem)">
    <section class="w-full max-w-xl text-center" aria-labelledby="construction-title">
        <img
            src="{{ asset('images/brand/yoka-icon.png') }}"
            alt="Yoka POS"
            class="mx-auto object-contain"
            style="width: 7rem; height: 7rem"
        >

        <p class="mt-8 text-xs font-bold uppercase text-[#0B8F5B]" style="letter-spacing: .28em">Yoka POS</p>
        <h2 id="construction-title" class="mt-3 text-3xl font-bold tracking-tight text-[#0B1B3F] sm:text-4xl">
            Under Construction
        </h2>
        <p class="mx-auto mt-4 max-w-md text-sm leading-6 text-slate-500 sm:text-base">
            Halaman paket langganan sedang kami siapkan. Fitur utama Yoka POS tetap dapat digunakan seperti biasa.
        </p>

        <a href="{{ route('dashboard') }}" class="mt-8 inline-flex h-11 items-center justify-center border border-[#0B8F5B] px-6 text-sm font-semibold text-[#0B8F5B] transition-colors hover:bg-[#0B8F5B] hover:text-white">
            Kembali ke Dashboard
        </a>
    </section>
</div>
@endsection
