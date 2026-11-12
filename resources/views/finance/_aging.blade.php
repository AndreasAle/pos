{{-- $aging (FinanceReports aging output), $labels, $title --}}
@php $rp = fn ($v) => \App\Support\Money::rp($v); @endphp
<section class="min-w-0 rounded-lg border border-gray-200 bg-white p-4" aria-labelledby="ag">
    <div class="flex flex-wrap items-baseline justify-between gap-2"><h2 id="ag" class="text-sm font-semibold">{{ $title }}</h2><p class="text-xs text-gray-500">Per {{ \Carbon\Carbon::parse($aging['as_of'])->locale('id')->isoFormat('D MMM Y') }} · umur dihitung dari tanggal jatuh tempo</p></div>
    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <div class="rounded-lg border border-[#0C7856]/40 p-3"><p class="text-xs font-semibold text-[#0C7856]">Total</p><p class="mt-1 text-lg font-bold tabular-nums">{{ $rp($aging['total']) }}</p></div>
        @foreach($labels as $k => $l)<div class="rounded-lg border border-gray-200 p-3"><p class="text-xs font-semibold {{ $k === 'd90_plus' && $aging['buckets'][$k] > 0 ? 'text-red-600' : 'text-gray-500' }}">{{ $l }}</p><p class="mt-1 text-lg font-bold tabular-nums">{{ $rp($aging['buckets'][$k]) }}</p></div>@endforeach
    </div>
    @if($aging['parties'])
    <div class="mt-4 overflow-x-auto"><table class="w-full min-w-[640px] text-sm"><caption class="sr-only">Umur per pihak</caption>
        <thead class="bg-gray-50 text-xs font-semibold text-gray-500"><tr><th scope="col" class="px-3 py-2 text-left">Pihak</th>@foreach($labels as $l)<th scope="col" class="px-3 py-2 text-right">{{ $l }}</th>@endforeach<th scope="col" class="px-3 py-2 text-right">Total</th></tr></thead>
        <tbody class="divide-y divide-gray-100">@foreach($aging['parties'] as $name => $b)<tr><th scope="row" class="px-3 py-2 text-left font-medium">{{ $name }}</th>@foreach($labels as $k => $l)<td class="px-3 py-2 text-right tabular-nums {{ isset($b[$k]) ? '' : 'text-gray-300' }}">{{ isset($b[$k]) ? $rp($b[$k]) : '–' }}</td>@endforeach<td class="px-3 py-2 text-right font-semibold tabular-nums">{{ $rp($b['total']) }}</td></tr>@endforeach</tbody></table></div>
    @endif
</section>
