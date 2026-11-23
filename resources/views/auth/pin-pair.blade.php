{{-- A device not yet bound to an outlet. Binding needs an account, not a code. --}}
<x-device-shell title="Hubungkan perangkat ini" subtitle="Sekali saja. Setelah ini kasir cukup memilih nama dan memasukkan PIN.">
    <div class="bg-white rounded-xl p-6 shadow-2xl">
        <ol class="space-y-3 text-sm text-[#0B1B3F]">
            <li class="flex gap-3"><span class="shrink-0 w-6 h-6 rounded-full bg-[#ECFDF3] text-[#16A34A] font-bold flex items-center justify-center text-xs">1</span>Pemilik atau admin masuk dengan akun Yoka.</li>
            <li class="flex gap-3"><span class="shrink-0 w-6 h-6 rounded-full bg-[#ECFDF3] text-[#16A34A] font-bold flex items-center justify-center text-xs">2</span>Pilih outlet untuk perangkat ini.</li>
            <li class="flex gap-3"><span class="shrink-0 w-6 h-6 rounded-full bg-[#ECFDF3] text-[#16A34A] font-bold flex items-center justify-center text-xs">3</span>Selesai. Kasir masuk dengan PIN.</li>
        </ol>

        <a href="{{ route('device.setup') }}"
           class="block w-full mt-6 bg-[#16A34A] hover:bg-emerald-700 text-white font-bold py-4 rounded-xl text-lg text-center transition-colors">
            Masuk dengan akun
        </a>
        <a href="{{ route('register') }}"
           class="block w-full mt-3 border border-gray-200 text-[#0B1B3F] font-semibold py-3 rounded-xl text-center hover:border-emerald-400 transition-colors">
            Belum punya akun? Daftar bisnis baru
        </a>
    </div>
</x-device-shell>
