@extends('reports.sales.layout')

@section('report')
@php $card = 'min-w-0 rounded-lg border border-gray-200 bg-white'; @endphp

<form method="GET" action="{{ url()->current() }}" class="{{ $card }} mb-4 flex flex-wrap items-end gap-3 p-3">
    @foreach($queryString as $k => $v)
        @foreach((array) $v as $vv)<input type="hidden" name="{{ $k }}{{ is_array($v) ? '[]' : '' }}" value="{{ $vv }}">@endforeach
    @endforeach
    @if(request()->filled('day'))<input type="hidden" name="day" value="{{ request('day') }}">@endif
    @if(request()->filled('hour'))<input type="hidden" name="hour" value="{{ request('hour') }}">@endif
    <label class="block grow sm:max-w-xs"><span class="mb-1 block text-xs font-semibold text-gray-500">Cari nomor order</span>
        <input type="search" name="q" value="{{ $search }}" placeholder="mis. ORD-2026" class="h-10 w-full rounded-lg border-gray-300 text-sm focus:border-[#0C7856] focus:ring-[#0C7856]"></label>
    <label class="block"><span class="mb-1 block text-xs font-semibold text-gray-500">Status</span>
        <select name="status" class="h-10 rounded-lg border-gray-300 text-sm focus:border-[#0C7856] focus:ring-[#0C7856]">
            @foreach(['paid' => 'Lunas', 'refunded' => 'Refund', 'cancelled' => 'Batal', 'all' => 'Semua'] as $k => $l)<option value="{{ $k }}" @selected($status === $k)>{{ $l }}</option>@endforeach
        </select></label>
    <button class="h-10 rounded-lg bg-[#0C7856] px-4 text-sm font-semibold text-white hover:bg-[#0a6a4c]">Cari</button>
    @if($drill)
    <p class="basis-full text-sm text-gray-700">Difilter ke <strong>{{ $drill }}</strong> · <a class="font-semibold text-[#0C7856] hover:underline" href="{{ route('reports.sales.orders', $queryString) }}">hapus filter ini</a></p>
    @endif
</form>

<section class="{{ $card }} overflow-hidden" aria-labelledby="ol-h">
    <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
        <h2 id="ol-h" class="text-sm font-semibold text-gray-900">Riwayat order</h2>
        <p class="text-xs text-gray-500">{{ \App\Support\Money::num($orders->total()) }} order · total laporan tidak berubah oleh halaman</p>
    </div>
    @include('reports.sales._orders_table', ['orders' => $orders])
    <div class="border-t border-gray-100 px-4 py-3">{{ $orders->links() }}</div>
</section>
@endsection
