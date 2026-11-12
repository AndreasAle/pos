<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Siapkan Bisnis — {{ config('app.name') }}</title>
    @include('layouts._fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="icon" type="image/png" href="{{ asset('images/brand/yoka-icon.png') }}">
    <style>
        [x-cloak]{display:none!important}
        .setup-field{height:3rem;width:100%;border:1px solid #cbd5e1;border-radius:.25rem;background:#fff;padding:0 .875rem;font-size:.9375rem;color:#0f172a;transition:border-color .15s,box-shadow .15s}
        .setup-field:focus{outline:none;border-color:#07894f;box-shadow:0 0 0 2px rgba(5,150,105,.12)}
        textarea.setup-field{height:5.25rem;padding-top:.75rem;resize:vertical}
    </style>
</head>
<body class="min-h-full bg-white font-sans text-slate-900">
<div class="min-h-screen lg:grid lg:grid-cols-2">
    {{-- Mengikuti bahasa visual halaman login: merek, pesan singkat, dan foto manusia nyata. --}}
    <aside class="relative hidden h-screen flex-col overflow-hidden bg-[#0c874e] px-14 pt-10 text-white lg:sticky lg:top-0 lg:flex">
        <a href="{{ route('landing') }}" class="relative z-20 inline-flex items-center gap-2 self-start" aria-label="Yoka POS">
            <img src="{{ asset('images/brand/yoka-logo-white.png') }}" alt="Yoka" class="h-9 w-auto">
            <span class="text-[22px] font-bold leading-none tracking-tight">POS</span>
        </a>

        <div class="relative z-20 mt-16 max-w-[490px]">
            <p class="text-sm font-semibold tracking-wide text-white/75">Pendaftaran bisnis · Langkah 2 dari 3</p>
            <h1 class="mt-4 text-[40px] font-extrabold leading-[1.14] tracking-[-.035em] xl:text-[46px]">Ceritakan sedikit tentang bisnis Anda.</h1>
            <p class="mt-5 max-w-[450px] text-base leading-7 text-white/85">Informasi ini membantu Yoka menyiapkan outlet utama dan fitur yang sesuai sejak hari pertama.</p>

            <div class="mt-8 border-y border-white/25 py-4">
                <div class="flex items-start gap-3">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        <p class="text-sm font-semibold">Akun sudah terverifikasi</p>
                        <p class="mt-1 text-sm text-white/70">{{ $user->email }}</p>
                    </div>
                </div>
            </div>
        </div>

        <img src="{{ asset('images/hero/slide-owner.jpg') }}" alt="Pemilik usaha memantau bisnis melalui Yoka POS"
             class="absolute bottom-0 right-0 z-10 w-[150%] max-w-none select-none"
             style="-webkit-mask-image:linear-gradient(to right,transparent 0,#000 10%);mask-image:linear-gradient(to right,transparent 0,#000 10%)">
    </aside>

    <main class="flex min-h-screen items-center bg-white px-5 py-10 sm:px-10 lg:px-16 xl:px-20">
        <div class="mx-auto w-full max-w-[650px]">
            <div class="mb-9 flex items-center justify-between lg:hidden">
                <a href="{{ route('landing') }}" class="inline-flex items-center gap-2">
                    <img src="{{ asset('images/brand/yoka-logo.png') }}" alt="Yoka" class="h-8 w-auto">
                    <span class="text-xl font-bold text-emerald-700">POS</span>
                </a>
                <span class="text-xs font-semibold text-slate-500">Langkah 2 dari 3</span>
            </div>

            <header class="mb-8 border-b border-slate-200 pb-6">
                <div class="mb-4 flex items-center gap-4">
                    <span class="text-xs font-bold uppercase tracking-[.16em] text-emerald-700">Informasi bisnis</span>
                    <span class="h-px flex-1 bg-slate-200"></span>
                    <span class="text-xs font-medium text-slate-400">02 / 03</span>
                </div>
                <h2 class="text-3xl font-bold tracking-[-.025em] text-slate-950 sm:text-[34px]">Siapkan profil bisnis</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">Isi data dasar berikut. Anda dapat memperbaruinya kembali melalui Pengaturan Bisnis.</p>
            </header>

            @if($errors->any())
                <div class="mb-6 border-l-4 border-red-600 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <p class="font-semibold">Ada data yang perlu diperiksa:</p>
                    <ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <form method="POST" action="{{ route('onboarding.business.store') }}" class="space-y-5" x-data="{ type: @js(old('business_type', 'fnb')) }">
                @csrf

                <fieldset>
                    <legend class="mb-2 block text-sm font-semibold text-slate-700">Jenis bisnis</legend>
                    <div class="grid border border-slate-300 sm:grid-cols-3 sm:divide-x sm:divide-slate-300">
                        @foreach([
                            ['fnb', 'Makanan & Minuman', 'Kafe, restoran, bakery'],
                            ['retail', 'Retail', 'Toko dan perdagangan'],
                            ['service', 'Jasa', 'Salon, studio, layanan'],
                        ] as $item)
                        <label class="flex cursor-pointer items-start gap-3 border-b border-slate-300 p-3.5 last:border-b-0 sm:border-b-0"
                               :class="type === '{{ $item[0] }}' ? 'bg-[#EAF7F0]' : 'bg-white hover:bg-slate-50'">
                            <input type="radio" name="business_type" value="{{ $item[0] }}" x-model="type" class="mt-1 h-4 w-4 shrink-0 accent-emerald-700">
                            <span><span class="block text-sm font-semibold text-slate-900">{{ $item[1] }}</span><span class="mt-0.5 block text-xs leading-5 text-slate-500">{{ $item[2] }}</span></span>
                        </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="grid gap-5 sm:grid-cols-2">
                    <label class="block">
                        <span class="mb-2 block text-sm font-semibold text-slate-700">Jumlah outlet saat ini</span>
                        <select name="outlet_scale" required class="setup-field">
                            <option value="">Pilih jumlah outlet</option>
                            @foreach(['1'=>'1 outlet','2-3'=>'2–3 outlet','4-10'=>'4–10 outlet','10+'=>'Lebih dari 10 outlet'] as $value=>$label)
                                <option value="{{ $value }}" @selected(old('outlet_scale')===$value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="mb-2 block text-sm font-semibold text-slate-700">Kota pusat bisnis</span>
                        <input name="city" value="{{ old('city') }}" required list="city-options" class="setup-field" placeholder="Cari atau ketik kota">
                        <datalist id="city-options">@foreach(['Jakarta','Bandung','Surabaya','Medan','Semarang','Yogyakarta','Makassar','Palembang','Denpasar','Malang','Bekasi','Tangerang','Depok','Bogor','Pekanbaru','Balikpapan'] as $city)<option value="{{ $city }}">@endforeach</datalist>
                    </label>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <label class="block">
                        <span class="mb-2 block text-sm font-semibold text-slate-700">Nama bisnis</span>
                        <input name="business_name" value="{{ old('business_name') }}" required maxlength="255" class="setup-field" placeholder="Contoh: Brew Cool">
                    </label>
                    <label class="block">
                        <span class="mb-2 block text-sm font-semibold text-slate-700">Nama outlet utama</span>
                        <input name="outlet_name" value="{{ old('outlet_name', 'Outlet Utama') }}" required maxlength="255" class="setup-field" placeholder="Outlet Utama">
                    </label>
                </div>

                <label class="block">
                    <span class="mb-2 block text-sm font-semibold text-slate-700">Alamat <span class="font-normal text-slate-400">(opsional)</span></span>
                    <textarea name="address" class="setup-field" placeholder="Alamat operasional utama">{{ old('address') }}</textarea>
                </label>

                <div class="flex flex-col-reverse gap-4 border-t border-slate-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
                    <p class="max-w-[260px] text-xs leading-5 text-slate-400">Trial gratis 7 hari dimulai setelah profil bisnis dibuat.</p>
                    <button class="inline-flex h-12 items-center justify-center gap-2 rounded-[4px] bg-[#07894f] px-6 text-sm font-bold text-white transition-colors hover:bg-[#067443] focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2">
                        Simpan dan lanjutkan
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</body>
</html>
