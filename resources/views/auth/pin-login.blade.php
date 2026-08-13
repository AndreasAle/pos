<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk Kasir — {{ $outlet->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{--
    Deliberately no Alpine here. Every other page pulls it from a CDN, but this
    is the register's front door: if the cafe's connection hiccups or the CDN is
    blocked, a framework-driven keypad leaves the cashier staring at a blank
    screen. Plain JS keeps the pad working as long as the page itself loaded.
--}}
<body class="bg-gradient-to-br from-emerald-700 to-emerald-900 min-h-screen flex items-center justify-center p-4 select-none">

<div class="w-full max-w-lg">

    <div class="text-center mb-5">
        <h1 class="text-xl font-bold text-white">{{ $outlet->name }}</h1>
        <p class="text-emerald-100/80 text-sm">Masukkan PIN untuk membuka kasir</p>
    </div>

    <div class="bg-white rounded-3xl p-6 shadow-2xl">

        @if($staff->isEmpty())
            <div class="text-center py-8">
                <p class="text-4xl mb-3">🔒</p>
                <p class="font-semibold text-gray-800">Belum ada kasir dengan PIN</p>
                <p class="text-sm text-gray-500 mt-2 leading-relaxed">
                    Pemilik perlu mengatur PIN dulu lewat back office:<br>
                    <strong>Manajemen User → Edit → PIN Kasir</strong>
                </p>
            </div>
        @else
            {{-- Names only. The PIN is the secret, not who works here. --}}
            <div class="flex flex-wrap justify-center gap-2 mb-6">
                @foreach($staff as $person)
                    <span class="inline-flex items-center gap-1.5 bg-gray-100 text-gray-600 text-xs font-medium px-3 py-1.5 rounded-full">
                        <span class="w-5 h-5 rounded-full bg-emerald-600 text-white flex items-center justify-center text-[10px] font-bold">
                            {{ strtoupper(substr($person->name, 0, 1)) }}
                        </span>
                        {{ $person->name }}
                    </span>
                @endforeach
            </div>

            <div id="dots" class="flex justify-center gap-3 mb-2"></div>

            <p id="error" class="text-center text-sm h-6 mb-3 font-medium text-red-600">
                {{ $errors->first('pin') }}
            </p>

            <form method="POST" action="{{ route('pin.login') }}" id="pinForm">
                @csrf
                <input type="hidden" name="pin" id="pinField">
            </form>

            <div class="grid grid-cols-3 gap-3">
                @foreach([1,2,3,4,5,6,7,8,9] as $n)
                    <button type="button" data-digit="{{ $n }}"
                            class="h-16 rounded-2xl bg-gray-50 hover:bg-gray-100 active:bg-emerald-600 active:text-white text-2xl font-bold text-gray-800 transition-colors">
                        {{ $n }}
                    </button>
                @endforeach

                <button type="button" id="clearBtn"
                        class="h-16 rounded-2xl bg-gray-50 hover:bg-gray-100 active:bg-gray-200 text-sm font-semibold text-gray-500">
                    Hapus
                </button>

                <button type="button" data-digit="0"
                        class="h-16 rounded-2xl bg-gray-50 hover:bg-gray-100 active:bg-emerald-600 active:text-white text-2xl font-bold text-gray-800 transition-colors">
                    0
                </button>

                <button type="button" id="backBtn"
                        class="h-16 rounded-2xl bg-gray-50 hover:bg-gray-100 active:bg-gray-200 text-xl text-gray-500">
                    &#9003;
                </button>
            </div>

            <button type="button" id="submitBtn" disabled
                    class="w-full mt-4 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 disabled:bg-gray-200 disabled:text-gray-400 text-white font-bold py-4 rounded-2xl text-lg transition-colors">
                Buka Kasir
            </button>
        @endif

    </div>

    <div class="flex items-center justify-center gap-4 mt-6 text-xs">
        <a href="{{ route('login') }}" class="text-emerald-200/70 underline">Login back office</a>
        <span class="text-emerald-200/30">•</span>
        <form method="POST" action="{{ route('pin.unpair') }}">
            @csrf
            <button type="submit" class="text-emerald-200/70 underline">Ganti outlet</button>
        </form>
    </div>
</div>

@if(!$staff->isEmpty())
<script>
(function () {
    var MAX = 8, AUTO_AT = 6;

    var pin       = '';
    var busy      = false;
    var dots      = document.getElementById('dots');
    var errorEl   = document.getElementById('error');
    var field     = document.getElementById('pinField');
    var form      = document.getElementById('pinForm');
    var submitBtn = document.getElementById('submitBtn');

    function render() {
        var shown = Math.max(pin.length, 4);
        var html  = '';

        for (var i = 0; i < shown; i++) {
            html += '<span class="w-3.5 h-3.5 rounded-full ' +
                    (i < pin.length ? 'bg-emerald-600' : 'bg-gray-200') + '"></span>';
        }

        dots.innerHTML = html;
        submitBtn.disabled = pin.length < 4 || busy;
    }

    function clearError() {
        if (errorEl.textContent) errorEl.textContent = '';
    }

    function push(d) {
        if (busy || pin.length >= MAX) return;
        clearError();
        pin += d;
        render();
        // Most PINs are six digits — submit on the last one so the cashier
        // never has to reach for a second button.
        if (pin.length === AUTO_AT) submit();
    }

    function back()  { if (busy) return; pin = pin.slice(0, -1); clearError(); render(); }
    function reset() { if (busy) return; pin = ''; clearError(); render(); }

    function submit() {
        if (busy || pin.length < 4) return;
        busy = true;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Memeriksa…';
        field.value = pin;
        form.submit();
    }

    Array.prototype.forEach.call(document.querySelectorAll('[data-digit]'), function (btn) {
        btn.addEventListener('click', function () { push(btn.getAttribute('data-digit')); });
    });

    document.getElementById('clearBtn').addEventListener('click', reset);
    document.getElementById('backBtn').addEventListener('click', back);
    submitBtn.addEventListener('click', submit);

    // Hardware numeric keypads are common at a register.
    document.addEventListener('keydown', function (e) {
        if (e.key >= '0' && e.key <= '9') { push(e.key); }
        else if (e.key === 'Backspace')   { e.preventDefault(); back(); }
        else if (e.key === 'Enter')       { e.preventDefault(); submit(); }
    });

    render();
})();
</script>
@endif

</body>
</html>
