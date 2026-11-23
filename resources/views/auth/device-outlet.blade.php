<x-device-shell title="Pilih outlet" subtitle="Perangkat ini akan menjadi kasir untuk outlet yang dipilih.">
    <div class="bg-white rounded-xl p-4 shadow-2xl space-y-2">
        @foreach($outlets as $outlet)
            <form method="POST" action="{{ route('device.setup.store') }}">
                @csrf
                <input type="hidden" name="outlet_id" value="{{ $outlet->id }}">
                <button type="submit"
                        class="w-full flex items-center justify-between gap-3 rounded-xl border px-4 py-4 text-left transition-colors
                               {{ $current?->id === $outlet->id ? 'border-[#16A34A] bg-[#ECFDF3]' : 'border-gray-200 hover:border-emerald-400' }}">
                    <span>
                        <span class="block font-bold text-[#0B1B3F]">{{ $outlet->name }}</span>
                        @if($outlet->address)
                            <span class="block text-xs text-gray-500 mt-0.5">{{ \Illuminate\Support\Str::limit($outlet->address, 70) }}</span>
                        @endif
                    </span>
                    @if($current?->id === $outlet->id)
                        <x-pill tone="green">Terhubung</x-pill>
                    @endif
                </button>
            </form>
        @endforeach
    </div>
</x-device-shell>
