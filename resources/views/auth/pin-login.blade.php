<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk Kasir — {{ $outlet->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="bg-gradient-to-br from-emerald-700 to-emerald-900 min-h-screen flex items-center justify-center p-4 select-none">

<div x-data="pinPad()" x-cloak class="w-full max-w-lg">

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
            {{-- Who can sign in here. Names only — the PIN is the secret. --}}
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

            {{-- PIN dots --}}
            <div class="flex justify-center gap-3 mb-2">
                <template x-for="i in 8" :key="i">
                    <span x-show="i <= Math.max(pin.length, 4)"
                          class="w-3.5 h-3.5 rounded-full transition-colors"
                          :class="i <= pin.length ? 'bg-emerald-600' : 'bg-gray-200'"></span>
                </template>
            </div>

            <p class="text-center text-sm h-6 mb-3 font-medium"
               :class="error ? 'text-red-600' : 'text-transparent'"
               x-text="error || '.'"></p>

            <form method="POST" action="{{ route('pin.login') }}" x-ref="form">
                @csrf
                <input type="hidden" name="pin" :value="pin">
            </form>

            {{-- Keypad --}}
            <div class="grid grid-cols-3 gap-3">
                <template x-for="n in [1,2,3,4,5,6,7,8,9]" :key="n">
                    <button type="button" @click="push(n)"
                            class="h-16 rounded-2xl bg-gray-50 hover:bg-gray-100 active:bg-emerald-600 active:text-white text-2xl font-bold text-gray-800 transition-colors"
                            x-text="n"></button>
                </template>

                <button type="button" @click="clearAll()"
                        class="h-16 rounded-2xl bg-gray-50 hover:bg-gray-100 active:bg-gray-200 text-sm font-semibold text-gray-500">
                    Hapus
                </button>

                <button type="button" @click="push(0)"
                        class="h-16 rounded-2xl bg-gray-50 hover:bg-gray-100 active:bg-emerald-600 active:text-white text-2xl font-bold text-gray-800 transition-colors">
                    0
                </button>

                <button type="button" @click="back()"
                        class="h-16 rounded-2xl bg-gray-50 hover:bg-gray-100 active:bg-gray-200 text-xl text-gray-500">
                    ⌫
                </button>
            </div>

            <button type="button" @click="submit()" :disabled="pin.length < 4 || busy"
                    class="w-full mt-4 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 disabled:bg-gray-200 disabled:text-gray-400 text-white font-bold py-4 rounded-2xl text-lg transition-colors">
                <span x-show="!busy">Buka Kasir</span>
                <span x-show="busy">Memeriksa…</span>
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

<script>
function pinPad() {
    return {
        pin:   '',
        busy:  false,
        error: @json($errors->first('pin')),

        push(n) {
            if (this.pin.length >= 8) return;
            this.error = '';
            this.pin += n;
            // Most PINs are the same length; submit as soon as a full-length one
            // is entered so the cashier never reaches for a second button.
            if (this.pin.length === 6) this.submit();
        },

        back()     { this.pin = this.pin.slice(0, -1); this.error = ''; },
        clearAll() { this.pin = ''; this.error = ''; },

        submit() {
            if (this.pin.length < 4 || this.busy) return;
            this.busy = true;
            this.$refs.form.submit();
        },
    };
}

// Hardware numeric keypads are common at a register.
document.addEventListener('keydown', (e) => {
    const el = document.querySelector('[x-data]');
    if (!el || !el._x_dataStack) return;
    const c = el._x_dataStack[0];
    if (e.key >= '0' && e.key <= '9') c.push(parseInt(e.key));
    else if (e.key === 'Backspace')   c.back();
    else if (e.key === 'Enter')       c.submit();
});
</script>

</body>
</html>
