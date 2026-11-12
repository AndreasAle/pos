{{-- Needs $f (SalesReportFilter), $outlets, $queryString. Optional $compare (bool) and $extra (html of hidden/extra fields). --}}
@php $presets = ['today' => 'Hari ini', 'yesterday' => 'Kemarin', 'this_week' => 'Minggu ini', 'last_week' => 'Minggu lalu', 'this_month' => 'Bulan ini', 'last_month' => 'Bulan lalu', 'custom' => 'Pilih tanggal']; @endphp
@if(session('report_error'))
<div role="alert" class="mb-3 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-800">{{ session('report_error') }} Filter dikembalikan ke bawaan.</div>
@endif
<form method="GET" action="{{ url()->current() }}" class="mb-4 flex flex-wrap items-end gap-3 rounded-lg border border-gray-200 bg-white p-3" x-data="{ custom: {{ $f->preset === 'custom' ? 'true' : 'false' }} }">
    <label class="block"><span class="mb-1 block text-xs font-semibold text-gray-500">Periode</span>
        <select name="preset" x-on:change="custom = $event.target.value === 'custom'" class="h-10 rounded-lg border-gray-300 text-sm focus:border-[#0C7856] focus:ring-[#0C7856]">
            @foreach($presets as $k => $l)<option value="{{ $k }}" @selected($f->preset === $k)>{{ $l }}</option>@endforeach
        </select></label>
    <div x-show="custom" x-cloak class="flex items-end gap-2">
        <label class="block"><span class="mb-1 block text-xs font-semibold text-gray-500">Dari</span><input type="date" name="date_from" value="{{ $f->dateFrom() }}" class="h-10 rounded-lg border-gray-300 text-sm"></label>
        <label class="block"><span class="mb-1 block text-xs font-semibold text-gray-500">Sampai</span><input type="date" name="date_to" value="{{ $f->dateTo() }}" class="h-10 rounded-lg border-gray-300 text-sm"></label>
    </div>
    @if(count($outlets) > 1)
    <label class="block"><span class="mb-1 block text-xs font-semibold text-gray-500">Outlet</span>
        <select name="outlet_ids[]" class="h-10 rounded-lg border-gray-300 text-sm focus:border-[#0C7856] focus:ring-[#0C7856]">
            <option value="">Semua outlet</option>
            @foreach($outlets as $o)<option value="{{ $o->id }}" @selected(count($f->outletIds) === 1 && count($f->allowedOutletIds) > 1 && $f->outletIds[0] === $o->id)>{{ $o->name }}</option>@endforeach
        </select></label>
    @endif
    @if(!empty($compare))
    <label class="block"><span class="mb-1 block text-xs font-semibold text-gray-500">Bandingkan</span>
        <select name="comparison" class="h-10 rounded-lg border-gray-300 text-sm"><option value="none">Tanpa pembanding</option><option value="previous" @selected($f->comparison === 'previous')>Periode sebelumnya</option></select></label>
    @endif
    {!! $extra ?? '' !!}
    <button class="h-10 rounded-lg bg-[#0C7856] px-4 text-sm font-semibold text-white hover:bg-[#0a6a4c] focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#0C7856]">Terapkan</button>
    <a href="{{ url()->current() }}" class="inline-flex h-10 items-center rounded-lg px-3 text-sm text-gray-500 hover:bg-gray-100">Reset</a>
    @isset($exports)
    <div class="ml-auto flex gap-2">@foreach($exports as $label => $fmt)<a href="{{ url()->current() }}?{{ http_build_query(array_merge($queryString, ['export' => $fmt])) }}" class="inline-flex h-10 items-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-semibold text-gray-700 hover:border-[#0C7856]">{{ $label }}</a>@endforeach</div>
    @endisset
    <p class="basis-full text-xs text-gray-500">{{ $f->start->locale('id')->isoFormat('D MMM Y') }} – {{ $f->end->subSecond()->locale('id')->isoFormat('D MMM Y') }} · {{ $f->timezone }} · {{ count($f->outletIds) === count($f->allowedOutletIds) ? 'semua outlet' : count($f->outletIds).' outlet' }}</p>
</form>
