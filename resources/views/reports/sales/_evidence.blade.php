{{-- Evidence footnote: as-of, metric version, timezone, exclusions, coverage. Needs $evidence (and optional $notes, $warnings). --}}
<footer class="mt-4 rounded-xl border border-[#E5E7EB] bg-white px-4 py-3 text-xs leading-relaxed text-[#64748B]">
    <p>
        <span class="font-semibold text-[#334155]">Sumber angka:</span>
        order berstatus paid · periode {{ $evidence['period']['from'] }} s/d {{ $evidence['period']['to'] }} ({{ $evidence['timezone'] }})
        · versi metrik {{ $evidence['metric_version'] }} · data per {{ $evidence['as_of'] ? \Carbon\Carbon::parse($evidence['as_of'])->format('d M Y H:i') : '-' }}.
    </p>
    <p class="mt-1">Pembayaran adalah catatan di POS, bukan settlement gateway atau rekonsiliasi bank. Order refund dan batal dilaporkan terpisah dan tidak dihitung sebagai penjualan.</p>
    @foreach(($notes ?? []) as $n)<p class="mt-1">{{ $n }}</p>@endforeach
</footer>
