<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Siapkan Bisnis — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="icon" type="image/png" href="{{ asset('images/brand/yoka-icon.png') }}">
    <style>
        [x-cloak]{display:none!important}
        .field{height:3.25rem;width:100%;border-radius:.875rem;border:1px solid #cbd5e1;background:white;padding:0 1rem;font-size:.9375rem;color:#0f172a;transition:.15s}
        .field:focus{outline:none;border-color:#059669;box-shadow:0 0 0 4px rgba(5,150,105,.13)}
        textarea.field{height:6rem;padding-top:.85rem;resize:vertical}
    </style>
</head>
<body class="min-h-full bg-[#F2F8F5] font-sans text-slate-900">
<div class="min-h-screen lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(600px,760px)]">
    <aside class="relative hidden overflow-hidden bg-[#087443] p-12 text-white lg:flex lg:flex-col">
        <div class="absolute -right-24 -top-24 h-80 w-80 rounded-full border-[52px] border-white/5"></div>
        <div class="absolute -bottom-36 -left-24 h-[430px] w-[430px] rounded-full bg-[#0B8F55]"></div>
        <a href="{{ route('landing') }}" class="relative z-10 inline-flex items-center gap-2 self-start">
            <img src="{{ asset('images/brand/yoka-logo-white.png') }}" alt="Yoka" class="h-10 w-auto" onerror="this.src='{{ asset('images/brand/yoka-logo.png') }}'">
            <span class="text-2xl font-bold">POS</span>
        </a>

        <div class="relative z-10 my-auto max-w-xl py-16">
            <p class="text-sm font-bold uppercase tracking-[.18em] text-emerald-200">Langkah 2 dari 3</p>
            <h1 class="mt-5 text-5xl font-extrabold leading-[1.08] tracking-[-.04em]">Kenalkan bisnis Anda kepada Yoka.</h1>
            <p class="mt-6 max-w-lg text-lg leading-8 text-emerald-50/85">Kami akan menyiapkan ruang kerja, outlet utama, dan rekomendasi fitur berdasarkan jenis usaha Anda.</p>

            <div class="mt-10 grid gap-3">
                @foreach([
                    ['01', 'Akun terverifikasi', 'Email '.$user->email.' sudah aman.'],
                    ['02', 'Profil bisnis', 'Isi informasi singkat di sebelah kanan.'],
                    ['03', 'Mulai operasional', 'Ikuti panduan langsung di dashboard.'],
                ] as $step)
                <div class="flex items-center gap-4 rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur-sm">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-sm font-extrabold text-emerald-700">{{ $step[0] }}</span>
                    <div><p class="font-bold">{{ $step[1] }}</p><p class="text-sm text-emerald-50/70">{{ $step[2] }}</p></div>
                </div>
                @endforeach
            </div>
        </div>
        <p class="relative z-10 text-sm text-emerald-100/70">Data ini dapat diubah lagi melalui Pengaturan Bisnis.</p>
    </aside>

    <main class="flex min-h-screen items-center px-5 py-10 sm:px-10 lg:px-16">
        <div class="mx-auto w-full max-w-[620px]">
            <div class="mb-8 flex items-center justify-between lg:hidden">
                <a href="{{ route('landing') }}" class="inline-flex items-center gap-2">
                    <img src="{{ asset('images/brand/yoka-logo.png') }}" alt="Yoka" class="h-9 w-auto"><span class="text-xl font-bold text-emerald-700">POS</span>
                </a>
                <span class="rounded-full bg-emerald-100 px-3 py-1.5 text-xs font-bold text-emerald-700">Langkah 2/3</span>
            </div>

            <div class="mb-8">
                <div class="mb-5 flex gap-2"><span class="h-1.5 flex-1 rounded-full bg-emerald-600"></span><span class="h-1.5 flex-1 rounded-full bg-emerald-600"></span><span class="h-1.5 flex-1 rounded-full bg-slate-200"></span></div>
                <p class="text-sm font-bold text-emerald-700">Informasi bisnis</p>
                <h2 class="mt-2 text-3xl font-extrabold tracking-[-.03em] sm:text-4xl">Siapkan ruang kerja Anda</h2>
                <p class="mt-3 text-slate-500">Hanya butuh sekitar satu menit. Semua informasi bisa diperbarui nanti.</p>
            </div>

            @if($errors->any())
                <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700">
                    <p class="font-bold">Periksa kembali data berikut:</p>
                    <ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <form method="POST" action="{{ route('onboarding.business.store') }}" class="space-y-6" x-data="{ type: @js(old('business_type', 'fnb')) }">
                @csrf
                <fieldset>
                    <legend class="mb-3 text-sm font-bold text-slate-700">Apa jenis bisnis Anda?</legend>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        @foreach([
                            ['fnb', 'Makanan & Minuman', 'Kafe, restoran, bakery', 'M4 6h16M6 6l1 14h10l1-14M9 10v6m6-6v6'],
                            ['retail', 'Retail', 'Toko dan perdagangan', 'M3 9l2-5h14l2 5m-18 0h18v11H3V9zm6 11v-6h6v6'],
                            ['service', 'Jasa', 'Salon, studio, layanan', 'M12 6V4m0 16v-2m6-6h2M4 12H2m15.1-5.1 1.4-1.4M5.5 18.5l1.4-1.4m10.2 0 1.4 1.4M5.5 5.5l1.4 1.4M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
                        ] as $item)
                        <label class="cursor-pointer rounded-2xl border-2 p-4 transition" :class="type === '{{ $item[0] }}' ? 'border-emerald-600 bg-emerald-50 shadow-sm' : 'border-slate-200 bg-white hover:border-emerald-300'">
                            <input type="radio" name="business_type" value="{{ $item[0] }}" x-model="type" class="sr-only">
                            <svg class="h-6 w-6" :class="type === '{{ $item[0] }}' ? 'text-emerald-600' : 'text-slate-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item[3] }}"/></svg>
                            <p class="mt-3 text-sm font-bold">{{ $item[1] }}</p><p class="mt-1 text-xs leading-5 text-slate-500">{{ $item[2] }}</p>
                        </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="grid gap-5 sm:grid-cols-2">
                    <label class="block"><span class="mb-2 block text-sm font-bold text-slate-700">Jumlah outlet saat ini</span>
                        <select name="outlet_scale" required class="field"><option value="">Pilih jumlah outlet</option>@foreach(['1'=>'1 outlet','2-3'=>'2–3 outlet','4-10'=>'4–10 outlet','10+'=>'Lebih dari 10 outlet'] as $value=>$label)<option value="{{ $value }}" @selected(old('outlet_scale')===$value)>{{ $label }}</option>@endforeach</select>
                    </label>
                    <label class="block"><span class="mb-2 block text-sm font-bold text-slate-700">Kota pusat bisnis</span>
                        <input name="city" value="{{ old('city') }}" required list="city-options" class="field" placeholder="Cari atau ketik kota">
                        <datalist id="city-options">@foreach(['Jakarta','Bandung','Surabaya','Medan','Semarang','Yogyakarta','Makassar','Palembang','Denpasar','Malang','Bekasi','Tangerang','Depok','Bogor','Pekanbaru','Balikpapan'] as $city)<option value="{{ $city }}">@endforeach</datalist>
                    </label>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <label class="block"><span class="mb-2 block text-sm font-bold text-slate-700">Nama bisnis</span><input name="business_name" value="{{ old('business_name') }}" required maxlength="255" class="field" placeholder="Contoh: Brew Cool"></label>
                    <label class="block"><span class="mb-2 block text-sm font-bold text-slate-700">Nama outlet utama</span><input name="outlet_name" value="{{ old('outlet_name', 'Outlet Utama') }}" required maxlength="255" class="field" placeholder="Outlet Utama"></label>
                </div>

                <label class="block"><span class="mb-2 block text-sm font-bold text-slate-700">Alamat <span class="font-normal text-slate-400">(opsional)</span></span><textarea name="address" class="field" placeholder="Alamat operasional utama">{{ old('address') }}</textarea></label>

                <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs leading-5 text-slate-400">Dengan melanjutkan, trial gratis 7 hari akan dimulai.</p>
                    <button class="inline-flex h-[52px] items-center justify-center gap-2 rounded-full bg-emerald-600 px-7 font-bold text-white shadow-lg shadow-emerald-600/20 transition hover:bg-emerald-700">
                        Siapkan Bisnis Saya
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</body>
</html>
