@extends('layouts.app')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
@php $showSummaryOnPhone = request()->boolean('ringkasan'); @endphp

{{-- Layar kecil (HP): daftar menu ala aplikasi chat. Baris "Ringkasan Hari Ini" membuka dashboard lengkap. --}}
<div class="{{ $showSummaryOnPhone ? 'hidden' : '' }} lg:hidden">
    @include('dashboard._home')
</div>

{{-- Desktop: dashboard lengkap (di HP hanya tampil lewat ?ringkasan=1) --}}
<div class="{{ $showSummaryOnPhone ? '' : 'hidden' }} lg:block">
    @include('dashboard._summary')
</div>
@endsection
