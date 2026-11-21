@extends('layouts.platform')
@section('title', 'Prospek Adit')

@section('content')
@php
    $cols = [
        ['business_name', 'Nama bisnis', 'w-48'], ['contact_name', 'Nama kontak', 'w-36'], ['phone', 'No. WhatsApp', 'w-36'],
        ['business_type', 'Jenis usaha', 'w-32'], ['city', 'Kota', 'w-32'], ['outlet_count', 'Outlet', 'w-20'],
        ['source', 'Sumber nomor', 'w-40'], ['notes', 'Catatan untuk Adit', 'w-64'],
    ];
    $tone = ['siap' => 'bg-emerald-100 text-emerald-800', 'perlu_dicek' => 'bg-amber-100 text-amber-800', 'dijadwalkan' => 'bg-sky-100 text-sky-800',
        'dihubungi' => 'bg-slate-200 text-slate-700', 'membalas' => 'bg-violet-100 text-violet-800', 'tertarik' => 'bg-fuchsia-100 text-fuchsia-800',
        'trial' => 'bg-teal-100 text-teal-800', 'bayar' => 'bg-[#16A34A] text-white', 'tidak_minat' => 'bg-slate-100 text-slate-500', 'berhenti' => 'bg-red-100 text-red-700', 'baru' => 'bg-slate-100 text-slate-600'];
