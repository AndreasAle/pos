@if(\App\Support\InApp::check())
{{-- Versi aplikasi Android: dua tahap di satu layar (buat lalu ulangi), tanpa Alpine
     seperti layar perangkat lainnya. Aturan PIN tetap dicek server. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Buat PIN — {{ config('app.name') }}</title>
    @include('layouts._fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="icon" type="image/png" href="{{ asset('images/brand/yoka-icon.png') }}">
</head>
<body class="in-app min-h-screen bg-white font-sans text-[#0B1B3F]">
    <header class="sticky top-0 z-10 h-14 bg-white/95 backdrop-blur border-b border-[#EEF0F3] px-5 flex items-center">
        <span class="text-[17px] font-semibold">PIN kasir</span>
    </header>

    <main class="max-w-md mx-auto px-6 pt-8 pb-10">
        <h1 id="pin-title" class="text-[28px] leading-tight font-bold tracking-[-0.02em]">Buat PIN kasir</h1>
        <p id="pin-sub" class="mt-2 text-[15px] leading-relaxed text-[#64748B]">4 sampai 6 angka. Dipakai untuk membuka kasir di perangkat ini setiap hari, tanpa email dan password.</p>

        <form id="pin-form" method="POST" action="{{ route('device.pin.store') }}" class="mt-10">
            @csrf
            <input type="hidden" name="pin" id="pin-value">
            <input type="hidden" name="pin_confirmation" id="pin-confirm-value">

            <label class="relative block" for="pin-input">
                <input id="pin-input" type="tel" inputmode="numeric" autocomplete="off" maxlength="6" autofocus
                       aria-label="PIN" class="absolute inset-0 w-full h-full opacity-0 caret-transparent">
                <span id="pin-slots" class="grid grid-cols-6 gap-2.5" aria-hidden="true">
                    @for($i = 0; $i < 6; $i++)
                        <span class="h-16 rounded-2xl bg-[#F4F6F8] flex items-center justify-center transition-colors">
                            <span class="w-3.5 h-3.5 rounded-full bg-[#0B1B3F] scale-0 transition-[scale] duration-150"></span>
                        </span>
                    @endfor
                </span>
            </label>

            <p id="pin-error" class="mt-4 min-h-[20px] text-[14px] text-[#DC2626]">@error('pin'){{ $message }}@enderror</p>

            <button id="pin-next" type="button" disabled
                    class="mt-6 w-full h-14 rounded-2xl bg-[#16A34A] active:bg-[#15803D] disabled:bg-[#BBF7D0] text-white text-[16px] font-semibold transition-colors">
                Lanjut
            </button>
            <button id="pin-back" type="button" hidden class="mt-4 w-full text-center text-[15px] font-semibold text-[#64748B]">Ganti PIN</button>
        </form>

        <p class="mt-10 text-center text-[13px] leading-relaxed text-[#94A3B8]">Hindari PIN yang mudah ditebak seperti 1234 atau tanggal lahir.</p>
    </main>

    <script>
    (function () {
        var input = document.getElementById('pin-input');
        var slots = document.getElementById('pin-slots').children;
        var next = document.getElementById('pin-next');
        var back = document.getElementById('pin-back');
        var err = document.getElementById('pin-error');
        var title = document.getElementById('pin-title');
        var sub = document.getElementById('pin-sub');
        var form = document.getElementById('pin-form');
        var first = '';

        function paint() {
            var v = input.value;
            for (var i = 0; i < 6; i++) {
                var box = slots[i], dot = box.firstElementChild;
                dot.style.scale = i < v.length ? '1' : '0';
                box.style.boxShadow = i === v.length ? 'inset 0 0 0 2px #16A34A' : 'none';
                box.style.background = i === v.length ? '#FFFFFF' : '#F4F6F8';
            }
            next.disabled = v.length < 4;
        }

        function confirmStep() {
            first = input.value;
            input.value = '';
            title.textContent = 'Ulangi PIN';
            sub.textContent = 'Ketik sekali lagi untuk memastikan.';
            next.textContent = 'Simpan PIN';
            back.hidden = false;
            err.textContent = '';
            paint();
            input.focus();
        }

        function restart() {
            first = '';
            input.value = '';
            title.textContent = 'Buat PIN kasir';
            sub.textContent = '4 sampai 6 angka.';
            next.textContent = 'Lanjut';
            back.hidden = true;
            err.textContent = '';
            paint();
            input.focus();
        }

        function submit() {
            if (input.value !== first) {
                err.textContent = 'PIN tidak sama. Coba ketik lagi.';
                input.value = '';
                paint();
                return;
            }
            document.getElementById('pin-value').value = first;
            document.getElementById('pin-confirm-value').value = input.value;
            next.disabled = true;
            next.textContent = 'Menyimpan...';
            form.submit();
        }

        input.addEventListener('input', function () {
            input.value = input.value.replace(/\D/g, '').slice(0, 6);
            err.textContent = '';
            paint();
            // Tahap ulangi: kirim otomatis begitu panjangnya sama dengan PIN pertama.
            if (first && input.value.length === first.length) submit();
        });
        next.addEventListener('click', function () { first ? submit() : confirmStep(); });
        back.addEventListener('click', restart);
        document.getElementById('pin-slots').parentElement.addEventListener('click', function () { input.focus(); });
        paint();
    })();
    </script>
</body>
</html>
@else
<x-device-shell title="Buat PIN kasir Anda" subtitle="Dipakai untuk membuka kasir di perangkat ini setiap hari, tanpa email dan password.">
    <form method="POST" action="{{ route('device.pin.store') }}" class="bg-white rounded-xl p-6 shadow-2xl">
        @csrf
        <label for="pin" class="block text-sm font-semibold text-[#0B1B3F] mb-1.5">PIN (4–6 angka)</label>
        <input id="pin" type="password" name="pin" inputmode="numeric" pattern="[0-9]*" minlength="4" maxlength="6" required autofocus
               class="w-full px-4 py-3.5 border-2 border-gray-200 rounded-xl text-center text-2xl font-bold tracking-[.5em] focus:outline-none focus:border-[#16A34A]">

        <label for="pin_confirmation" class="block text-sm font-semibold text-[#0B1B3F] mt-4 mb-1.5">Ulangi PIN</label>
        <input id="pin_confirmation" type="password" name="pin_confirmation" inputmode="numeric" pattern="[0-9]*" minlength="4" maxlength="6" required
               class="w-full px-4 py-3.5 border-2 border-gray-200 rounded-xl text-center text-2xl font-bold tracking-[.5em] focus:outline-none focus:border-[#16A34A]">

        @error('pin')
            <p class="text-sm text-red-600 mt-3 text-center font-medium">{{ $message }}</p>
        @enderror

        <p class="text-xs text-gray-500 mt-4 text-center leading-relaxed">Jangan pakai PIN yang mudah ditebak seperti 1234 atau tanggal lahir.</p>

        <button type="submit" class="w-full mt-5 bg-[#16A34A] hover:bg-emerald-700 text-white font-bold py-4 rounded-xl text-lg transition-colors">
            Simpan PIN
        </button>
    </form>
</x-device-shell>
@endif
