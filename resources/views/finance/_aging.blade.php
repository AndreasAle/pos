{{-- $aging (FinanceReports aging), $labels, $title, $noun ("Hutang" / "Piutang") --}}
@php
    $rp = fn ($v) => \App\Support\Money::rp($v);
    $total = (float) $aging['total'];
    $docs = count($aging['rows']);
    $overdueDocs = collect($aging['rows'])->where('days_overdue', '>', 0)->count();
    $old = (float) ($aging['buckets']['d61_90'] + $aging['buckets']['d90_plus']);
    // Hijau ke merah: makin lama menunggak, makin gelap peringatannya.
    $seg = ['current' => '#16A34A', 'd1_30' => '#84CC16', 'd31_60' => '#F59E0B', 'd61_90' => '#F97316', 'd90_plus' => '#DC2626'];
@endphp

<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    <x-kpi :label="$noun.' belum lunas'" :value="$rp($total)" :sub="$docs.' dokumen'" tone="brand"/>
    <x-kpi label="Lewat jatuh tempo" :value="$rp($aging['overdue'])" :sub="$overdueDocs.' dokumen menunggak'" :tone="$aging['overdue'] > 0 ? 'danger' : 'default'"/>
    <x-kpi label="Belum jatuh tempo" :value="$rp($aging['buckets']['current'])" sub="Masih dalam tempo"/>
    <x-kpi label="Menunggak > 60 hari" :value="$rp($old)" :sub="$old > 0 ? 'Prioritaskan tindak lanjut' : 'Tidak ada'" :tone="$old > 0 ? 'warn' : 'default'"/>
</div>

<x-panel class="mt-4" :title="$title" :sub="'Per '.\Carbon\Carbon::parse($aging['as_of'])->locale('id')->isoFormat('D MMM Y').' · umur dihitung dari tanggal jatuh tempo'">
    @if($total > 0)
        <div class="flex h-3 overflow-hidden rounded-full bg-slate-100" role="img" aria-label="Komposisi umur {{ strtolower($noun) }}">
            @foreach($labels as $k => $l)
                @if($aging['buckets'][$k] > 0)<div style="width: {{ $aging['buckets'][$k] / $total * 100 }}%; background: {{ $seg[$k] }}" title="{{ $l }}: {{ $rp($aging['buckets'][$k]) }}"></div>@endif
            @endforeach
        </div>
        <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3 lg:grid-cols-5">
            @foreach($labels as $k => $l)
            <div class="min-w-0">
                <dt class="flex items-center gap-1.5 text-xs font-semibold text-[#64748B]"><span class="h-2 w-2 rounded-full" style="background: {{ $seg[$k] }}"></span>{{ $l }}</dt>
                <dd class="mt-0.5 text-base font-bold tabular-nums {{ $k === 'd90_plus' && $aging['buckets'][$k] > 0 ? 'text-red-600' : '' }}">{{ $rp($aging['buckets'][$k]) }}</dd>
            </div>
            @endforeach
        </dl>

        @if($aging['parties'])
        <div class="mt-5 overflow-x-auto rounded-lg border border-[#E5E7EB]">
            <table class="w-full min-w-[680px] text-sm"><caption class="sr-only">Umur per pihak</caption>
                <thead><tr><th scope="col" class="px-4 py-3 text-left">Pihak</th>@foreach($labels as $l)<th scope="col" class="px-4 py-3 text-right">{{ $l }}</th>@endforeach<th scope="col" class="px-4 py-3 text-right">Total</th></tr></thead>
                <tbody class="divide-y divide-[#E5E7EB]">
                @foreach($aging['parties'] as $name => $b)
                    <tr><th scope="row" class="px-4 py-3 text-left font-semibold">{{ $name }}</th>
                        @foreach($labels as $k => $l)<td class="px-4 py-3 text-right tabular-nums {{ isset($b[$k]) ? ($k === 'd90_plus' ? 'text-red-600 font-semibold' : '') : 'text-[#CBD5E1]' }}">{{ isset($b[$k]) ? $rp($b[$k]) : '–' }}</td>@endforeach
                        <td class="px-4 py-3 text-right font-bold tabular-nums">{{ $rp($b['total']) }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    @else
        <x-empty :title="'Tidak ada '.strtolower($noun).' yang belum lunas'" text="Semua tagihan sudah lunas. Ringkasan umur akan muncul di sini begitu ada yang belum dibayar." icon="M5 13l4 4L19 7"/>
    @endif
</x-panel>