@endphp
<div x-data="prospectSheet(@js($rows), @js($tone))" class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black">Prospek Adit</h1>
            <p class="mt-1 max-w-3xl text-sm text-slate-500">Isi seperti spreadsheet, atau tempel langsung dari Google Sheets. Adit merapikan dan mengecek tiap baris. Setiap hari jam 09.00 Adit menyiapkan maksimal {{ $stats['daily_limit'] }} pesan personal, dan pesan baru terkirim setelah kamu tekan <b>Setujui &amp; kirim</b> di Tim AI.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ route('platform.prospects.check') }}">@csrf<button class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold">Cek ulang semua</button></form>
            <form method="POST" action="{{ route('platform.prospects.draft') }}">@csrf<button class="rounded-lg bg-[#0B1B3F] px-4 py-2.5 text-sm font-bold text-white">Susun draf hari ini ({{ $stats['drafts_today'] }}/{{ $stats['daily_limit'] }})</button></form>
        </div>
    </div>

    @if(session('success'))<div class="rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('success') }}</div>@endif
    <div x-show="error" x-cloak class="rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800" x-text="error"></div>

    <div class="grid gap-3 sm:grid-cols-5">
        @foreach([['Total prospek', $stats['total']], ['Sudah dihubungi', $stats['contacted']], ['Membalas', $stats['replied']], ['Daftar trial', $stats['trial']], ['Bayar', $stats['paid']]] as [$l, $v])
        <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $l }}</p><p class="mt-1 text-2xl font-black">{{ $v }}</p></div>
        @endforeach
    </div>

    <section class="overflow-hidden rounded-xl border border-slate-300 bg-white">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-[#F8FAFD] px-3 py-2">
            <div class="flex items-center gap-3 text-sm">
                <button type="button" @click="addRow()" class="rounded-md bg-[#16A34A] px-3 py-1.5 text-sm font-bold text-white">+ Tambah baris</button>
                <span class="text-xs text-slate-500">Ketik langsung di baris kosong, atau salin dari Google Sheets lalu tempel (Ctrl+V) di sel mana saja.</span>
            </div>
            <div class="flex items-center gap-2 text-sm">
                <input x-model="q" placeholder="Cari nama, nomor, kota..." class="w-56 rounded-md border border-slate-300 px-3 py-1.5">
                <select x-model="filter" class="rounded-md border border-slate-300 px-3 py-1.5">
                    <option value="">Semua status</option>
                    @foreach($statuses as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                </select>
            </div>
        </div>
        <div class="max-h-[70vh] overflow-auto">
            <table class="w-full min-w-[1600px] border-collapse text-[13px]" style="font-family: Arial, sans-serif">
                <thead class="sticky top-0 z-10">
                    <tr class="bg-[#F1F3F4] text-center text-[11px] text-slate-500">
                        <th class="w-10 border border-slate-300"></th>
                        @foreach(range(0, count($cols) + 2) as $i)<th class="border border-slate-300 py-0.5 font-normal">{{ chr(65 + $i) }}</th>@endforeach
                    </tr>
                    <tr class="bg-[#274E3D] text-center text-xs font-bold text-white">
                        <th class="border border-slate-300 bg-[#F1F3F4] text-[11px] font-normal text-slate-500">1</th>
                        @foreach($cols as [$k, $label, $w])<th class="{{ $w }} border border-slate-400 px-2 py-2">{{ $label }}</th>@endforeach
                        <th class="w-40 border border-slate-400 px-2 py-2">Status</th>
                        <th class="w-80 border border-slate-400 px-2 py-2">Cek Adit</th>
                        <th class="w-28 border border-slate-400 px-2 py-2">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(r, i) in visible()" :key="r.id">
                        <tr class="align-middle">
                            <td class="border border-slate-300 bg-[#F1F3F4] text-center text-[11px] text-slate-500" x-text="i + 2"></td>
                            @foreach($cols as [$k, $label, $w])
                            <td class="border border-slate-300 p-0">
                                <input :value="r.{{ $k }} ?? ''" @change="save(r, '{{ $k }}', $event.target.value)" @keydown.enter.prevent="$event.target.blur()" @paste="onPaste($event)"
                                       :readonly="'{{ $k }}' === 'phone' && r.locked" @if($k === 'outlet_count') type="number" min="1" @endif
                                       class="block w-full bg-transparent px-2 py-1.5 outline-none focus:ring-2 focus:ring-inset focus:ring-[#1A73E8] {{ $k === 'outlet_count' ? 'text-center' : '' }}">
                            </td>
                            @endforeach
                            <td class="border border-slate-300 px-2 py-1 text-center">
                                <span class="inline-block rounded px-2 py-0.5 text-xs font-bold" :class="tone[r.status] || tone.baru" x-text="r.status_label"></span>
                                <p class="text-[10px] text-slate-500" x-show="r.contact_count > 0" x-text="'Dihubungi ' + r.contact_count + 'x' + (r.last_reply ? ' · balas ' + r.last_reply : '')"></p>
                            </td>
                            <td class="border border-slate-300 px-2 py-1 text-xs">
                                <template x-for="it in r.issues"><p :class="it.level === 'blok' ? 'text-red-600' : (it.level === 'saran' ? 'text-amber-700' : 'text-slate-500')" x-text="it.text"></p></template>
                                <p x-show="!r.issues.length" class="text-emerald-700">Data lengkap, aman dihubungi.</p>
                            </td>
                            <td class="border border-slate-300 px-2 py-1 text-center text-xs">
                                <select @change="setStatus(r, $event.target.value); $event.target.value = ''" class="w-full rounded border border-slate-200 px-1 py-0.5" x-show="r.contact_count > 0">
                                    <option value="">Tandai...</option><option value="tertarik">Tertarik</option><option value="tidak_minat">Tidak minat</option><option value="berhenti">Minta berhenti</option>
                                </select>
                                <button type="button" x-show="!r.locked" @click="remove(r)" class="text-red-600 hover:underline">Hapus</button>
                            </td>
                        </tr>
                    </template>
                    <template x-for="n in blanks()" :key="'b' + n">
                        <tr>
                            <td class="border border-slate-300 bg-[#F1F3F4] text-center text-[11px] text-slate-500" x-text="visible().length + n + 1"></td>
                            @foreach($cols as [$k, $label, $w])
                            <td class="border border-slate-300 p-0"><input @change="createFrom('{{ $k }}', $event.target)" @keydown.enter.prevent="$event.target.blur()" @paste="onPaste($event)" @if($k === 'outlet_count') type="number" min="1" @endif class="block w-full bg-transparent px-2 py-1.5 outline-none focus:ring-2 focus:ring-inset focus:ring-[#1A73E8]"></td>
                            @endforeach
                            <td class="border border-slate-300"></td><td class="border border-slate-300"></td><td class="border border-slate-300"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </section>
    <p class="text-xs text-slate-500">Aturan yang dikunci di kode: maksimal {{ $stats['daily_limit'] }} pesan per hari, satu prospek paling banyak dihubungi 2 kali (pembuka dan satu follow-up setelah 3 hari tanpa balasan), nomor tanpa sumber, nomor dobel, atau yang sudah jadi pelanggan tidak dihubungi, dan yang membalas "stop" otomatis berhenti dihubungi. Adit tidak memberi diskon.</p>
</div>

<script>
function prospectSheet(rows, tone) {
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const call = async (method, url, body) => {
        const res = await fetch(url, { method, headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: body ? JSON.stringify(body) : undefined });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message || 'Gagal menyimpan.');
        return data;
    };
    return {
        rows, tone, q: '', filter: '', pasteOpen: false, pasteText: '', busy: false, error: '',
        visible() {
            const q = this.q.toLowerCase();
            return this.rows.filter(r => (!this.filter || r.status === this.filter)
                && (!q || [r.business_name, r.contact_name, r.phone, r.city, r.business_type].join(' ').toLowerCase().includes(q)));
        },
        blanks() { return Math.max(25 - this.visible().length, 8); },
        async createFrom(field, el) {
            const v = el.value.trim(); if (!v) return;
            try { this.error = ''; this.rows.push(await call('POST', @js(route('platform.prospects.store')), { [field]: v })); el.value = ''; } catch (e) { this.error = e.message; }
        },
        onPaste(e) {
            const t = (e.clipboardData || window.clipboardData).getData('text');
            if (!/[\t\n]/.test(t.trim())) return;
            e.preventDefault(); this.pasteText = t; this.paste();
        },
        replace(row) { const i = this.rows.findIndex(r => r.id === row.id); if (i >= 0) this.rows.splice(i, 1, row); },
        async addRow() {
            try { this.error = ''; this.rows.push(await call('POST', @js(route('platform.prospects.store')), {})); } catch (e) { this.error = e.message; }
        },
        async save(r, field, value) {
            if ((r[field] ?? '') == value) return;
            try { this.error = ''; this.replace(await call('PATCH', @js(url('platform/prospects')) + '/' + r.id, { [field]: value === '' ? null : value })); } catch (e) { this.error = e.message; }
        },
        async setStatus(r, status) {
            if (!status) return;
            try { this.replace(await call('POST', @js(url('platform/prospects')) + '/' + r.id + '/status', { status })); } catch (e) { this.error = e.message; }
        },
        async remove(r) {
            if (!confirm('Hapus prospek ' + (r.business_name || '#' + r.id) + '?')) return;
            try { await call('DELETE', @js(url('platform/prospects')) + '/' + r.id); this.rows = this.rows.filter(x => x.id !== r.id); } catch (e) { this.error = e.message; }
        },
        async paste() {
            if (!this.pasteText.trim()) return;
            this.busy = true;
            try { const d = await call('POST', @js(route('platform.prospects.paste')), { text: this.pasteText }); this.rows = [...this.rows, ...d.rows]; this.pasteText = ''; this.pasteOpen = false; this.error = ''; }
            catch (e) { this.error = e.message; } finally { this.busy = false; }
        },
    };
}
</script>
@endsection
