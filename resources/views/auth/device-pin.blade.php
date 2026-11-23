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
