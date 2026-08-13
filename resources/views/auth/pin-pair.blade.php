<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pasangkan Perangkat — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-br from-emerald-700 to-emerald-900 min-h-screen flex items-center justify-center p-6">

    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-white/15 rounded-2xl flex items-center justify-center mx-auto mb-4 text-3xl">
                🛒
            </div>
            <h1 class="text-2xl font-bold text-white">Pasangkan Perangkat</h1>
            <p class="text-emerald-100 text-sm mt-2">
                Sekali saja. Setelah ini kasir cukup memasukkan PIN.
            </p>
        </div>

        <form method="POST" action="{{ route('pin.pair') }}" class="bg-white rounded-3xl p-6 shadow-2xl">
            @csrf

            <label class="block text-sm font-semibold text-gray-700 mb-2">Kode Outlet</label>

            <input type="text" name="outlet_code" autofocus autocapitalize="characters"
                   value="{{ old('outlet_code') }}"
                   class="w-full px-4 py-4 border-2 border-gray-200 rounded-2xl text-center text-2xl font-bold tracking-widest uppercase focus:outline-none focus:border-emerald-500"
                   placeholder="VR01">

            @error('outlet_code')
                <p class="text-sm text-red-600 mt-2 text-center font-medium">{{ $message }}</p>
            @enderror

            <p class="text-xs text-gray-400 mt-3 text-center leading-relaxed">
                Kode outlet bisa dilihat pemilik di menu <strong>Outlet</strong> pada back office.
            </p>

            <button type="submit"
                    class="w-full mt-5 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-bold py-4 rounded-2xl text-lg transition-colors">
                Pasangkan
            </button>
        </form>

        <p class="text-center text-emerald-200/70 text-xs mt-6">
            Perlu masuk sebagai pemilik?
            <a href="{{ route('login') }}" class="underline">Login back office</a>
        </p>
    </div>

</body>
</html>
