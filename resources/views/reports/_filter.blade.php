{{-- $outlets, $f (array date_from/date_to/outlet_id), $exports (opsional: [['label' => 'Excel', 'url' => '...'], ...]) --}}
<form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-[#E5E7EB] bg-white p-4">
    <label class="block">
        <span class="mb-1.5 block text-xs font-semibold text-[#64748B]">Dari tanggal</span>
        <input type="date" name="date_from" value="{{ $f['date_from'] }}" class="h-10 px-3">
    </label>
    <label class="block">
        <span class="mb-1.5 block text-xs font-semibold text-[#64748B]">Sampai tanggal</span>
        <input type="date" name="date_to" value="{{ $f['date_to'] }}" class="h-10 px-3">
    </label>
    @if(isset($outlets) && count($outlets) > 1)
    <label class="block">
        <span class="mb-1.5 block text-xs font-semibold text-[#64748B]">Outlet</span>
        <select name="outlet_id" class="h-10 min-w-[160px] px-3">
            <option value="">Semua outlet</option>
            @foreach($outlets as $outlet)
            <option value="{{ $outlet->id }}" {{ ($f['outlet_id'] ?? '') == $outlet->id ? 'selected' : '' }}>{{ $outlet->name }}</option>
            @endforeach
        </select>
    </label>
    @endif
    <button type="submit" class="h-10 rounded-lg bg-[#16A34A] px-5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-[#15803D]">Terapkan</button>
    <a href="{{ url()->current() }}" class="inline-flex h-10 items-center rounded-lg px-3 text-sm font-semibold text-[#64748B] transition-colors hover:bg-slate-100">Reset</a>

    @if(!empty($exports))
    <div class="ml-auto flex flex-wrap gap-2">
        @foreach($exports as $ex)
        <a href="{{ $ex['url'] }}" class="inline-flex h-10 items-center gap-2 rounded-lg border border-[#E5E7EB] bg-white px-4 text-sm font-semibold text-[#334155] transition-colors hover:border-[#16A34A] hover:text-[#15803D]">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M5 20h14"/></svg>{{ $ex['label'] }}
        </a>
        @endforeach
    </div>
    @endif
</form>
