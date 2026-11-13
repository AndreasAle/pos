@extends('layouts.platform')
@section('title', 'Tim AI')

@section('content')
<div x-data="{ intake: false, newTask: false, moveTask: null }" class="space-y-6">
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

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div><p class="text-xs font-bold uppercase tracking-[.16em] text-emerald-700">Marketing dan Sales / Tugas</p><h2 class="mt-1 text-xl font-black text-[#0B1B3F]">Kanban Tim AI</h2></div>
            <div class="flex gap-2"><span class="rounded-xl bg-slate-100 px-3 py-2 text-xs font-bold text-slate-600">{{ $tasks->count() }} tugas</span><button type="button" @click="newTask = true" class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white">+ Buat Tugas</button></div>
        </div>
        @php
            $kanban = [
                'proposed' => ['To Do List', 'bg-amber-400', 'Belum dikerjakan'],
                'approved' => ['Doing', 'bg-sky-500', 'Sedang dikerjakan'],
                'completed' => ['Done', 'bg-emerald-500', 'Selesai'],
                'rejected' => ['Blocked', 'bg-red-500', 'Ditolak / terhambat'],
            ];
            $agentColors = ['chief'=>'bg-emerald-600','marketing'=>'bg-cyan-500','ads'=>'bg-sky-600','finance'=>'bg-amber-500','accounting'=>'bg-teal-600','cs'=>'bg-rose-500','sales'=>'bg-violet-600'];
        @endphp
        <div class="overflow-x-auto bg-slate-100 p-4">
            <div class="grid min-w-[1180px] grid-cols-4 gap-3">
                @foreach($kanban as $status => [$label, $dot, $hint])
                @php $columnTasks = $tasks->where('status', $status); @endphp
                <div class="flex max-h-[720px] min-h-[520px] flex-col rounded-xl bg-slate-200/80">
                    <div class="flex items-center justify-between border-b border-slate-300 px-4 py-3"><div class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full {{ $dot }}"></span><h3 class="text-sm font-black text-slate-800">{{ $label }}</h3><span class="rounded-full bg-white px-2 py-0.5 text-[11px] font-bold text-slate-500">{{ $columnTasks->count() }}</span></div><span class="text-lg font-black text-slate-500">•••</span></div>
                    <div class="flex-1 space-y-3 overflow-y-auto p-3">
                        @forelse($columnTasks as $task)
                        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                            <div class="flex items-start justify-between gap-2"><span class="rounded-md px-2 py-1 text-[11px] font-black text-white {{ $agentColors[$task->agent->key] ?? 'bg-slate-600' }}">{{ $task->agent->name }}</span><button type="button" @click="moveTask = {{ $task->id }}" class="rounded-lg px-2 text-lg font-black text-slate-400 hover:bg-slate-100">•••</button></div>
                            <h4 class="mt-3 text-sm font-black leading-snug text-slate-900">[AI-{{ str_pad($task->id, 3, '0', STR_PAD_LEFT) }}] {{ $task->title }}</h4>
                            <p class="mt-2 line-clamp-3 text-xs leading-relaxed text-slate-500">{{ $task->summary }}</p>
                            <div class="mt-4 flex flex-wrap items-center gap-2 text-[11px] font-bold">
                                <span class="rounded-md px-2 py-1 {{ $task->risk === 'high' ? 'bg-red-100 text-red-700' : ($task->risk === 'medium' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-700') }}">{{ ucfirst($task->risk) }} risk</span>
                                <span class="text-slate-500">{{ $task->scheduled_for?->translatedFormat('d M') ?? $task->created_at->translatedFormat('d M') }}</span>
                                @if($task->requires_approval)<span class="text-slate-500">Approval</span>@endif
                            </div>
                            @if($task->recommendation)<div class="mt-3 border-l-2 border-emerald-400 pl-2 text-[11px] leading-relaxed text-slate-600">{{ $task->recommendation }}</div>@endif
                            <button type="button" @click="moveTask = {{ $task->id }}" class="mt-4 w-full rounded-lg border border-slate-200 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Pindahkan Tugas</button>
                        </article>
                        @empty<div class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-xs text-slate-400">{{ $hint }}</div>@endforelse
                    </div>
                    <button type="button" @click="newTask = true" class="border-t border-slate-300 px-4 py-3 text-left text-sm font-bold text-slate-600 hover:bg-slate-300/40">+ Buat Tugas</button>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
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
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="font-black text-[#0B1B3F]">Cara kerja board</h2><div class="mt-4 grid gap-3 sm:grid-cols-2"><div class="rounded-xl bg-amber-50 p-4 text-sm text-amber-950"><strong>To Do</strong><p class="mt-1 text-xs leading-relaxed">Usulan baru dan pekerjaan yang menunggu keputusan.</p></div><div class="rounded-xl bg-sky-50 p-4 text-sm text-sky-950"><strong>Doing</strong><p class="mt-1 text-xs leading-relaxed">Sudah disetujui dan sedang diproses.</p></div><div class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-950"><strong>Done</strong><p class="mt-1 text-xs leading-relaxed">Analisis atau pekerjaan internal selesai.</p></div><div class="rounded-xl bg-red-50 p-4 text-sm text-red-950"><strong>Blocked</strong><p class="mt-1 text-xs leading-relaxed">Ditolak, gagal, atau menunggu integrasi.</p></div></div></section>
    </div>

    <div x-show="newTask" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/50 p-4" @keydown.escape.window="newTask=false">
        <div @click.outside="newTask=false" class="w-full max-w-xl rounded-xl bg-white p-6 shadow-2xl"><div class="flex items-center justify-between"><div><p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Tugas baru</p><h2 class="mt-1 text-xl font-black text-[#0B1B3F]">Delegasikan ke Tim AI</h2></div><button type="button" @click="newTask=false" class="text-2xl text-slate-400">×</button></div>
            <form method="POST" action="{{ route('platform.ai-team.tasks.store') }}" class="mt-5 space-y-4">@csrf
                <label class="block text-sm font-bold text-slate-700">Pilih agen<select name="platform_ai_agent_id" required class="mt-1.5 w-full rounded-xl border-slate-300 text-sm">@foreach($agents as $agent)<option value="{{ $agent->id }}">{{ $agent->name }} · {{ $agent->department }}</option>@endforeach</select></label>
                <label class="block text-sm font-bold text-slate-700">Nama tugas<input name="title" required maxlength="180" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm" placeholder="Contoh: Analisis 10 video TikTok terakhir"></label>
                <label class="block text-sm font-bold text-slate-700">Instruksi<textarea name="summary" required maxlength="2000" rows="4" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm" placeholder="Jelaskan hasil yang kamu harapkan"></textarea></label>
                <div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-bold text-slate-700">Risiko<select name="risk" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm"><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option></select></label><label class="text-sm font-bold text-slate-700">Target selesai<input name="scheduled_for" type="datetime-local" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm"></label></div>
                <button class="w-full rounded-xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white hover:bg-emerald-700">Masukkan ke To Do</button>
            </form>
        </div>
    </div>

    <div x-show="moveTask !== null" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/50 p-4" @keydown.escape.window="moveTask=null">
        @foreach($tasks as $task)
        <form x-show="moveTask === {{ $task->id }}" @click.outside="moveTask=null" method="POST" action="{{ route('platform.ai-team.tasks.move', $task) }}" class="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl">@csrf @method('PATCH')
            <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Pindahkan tugas</p><h2 class="mt-1 text-lg font-black text-[#0B1B3F]">{{ $task->title }}</h2></div><button type="button" @click="moveTask=null" class="text-2xl text-slate-400">×</button></div>
            <label class="mt-5 block text-sm font-bold text-slate-700">Pilih list tujuan<select name="status" required class="mt-1.5 w-full rounded-xl border-slate-300 text-sm"><option value="proposed" @selected($task->status==='proposed')>To Do List</option><option value="approved" @selected($task->status==='approved')>Doing</option><option value="completed" @selected($task->status==='completed')>Done</option><option value="rejected" @selected($task->status==='rejected')>Blocked</option></select></label>
            <div class="mt-4 rounded-xl bg-slate-50 p-3 text-xs text-slate-500">Tim: <strong>{{ $task->agent->name }}</strong> · Risiko: <strong>{{ ucfirst($task->risk) }}</strong></div>
            <button class="mt-5 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white hover:bg-emerald-700">Pindahkan</button>
        </form>
        @endforeach
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
