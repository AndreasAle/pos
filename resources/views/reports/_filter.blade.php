{{-- $outlets, $f --}}
<form method="GET" class="flex flex-wrap items-end gap-3 mb-5 bg-white rounded-2xl border border-gray-200 shadow-sm p-4">
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1 uppercase">Dari Tanggal</label>
        <input type="date" name="date_from" value="{{ $f['date_from'] }}"
               class="px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1 uppercase">Sampai Tanggal</label>
        <input type="date" name="date_to" value="{{ $f['date_to'] }}"
               class="px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
    </div>
    @if(isset($outlets) && count($outlets) > 1)
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1 uppercase">Outlet</label>
        <select name="outlet_id" class="px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
            <option value="">Semua Outlet</option>
            @foreach($outlets as $outlet)
            <option value="{{ $outlet->id }}" {{ ($f['outlet_id'] ?? '') == $outlet->id ? 'selected' : '' }}>
                {{ $outlet->name }}
            </option>
            @endforeach
        </select>
    </div>
    @endif
    <button type="submit"
            class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-2.5 rounded-xl text-sm transition-colors">
        Tampilkan
    </button>
    <a href="{{ url()->current() }}" class="text-sm text-gray-500 hover:text-gray-700 py-2.5 px-3 rounded-xl hover:bg-gray-100 transition-colors">
        Reset
    </a>
</form>
