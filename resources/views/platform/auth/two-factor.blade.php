@extends('platform.auth._shell', ['step' => 2])
@section('title', 'Verifikasi Tim Yoka')

@section('card')
<div class="flex items-center gap-2 text-[12px] font-bold uppercase tracking-[0.18em] text-[#0A5C3A]">
    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l9 6 9-6M5 5h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/></svg>
    Verifikasi dua langkah
</div>
<h2 class="mt-3 text-[30px] font-bold leading-tight tracking-tight">Periksa email kamu</h2>
<p class="mt-2 text-[15px] leading-relaxed text-slate-500">Kode 6 digit dikirim ke <strong class="font-semibold text-slate-800">{{ $admin->email }}</strong>. Kode berlaku 10 menit. Belum ada? Cek folder spam.</p>

@if(session('success'))<div class="mt-5 rounded-xl border border-[#16A34A]/25 bg-[#ECFDF3] px-4 py-3 text-[14px] font-semibold text-[#066B45]">{{ session('success') }}</div>@endif
@if($errors->any())<div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-[14px] font-semibold text-red-700" role="alert">{{ $errors->first() }}</div>@endif

<form method="POST" action="{{ route('platform.two-factor.verify') }}" class="mt-7" x-data="{ busy: false }" @submit="busy = true">
    @csrf
    <label for="code" class="mb-2 block text-[14px] font-semibold text-slate-700">Kode keamanan</label>
    <input id="code" type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus autocomplete="one-time-code" placeholder="000000"
           class="h-16 w-full rounded-xl border border-[#E5E7EB] bg-white text-center font-mono text-[30px] font-bold tracking-[0.4em] text-[#0B1B3F] placeholder:text-slate-300 transition focus:border-[#16A34A] focus:outline-none focus:ring-4 focus:ring-[#16A34A]/10">
    <button type="submit" :disabled="busy" class="mt-5 inline-flex h-12 w-full items-center justify-center rounded-xl bg-[#0A5C3A] text-[15px] font-bold text-white shadow-sm transition hover:bg-[#08482E] disabled:opacity-70">
        <span x-text="busy ? 'Memverifikasi…' : 'Verifikasi & masuk'"></span>
    </button>
</form>

<div class="mt-5 flex items-center justify-between text-[13px]" x-data="{ left: 45, t: null }" x-init="t = setInterval(() => { if (--left <= 0) clearInterval(t) }, 1000)">
    <form method="POST" action="{{ route('platform.two-factor.resend') }}">@csrf
        <button type="submit" :disabled="left > 0" class="font-semibold text-[#0A5C3A] hover:underline disabled:cursor-not-allowed disabled:text-slate-400 disabled:no-underline">
            <span x-text="left > 0 ? 'Kirim ulang kode (' + left + 's)' : 'Kirim ulang kode'"></span>
        </button>
    </form>
    <form method="POST" action="{{ route('platform.logout') }}">@csrf<button type="submit" class="font-semibold text-slate-500 hover:text-slate-800">Batalkan</button></form>
</div>
@endsection

@section('footer')
Kode salah lebih dari 5 kali mengharuskan kamu masuk ulang.
@endsection
