@extends('layouts.platform')
@section('title', 'Tim AI')

@section('content')
<div x-data="{ intake: false }" class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-700">AI Operations Control Plane</p>
            <h1 class="mt-1 text-3xl font-black text-[#0B1B3F]">Tim AI Yoka</h1>
            <p class="mt-2 max-w-3xl text-sm leading-relaxed text-slate-500">Agen bekerja dari data yang dapat diaudit. Analisis internal boleh otomatis; uang, pesan keluar, refund, dan perubahan harga selalu membutuhkan persetujuan.</p>
        </div>
        <button type="button" @click="intake = !intake" class="rounded-xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white hover:bg-emerald-700">Tambah data kerja</button>
    </div>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ([
            ['Konten', $counts['content'] ?? 0, 'text-fuchsia-700'],
            ['Campaign', $counts['campaign'] ?? 0, 'text-sky-700'],
            ['Lead', $counts['lead'] ?? 0, 'text-amber-700'],
            ['Tiket CS', $counts['ticket'] ?? 0, 'text-rose-700'],
            ['Catatan finance', $counts['finance'] ?? 0, 'text-emerald-700'],
        ] as [$label, $value, $color])
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $label }}</p>
            <p class="mt-2 text-3xl font-black {{ $color }}">{{ number_format($value) }}</p>
        </div>
        @endforeach
    </section>

    <section x-show="intake" x-cloak x-transition class="rounded-xl border border-emerald-200 bg-white p-6 shadow-sm">
        <div class="mb-5"><h2 class="text-lg font-black text-[#0B1B3F]">Masukkan data operasional</h2><p class="mt-1 text-sm text-slate-500">Satu pintu untuk bahan kerja Marketing, Ads, Finance, Accounting, CS, dan Sales.</p></div>
        <form method="POST" action="{{ route('platform.ai-team.records') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">@csrf
            <label class="text-sm font-bold text-slate-700">Jenis
                <select name="kind" required class="mt-1.5 w-full rounded-xl border-slate-300 text-sm">
                    <option value="content">Konten Instagram/TikTok</option><option value="campaign">Campaign iklan</option><option value="lead">Lead sales</option><option value="ticket">Tiket CS</option><option value="finance">Pendapatan/biaya</option>
                </select>
            </label>
            <label class="text-sm font-bold text-slate-700 xl:col-span-2">Judul
                <input name="title" required maxlength="180" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm" placeholder="Contoh: Video kesalahan stok kafe">
            </label>
            <label class="text-sm font-bold text-slate-700">Status
                <select name="status" required class="mt-1.5 w-full rounded-xl border-slate-300 text-sm"><option value="new">Baru</option><option value="active">Aktif</option><option value="expense">Biaya</option><option value="income">Pendapatan</option><option value="resolved">Selesai</option></select>
            </label>
            <label class="text-sm font-bold text-slate-700">Sumber
                <input name="source" required maxlength="80" value="manual" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm" placeholder="Instagram, TikTok, WhatsApp">
            </label>
            <label class="text-sm font-bold text-slate-700">Skor 0–100
                <input name="score" type="number" min="0" max="100" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm" placeholder="Opsional">
            </label>
            <label class="text-sm font-bold text-slate-700">Nominal
                <input name="amount" type="number" min="0" step="1" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm" placeholder="Opsional">
            </label>
            <label class="text-sm font-bold text-slate-700">URL
                <input name="url" type="url" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm" placeholder="https://...">
            </label>
            <label class="text-sm font-bold text-slate-700 md:col-span-2 xl:col-span-3">Catatan
                <textarea name="notes" rows="3" maxlength="2000" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm" placeholder="Metrik, kebutuhan pelanggan, konteks, atau alasan prospek cocok"></textarea>
            </label>
            <div class="flex items-end"><button class="w-full rounded-xl bg-[#0B1B3F] px-5 py-3 text-sm font-bold text-white hover:bg-slate-800">Simpan ke workspace</button></div>
        </form>
    </section>

    <section>
        <div class="mb-4"><h2 class="text-xl font-black text-[#0B1B3F]">Pekerja aktif</h2><p class="mt-1 text-sm text-slate-500">Setiap eksekusi membuat hasil dan jejak audit baru.</p></div>
        <div class="grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
            @foreach($agents as $agent)
            @php
                $colors = ['chief'=>'emerald','marketing'=>'fuchsia','ads'=>'sky','finance'=>'amber','accounting'=>'teal','cs'=>'rose','sales'=>'violet'];
                $color = $colors[$agent->key] ?? 'slate';
            @endphp
            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div><p class="text-xs font-bold uppercase tracking-[.16em] text-{{ $color }}-700">{{ $agent->department }}</p><h3 class="mt-1 text-lg font-black text-[#0B1B3F]">{{ $agent->name }}</h3></div>
                    <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $agent->status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">{{ $agent->status === 'active' ? 'Aktif' : 'Dijeda' }}</span>
                </div>
                <p class="mt-3 min-h-12 text-sm leading-relaxed text-slate-600">{{ $agent->mission }}</p>
                <div class="mt-4 grid grid-cols-3 gap-2 rounded-xl bg-slate-50 p-3 text-center text-xs">
                    <div><p class="font-black text-slate-900">{{ $agent->open_tasks_count }}</p><p class="text-slate-500">Terbuka</p></div>
                    <div><p class="font-black text-slate-900">{{ $agent->last_run_at?->diffForHumans() ?? '—' }}</p><p class="text-slate-500">Terakhir</p></div>
                    <div><p class="font-black text-slate-900">Rp {{ number_format($agent->daily_budget, 0, ',', '.') }}</p><p class="text-slate-500">Batas/hari</p></div>
                </div>
                <div class="mt-4 flex gap-2">
                    <form method="POST" action="{{ route('platform.ai-team.run', $agent) }}" class="flex-1">@csrf<button class="w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">Jalankan sekarang</button></form>
                    <button type="button" @click="$refs['settings{{ $agent->id }}'].classList.toggle('hidden')" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700">Atur</button>
                </div>
                <form x-ref="settings{{ $agent->id }}" method="POST" action="{{ route('platform.ai-team.agent', $agent) }}" class="mt-3 hidden grid grid-cols-[1fr_1fr_auto] gap-2 border-t border-slate-100 pt-3">@csrf @method('PATCH')
                    <select name="status" class="rounded-xl border-slate-300 text-xs"><option value="active" @selected($agent->status==='active')>Aktif</option><option value="paused" @selected($agent->status==='paused')>Jeda</option></select>
                    <input name="daily_budget" type="number" min="0" max="100000000" value="{{ $agent->daily_budget }}" class="rounded-xl border-slate-300 text-xs" aria-label="Batas harian">
                    <button class="rounded-xl bg-slate-900 px-3 text-xs font-bold text-white">Simpan</button>
                </form>
            </article>
            @endforeach
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[1.35fr_.65fr]">
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-black text-[#0B1B3F]">Antrean keputusan</h2><p class="mt-1 text-xs text-slate-500">Tidak ada tindakan eksternal yang dieksekusi diam-diam.</p></div>
            <div class="divide-y divide-slate-100">
                @forelse($tasks as $task)
                <article class="p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div><div class="flex flex-wrap items-center gap-2"><span class="text-xs font-black uppercase tracking-wider text-emerald-700">{{ $task->agent->name }}</span><span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-600">{{ $task->status }}</span><span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $task->risk === 'high' ? 'bg-red-100 text-red-700' : ($task->risk === 'medium' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-700') }}">Risiko {{ $task->risk }}</span></div><h3 class="mt-2 font-bold text-slate-900">{{ $task->title }}</h3></div>
                        <time class="text-xs text-slate-400">{{ $task->created_at->diffForHumans() }}</time>
                    </div>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $task->summary }}</p>
                    @if($task->recommendation)<div class="mt-3 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-950"><strong>Rekomendasi:</strong> {{ $task->recommendation }}</div>@endif
                    @if($task->status === 'proposed')
                    <div class="mt-4 flex gap-2">
                        <form method="POST" action="{{ route('platform.ai-team.decision', $task) }}">@csrf @method('PATCH')<input type="hidden" name="decision" value="approve"><button class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white">Setujui</button></form>
                        <form method="POST" action="{{ route('platform.ai-team.decision', $task) }}">@csrf @method('PATCH')<input type="hidden" name="decision" value="reject"><button class="rounded-xl border border-red-200 px-4 py-2 text-xs font-bold text-red-700">Tolak</button></form>
                    </div>
                    @endif
                </article>
                @empty <div class="p-10 text-center text-sm text-slate-400">Jalankan agen untuk membuat analisis pertama.</div> @endforelse
            </div>
        </section>

        <div class="space-y-6">
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-black text-[#0B1B3F]">Koneksi eksekusi</h2><p class="mt-1 text-xs leading-relaxed text-slate-500">Credential tidak disimpan pada tugas atau browser.</p>
                <div class="mt-4 space-y-3 text-sm">
                    @foreach ($connections as [$name, $ready])
                    <div class="flex items-center justify-between gap-3"><span class="font-semibold text-slate-700">{{ $name }}</span><span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $ready ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800' }}">{{ $ready ? 'Terhubung' : 'Perlu koneksi' }}</span></div>
                    @endforeach
                </div>
            </section>
            <section class="rounded-xl border border-slate-200 bg-[#0B1B3F] p-5 text-white shadow-sm">
                <h2 class="font-black">Batas keselamatan</h2>
                <ul class="mt-3 space-y-2 text-sm leading-relaxed text-slate-200"><li>• Campaign baru selalu perlu approval.</li><li>• Pesan massal dan scraping dilarang.</li><li>• Refund dan perubahan harga tidak otomatis.</li><li>• Semua keputusan dicatat di audit log.</li><li>• Agen dapat dijeda kapan saja.</li></ul>
            </section>
        </div>
    </div>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-black text-[#0B1B3F]">Data kerja terbaru</h2></div>
        <div class="overflow-x-auto"><table class="w-full min-w-[760px] text-sm"><thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-3">Jenis</th><th class="px-5 py-3">Judul</th><th class="px-5 py-3">Sumber</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Skor</th><th class="px-5 py-3">Nominal</th></tr></thead><tbody class="divide-y divide-slate-100">
            @forelse($records as $record)<tr><td class="px-5 py-4 font-bold uppercase text-emerald-700">{{ $record->kind }}</td><td class="px-5 py-4"><p class="font-semibold">{{ $record->title }}</p>@if(data_get($record->data,'notes'))<p class="mt-1 max-w-xl truncate text-xs text-slate-500">{{ data_get($record->data,'notes') }}</p>@endif</td><td class="px-5 py-4 text-slate-500">{{ $record->source }}</td><td class="px-5 py-4">{{ $record->status }}</td><td class="px-5 py-4">{{ $record->score ?? '—' }}</td><td class="px-5 py-4">{{ $record->amount !== null ? 'Rp '.number_format((float)$record->amount,0,',','.') : '—' }}</td></tr>
            @empty<tr><td colspan="6" class="px-5 py-10 text-center text-slate-400">Belum ada data kerja.</td></tr>@endforelse
        </tbody></table></div>
    </section>
</div>
@endsection
