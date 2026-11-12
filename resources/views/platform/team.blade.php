@extends('layouts.platform')
@section('title', 'Tim Yoka')

@section('content')
@php $input = 'w-full border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-emerald-600 focus:outline-none'; $roles = ['super_admin' => 'Super admin', 'support' => 'Support', 'finance' => 'Finance']; @endphp
<div class="mb-7">
    <h1 class="text-3xl font-black">Tim Yoka</h1>
    <p class="mt-1 text-sm text-slate-500">Akun yang bisa masuk ke platform. Super admin: semua akses · Support: toko dan bantuan · Finance: penarikan dan keuangan.</p>
</div>

@error('team')<div class="mb-5 border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>@enderror

@if($tempPassword)
<section class="mb-6 border-2 border-amber-400 bg-amber-50 p-5">
    <p class="font-black text-amber-900">Password sementara untuk {{ $tempEmail }}</p>
    <p class="mt-2 inline-block bg-white px-4 py-2 font-mono text-lg font-bold tracking-wide">{{ $tempPassword }}</p>
    <p class="mt-2 text-sm text-amber-900">Hanya tampil sekali. Berikan lewat jalur aman dan minta orangnya segera menggantinya di menu Akun Saya.</p>
</section>
@endif

<section class="mb-6 overflow-x-auto border border-slate-200 bg-white">
    <table class="w-full min-w-[720px] text-sm">
        <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-3">Nama</th><th class="px-5 py-3">Peran</th><th class="px-5 py-3">Terakhir masuk</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Aksi</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @foreach($admins as $a)
            <tr>
                <td class="px-5 py-3.5"><p class="font-bold">{{ $a->name }}@if($a->is($me)) <span class="ml-1 bg-slate-100 px-1.5 py-0.5 text-[12px] font-bold text-slate-500">Kamu</span>@endif</p><p class="text-slate-500">{{ $a->email }}</p></td>
                <td class="px-5 py-3.5">
                    @if($a->is($me))<span class="font-semibold">{{ $roles[$a->role] }}</span>
                    @else
                    <form method="POST" action="{{ route('platform.team.role', $a) }}" class="flex items-center gap-2">@csrf @method('PATCH')
                        <select name="role" class="border border-slate-300 bg-white px-2 py-1.5 text-sm" onchange="this.form.submit()">@foreach($roles as $k => $l)<option value="{{ $k }}" @selected($a->role === $k)>{{ $l }}</option>@endforeach</select>
                    </form>
                    @endif
                </td>
                <td class="px-5 py-3.5 text-slate-500">{{ $a->last_login_at?->diffForHumans() ?? 'Belum pernah' }}</td>
                <td class="px-5 py-3.5"><span class="px-2 py-0.5 text-[12px] font-bold {{ $a->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' }}">{{ $a->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                <td class="px-5 py-3.5 text-right">
                    @unless($a->is($me))
                    <form method="POST" action="{{ route('platform.team.toggle', $a) }}" onsubmit="return confirm('{{ $a->is_active ? 'Nonaktifkan' : 'Aktifkan' }} akun {{ $a->email }}?')">@csrf @method('PATCH')
                        <button class="border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold hover:bg-slate-50">{{ $a->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                    </form>
                    @endunless
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</section>

<section class="border border-slate-200 bg-white p-6">
    <h2 class="text-lg font-black">Tambah anggota tim</h2>
    <p class="mt-1 text-sm text-slate-500">Password sementara dibuat otomatis. Kode 2FA dikirim ke email anggota saat masuk.</p>
    <form method="POST" action="{{ route('platform.team.store') }}" class="mt-5 grid gap-4 md:grid-cols-[1fr_1fr_180px_auto] md:items-end">@csrf
        <div><label class="mb-1.5 block text-sm font-bold">Nama</label><input name="name" value="{{ old('name') }}" required class="{{ $input }}">@error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
        <div><label class="mb-1.5 block text-sm font-bold">Email</label><input type="email" name="email" value="{{ old('email') }}" required class="{{ $input }}">@error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
        <div><label class="mb-1.5 block text-sm font-bold">Peran</label><select name="role" class="{{ $input }}">@foreach($roles as $k => $l)<option value="{{ $k }}" @selected($k === 'support')>{{ $l }}</option>@endforeach</select></div>
        <button class="bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-800">Tambah</button>
    </form>
</section>
@endsection
