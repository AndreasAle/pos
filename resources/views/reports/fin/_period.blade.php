{{-- Needs $f (SalesReportFilter), $outlets, $queryString. Optional $compare (bool) and $extra (html of hidden/extra fields). --}}
@php $presets = ['today' => 'Hari ini', 'yesterday' => 'Kemarin', 'this_week' => 'Minggu ini', 'last_week' => 'Minggu lalu', 'this_month' => 'Bulan ini', 'last_month' => 'Bulan lalu', 'custom' => 'Pilih tanggal']; @endphp
@if(session('report_error'))
<div role="alert" class="mb-3 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-800">{{ session('report_error') }} Filter dikembalikan ke bawaan.</div>
@endif
<form method="GET" action="{{ url()->current() }}" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-[#E5E7EB] bg-white p-4" x-data="{ custom: {{ $f->preset === 'custom' ? 'true' : 'false' }} }">
    <label class="block"><span class="mb-1.5 block text-xs font-semibold text-[#64748B]">Periode</span>
        <select name="preset" x-on:change="custom = $event.target.value === 'custom'" class="h-10 rounded-lg text-sm">
            @foreach($presets as $k => $l)<option value="{{ $k }}" @selected($f->preset === $k)>{{ $l }}</option>@endforeach
        </select></label>
    <div x-show="custom" x-cloak class="flex items-end gap-2">
        <label class="block"><span class="mb-1.5 block text-xs font-semibold text-[#64748B]">Dari</span><input type="date" name="date_from" value="{{ $f->dateFrom() }}" class="h-10 rounded-lg text-sm"></label>
        <label class="block"><span class="mb-1.5 block text-xs font-semibold text-[#64748B]">Sampai</span><input type="date" name="date_to" value="{{ $f->dateTo() }}" class="h-10 rounded-lg text-sm"></label>
    </div>
    @if(count($outlets) > 1)
    <label class="block"><span class="mb-1.5 block text-xs font-semibold text-[#64748B]">Outlet</span>
        <select name="outlet_ids[]" class="h-10 rounded-lg text-sm">
            <option value="">Semua outlet</option>
            @foreach($outlets as $o)<option value="{{ $o->id }}" @selected(count($f->outletIds) === 1 && count($f->allowedOutletIds) > 1 && $f->outletIds[0] === $o->id)>{{ $o->name }}</option>@endforeach
        </select></label>
    @endif
    @if(!empty($compare))
    <label class="block"><span class="mb-1.5 block text-xs font-semibold text-[#64748B]">Bandingkan</span>
        <select name="comparison" class="h-10 rounded-lg text-sm"><option value="none">Tanpa pembanding</option><option value="previous" @selected($f->comparison === 'previous')>Periode sebelumnya</option></select></label>
    @endif
    {!! $extra ?? '' !!}
    <button class="h-10 rounded-lg bg-[#16A34A] px-4 text-sm font-semibold text-white hover:bg-[#15803D] focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#16A34A]">Terapkan</button>
    <a href="{{ url()->current() }}" class="inline-flex h-10 items-center rounded-lg px-3 text-sm text-[#64748B] hover:bg-slate-100">Reset</a>
    @isset($exports)
    <div class="ml-auto flex gap-2">@foreach($exports as $label => $fmt)<a href="{{ url()->current() }}?{{ http_build_query(array_merge($queryString, ['export' => $fmt])) }}" class="inline-flex h-10 items-center rounded-lg border border-[#E5E7EB] bg-white px-3 text-sm font-semibold text-[#334155] hover:border-[#16A34A] hover:text-[#15803D]">{{ $label }}</a>@endforeach</div>
    @endisset
    <p class="basis-full text-xs text-[#64748B]">{{ $f->start->locale('id')->isoFormat('D MMM Y') }} – {{ $f->end->subSecond()->locale('id')->isoFormat('D MMM Y') }} · {{ $f->timezone }} · {{ count($f->outletIds) === count($f->allowedOutletIds) ? 'semua outlet' : count($f->outletIds).' outlet' }}</p>
</form>
