@extends('reports.sales.layout')

@section('report')
@php $card = 'min-w-0 rounded-xl border border-[#E5E7EB] bg-white'; @endphp

<form method="GET" action="{{ url()->current() }}" class="{{ $card }} mb-4 flex flex-wrap items-end gap-3 p-3">
    @foreach($queryString as $k => $v)
        @foreach((array) $v as $vv)<input type="hidden" name="{{ $k }}{{ is_array($v) ? '[]' : '' }}" value="{{ $vv }}">@endforeach
    @endforeach
    @if(request()->filled('day'))<input type="hidden" name="day" value="{{ request('day') }}">@endif
    @if(request()->filled('hour'))<input type="hidden" name="hour" value="{{ request('hour') }}">@endif
    <label class="block grow sm:max-w-xs"><span class="mb-1 block text-xs font-semibold text-[#64748B]">Cari nomor order</span>
        <input type="search" name="q" value="{{ $search }}" placeholder="mis. ORD-2026" class="h-10 w-full rounded-lg text-sm"></label>
    <label class="block"><span class="mb-1 block text-xs font-semibold text-[#64748B]">Status</span>
        <select name="status" class="h-10 rounded-lg text-sm">
            @foreach(['paid' => 'Lunas', 'refunded' => 'Refund', 'cancelled' => 'Batal', 'all' => 'Semua'] as $k => $l)<option value="{{ $k }}" @selected($status === $k)>{{ $l }}</option>@endforeach
        </select></label>
    <button class="h-10 rounded-lg bg-[#16A34A] px-4 text-sm font-semibold text-white hover:bg-[#15803D]">Cari</button>
    @if($drill)
    <p class="basis-full text-sm text-[#334155]">Difilter ke <strong>{{ $drill }}</strong> · <a class="font-semibold text-[#16A34A] hover:underline" href="{{ route('reports.sales.orders', $queryString) }}">hapus filter ini</a></p>
    @endif
</form>

<section class="{{ $card }} overflow-hidden" aria-labelledby="ol-h">
    <div class="flex items-center justify-between border-b border-[#E5E7EB] px-4 py-3">
        <h2 id="ol-h" class="text-base font-semibold text-[#0B1B3F]">Riwayat order</h2>
        <p class="text-xs text-[#64748B]">{{ \App\Support\Money::num($orders->total()) }} order · total laporan tidak berubah oleh halaman</p>
    </div>
    @include('reports.sales._orders_table', ['orders' => $orders])
    <div class="border-t border-[#E5E7EB] px-4 py-3">{{ $orders->links() }}</div>
</section>
@endsection
