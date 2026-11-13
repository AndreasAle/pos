@extends('layouts.platform')
@section('title', 'Operasional Tim')

@section('content')
@php
    $kanban = [
        'proposed' => ['To Do', 'Menunggu persetujuan', 'bg-amber-500'],
        'approved' => ['In Progress', 'Sedang dikerjakan', 'bg-sky-500'],
        'completed' => ['Completed', 'Pekerjaan selesai', 'bg-emerald-500'],
        'rejected' => ['Blocked', 'Ditolak atau terhambat', 'bg-rose-500'],
    ];
    $agentStyles = [
        'chief' => 'border-emerald-200 bg-emerald-50 text-emerald-800', 'marketing' => 'border-cyan-200 bg-cyan-50 text-cyan-800',
        'ads' => 'border-sky-200 bg-sky-50 text-sky-800', 'finance' => 'border-amber-200 bg-amber-50 text-amber-800',
        'accounting' => 'border-teal-200 bg-teal-50 text-teal-800', 'cs' => 'border-rose-200 bg-rose-50 text-rose-800',
        'sales' => 'border-violet-200 bg-violet-50 text-violet-800',
    ];
    $openTasks = $tasks->whereIn('status', ['proposed', 'approved'])->count();
@endphp

<div x-data="{ tab: 'board', newTask: false, moveTask: null, intake: false, search: '' }" class="space-y-5">
    <header class="rounded-xl border border-slate-200 bg-white px-5 py-5 shadow-sm sm:px-6">
        <div class="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold text-slate-500"><span>Yoka Platform</span><span class="text-slate-300">/</span><span>Operasional</span><span class="text-slate-300">/</span><span class="text-emerald-700">Tim</span></div>
                <h1 class="mt-2 text-2xl font-black tracking-tight text-[#0B1B3F] sm:text-3xl">Pusat Operasional Yoka</h1>
                <p class="mt-1 text-sm text-slate-500">Kelola pekerjaan, anggota tim digital, dan data operasional dari satu tempat.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="intake=true; tab='data'" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">Tambah data</button>
                <button type="button" @click="newTask=true" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-emerald-700">Buat tugas baru</button>
            </div>
        </div>
    </header>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Pekerjaan aktif', $openTasks, 'Tugas yang perlu ditangani', 'text-[#0B1B3F]'],
            ['Selesai', $tasks->where('status','completed')->count(), 'Dari 30 aktivitas terbaru', 'text-emerald-700'],
            ['Terhambat', $tasks->where('status','rejected')->count(), 'Perlu keputusan atau koneksi', 'text-rose-700'],
            ['Agen aktif', $agents->where('status','active')->count(), 'Dari '.$agents->count().' anggota tim', 'text-sky-700'],
        ] as [$label, $value, $description, $color])
        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-[.14em] text-slate-500">{{ $label }}</p>
            <div class="mt-3 flex items-end justify-between gap-3"><p class="text-3xl font-black {{ $color }}">{{ $value }}</p><span class="h-2 w-2 rounded-full bg-emerald-500"></span></div>
            <p class="mt-2 text-xs text-slate-500">{{ $description }}</p>
        </article>
        @endforeach
    </section>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <nav class="flex overflow-x-auto border-b border-slate-200 px-3 sm:px-5">
            @foreach ([['board','Papan tugas'],['team','Anggota tim'],['data','Data & integrasi']] as [$key,$label])
            <button type="button" @click="tab='{{ $key }}'" class="whitespace-nowrap border-b-2 px-4 py-4 text-sm font-bold transition" :class="tab === '{{ $key }}' ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-500 hover:text-slate-800'">{{ $label }}</button>
            @endforeach
        </nav>

        <div x-show="tab === 'board'" x-cloak>
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                <div><h2 class="font-black text-[#0B1B3F]">Papan tugas</h2><p class="mt-0.5 text-xs text-slate-500">Klik kartu untuk memperbarui progres.</p></div>
                <label class="relative block w-full lg:w-72"><span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35m1.35-5.65a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/></svg></span><input x-model="search" class="w-full rounded-xl border-slate-300 py-2.5 pl-9 pr-3 text-sm" placeholder="Cari tugas atau anggota tim"></label>
            </div>
            <div class="overflow-x-auto bg-slate-100 p-4 sm:p-5">
                <div class="grid min-w-[1120px] grid-cols-4 gap-4">
                    @foreach($kanban as $status => [$label, $description, $dot])
                    @php $columnTasks = $tasks->where('status', $status); @endphp
                    <section class="flex min-h-[570px] flex-col rounded-xl border border-slate-200 bg-slate-50">
                        <header class="border-b border-slate-200 px-4 py-3.5"><div class="flex items-center justify-between"><div class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full {{ $dot }}"></span><h3 class="text-sm font-black text-slate-900">{{ $label }}</h3></div><span class="rounded-full bg-slate-200 px-2 py-0.5 text-[11px] font-black text-slate-600">{{ $columnTasks->count() }}</span></div><p class="mt-1 text-[11px] text-slate-500">{{ $description }}</p></header>
                        <div class="flex-1 space-y-3 p-3">
                            @forelse($columnTasks as $task)
                            @php $searchText = strtolower($task->title.' '.$task->summary.' '.$task->agent->name); @endphp
                            <article x-show="!search || @js($searchText).includes(search.toLowerCase())" class="group rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-slate-300 hover:shadow-md">
                                <div class="flex items-center justify-between gap-3"><span class="rounded-md border px-2 py-1 text-[10px] font-black uppercase tracking-wide {{ $agentStyles[$task->agent->key] ?? 'border-slate-200 bg-slate-50 text-slate-700' }}">{{ $task->agent->name }}</span><button type="button" @click="moveTask={{ $task->id }}" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700"><svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><circle cx="4" cy="10" r="1.5"/><circle cx="10" cy="10" r="1.5"/><circle cx="16" cy="10" r="1.5"/></svg></button></div>
                                <button type="button" @click="moveTask={{ $task->id }}" class="mt-3 block w-full text-left"><h4 class="text-sm font-black leading-snug text-slate-900">{{ $task->title }}</h4><p class="mt-2 line-clamp-3 text-xs leading-relaxed text-slate-500">{{ $task->summary }}</p></button>
                                @if($task->recommendation)<div class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-[11px] leading-relaxed text-slate-600">{{ $task->recommendation }}</div>@endif
                                <footer class="mt-4 flex items-center justify-between gap-3 border-t border-slate-100 pt-3 text-[11px]"><div class="flex items-center gap-2"><span class="font-bold {{ $task->risk === 'high' ? 'text-rose-700' : ($task->risk === 'medium' ? 'text-amber-700' : 'text-emerald-700') }}">{{ ucfirst($task->risk) }}</span>@if($task->requires_approval)<span class="text-slate-400">Approval</span>@endif</div><time class="font-semibold text-slate-500">{{ $task->scheduled_for?->translatedFormat('d M') ?? $task->created_at->translatedFormat('d M') }}</time></footer>
                            </article>
                            @empty<div class="rounded-xl border border-dashed border-slate-300 px-4 py-10 text-center text-xs font-semibold text-slate-400">Belum ada tugas</div>@endforelse
                        </div>
                        <button type="button" @click="newTask=true" class="m-3 mt-0 rounded-xl border border-dashed border-slate-300 px-4 py-2.5 text-left text-xs font-bold text-slate-500 hover:border-emerald-400 hover:bg-emerald-50 hover:text-emerald-700">+ Tambah tugas</button>
                    </section>
                    @endforeach
                </div>
            </div>
        </div>

        <div x-show="tab === 'team'" x-cloak class="p-5 sm:p-6">
            <div class="mb-5"><h2 class="font-black text-[#0B1B3F]">Anggota tim</h2><p class="mt-1 text-sm text-slate-500">Atur status, batas anggaran, dan jalankan analisis sesuai kebutuhan.</p></div>
            <div class="overflow-hidden rounded-xl border border-slate-200">
                <div class="hidden grid-cols-[1.4fr_1fr_1fr_1fr_auto] gap-4 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wider text-slate-500 lg:grid"><span>Anggota</span><span>Status</span><span>Aktivitas</span><span>Batas harian</span><span>Tindakan</span></div>
                <div class="divide-y divide-slate-200">
                    @foreach($agents as $agent)
                    <article class="grid gap-4 px-5 py-5 lg:grid-cols-[1.4fr_1fr_1fr_1fr_auto] lg:items-center">
                        <div><div class="flex items-center gap-2"><span class="rounded-md border px-2 py-1 text-[10px] font-black uppercase {{ $agentStyles[$agent->key] ?? 'border-slate-200 bg-slate-50 text-slate-700' }}">{{ $agent->department }}</span><h3 class="font-black text-slate-900">{{ $agent->name }}</h3></div><p class="mt-1.5 max-w-lg text-xs leading-relaxed text-slate-500">{{ $agent->mission }}</p></div>
                        <div><span class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-bold {{ $agent->status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}"><span class="h-1.5 w-1.5 rounded-full {{ $agent->status === 'active' ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>{{ $agent->status === 'active' ? 'Aktif' : 'Dijeda' }}</span></div>
                        <div class="text-xs"><p class="font-bold text-slate-800">{{ $agent->open_tasks_count }} tugas terbuka</p><p class="mt-1 text-slate-500">{{ $agent->last_run_at?->diffForHumans() ?? 'Belum dijalankan' }}</p></div><p class="text-sm font-black text-slate-800">Rp {{ number_format($agent->daily_budget,0,',','.') }}</p>
                        <div class="flex gap-2"><form method="POST" action="{{ route('platform.ai-team.run',$agent) }}">@csrf<button class="rounded-lg bg-[#0B1B3F] px-3 py-2 text-xs font-bold text-white">Jalankan</button></form><button type="button" @click="$refs['agent{{ $agent->id }}'].classList.toggle('hidden')" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700">Atur</button></div>
                        <form x-ref="agent{{ $agent->id }}" method="POST" action="{{ route('platform.ai-team.agent',$agent) }}" class="hidden rounded-xl bg-slate-50 p-3 lg:col-span-5 lg:grid lg:grid-cols-[180px_220px_auto] lg:gap-3">@csrf @method('PATCH')<select name="status" class="rounded-lg border-slate-300 text-sm"><option value="active" @selected($agent->status==='active')>Aktif</option><option value="paused" @selected($agent->status==='paused')>Jeda</option></select><input name="daily_budget" type="number" min="0" max="100000000" value="{{ $agent->daily_budget }}" class="rounded-lg border-slate-300 text-sm"><button class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white">Simpan pengaturan</button></form>
                    </article>
                    @endforeach
                </div>
            </div>
        </div>

        <div x-show="tab === 'data'" x-cloak class="p-5 sm:p-6">
            <div class="grid gap-6 xl:grid-cols-[1fr_340px]">
                <div>
                    <div class="flex items-center justify-between gap-4"><div><h2 class="font-black text-[#0B1B3F]">Data operasional</h2><p class="mt-1 text-sm text-slate-500">Sumber informasi yang digunakan anggota tim untuk mengambil keputusan.</p></div><button type="button" @click="intake=!intake" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white">Tambah data</button></div>
                    <form x-show="intake" x-transition method="POST" action="{{ route('platform.ai-team.records') }}" class="mt-5 grid gap-4 rounded-xl border border-slate-200 bg-slate-50 p-5 md:grid-cols-2">@csrf
                        <label class="text-xs font-bold text-slate-700">Jenis<select name="kind" required class="mt-1.5 w-full rounded-lg border-slate-300 text-sm"><option value="content">Konten</option><option value="campaign">Campaign</option><option value="lead">Lead sales</option><option value="ticket">Tiket CS</option><option value="finance">Keuangan</option></select></label><label class="text-xs font-bold text-slate-700">Judul<input name="title" required maxlength="180" class="mt-1.5 w-full rounded-lg border-slate-300 text-sm"></label>
                        <label class="text-xs font-bold text-slate-700">Status<select name="status" required class="mt-1.5 w-full rounded-lg border-slate-300 text-sm"><option value="new">Baru</option><option value="active">Aktif</option><option value="expense">Biaya</option><option value="income">Pendapatan</option><option value="resolved">Selesai</option></select></label><label class="text-xs font-bold text-slate-700">Sumber<input name="source" required value="manual" class="mt-1.5 w-full rounded-lg border-slate-300 text-sm"></label>
                        <label class="text-xs font-bold text-slate-700">Skor<input name="score" type="number" min="0" max="100" class="mt-1.5 w-full rounded-lg border-slate-300 text-sm"></label><label class="text-xs font-bold text-slate-700">Nominal<input name="amount" type="number" min="0" class="mt-1.5 w-full rounded-lg border-slate-300 text-sm"></label>
                        <label class="text-xs font-bold text-slate-700 md:col-span-2">URL<input name="url" type="url" class="mt-1.5 w-full rounded-lg border-slate-300 text-sm"></label><label class="text-xs font-bold text-slate-700 md:col-span-2">Catatan<textarea name="notes" rows="3" maxlength="2000" class="mt-1.5 w-full rounded-lg border-slate-300 text-sm"></textarea></label><button class="rounded-lg bg-[#0B1B3F] px-4 py-2.5 text-xs font-bold text-white md:col-span-2">Simpan data</button>
                    </form>
                    <div class="mt-5 overflow-x-auto rounded-xl border border-slate-200"><table class="w-full min-w-[700px] text-sm"><thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500"><tr><th class="px-4 py-3">Jenis</th><th class="px-4 py-3">Judul</th><th class="px-4 py-3">Sumber</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Skor</th><th class="px-4 py-3">Nominal</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($records as $record)<tr><td class="px-4 py-3 text-xs font-black uppercase text-emerald-700">{{ $record->kind }}</td><td class="px-4 py-3 font-semibold text-slate-800">{{ $record->title }}</td><td class="px-4 py-3 text-slate-500">{{ $record->source }}</td><td class="px-4 py-3 text-slate-600">{{ $record->status }}</td><td class="px-4 py-3">{{ $record->score ?? '—' }}</td><td class="px-4 py-3">{{ $record->amount !== null ? 'Rp '.number_format((float)$record->amount,0,',','.') : '—' }}</td></tr>@empty<tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Belum ada data.</td></tr>@endforelse</tbody></table></div>
                </div>
                <aside class="space-y-4"><div class="rounded-xl border border-slate-200 p-5"><h3 class="font-black text-[#0B1B3F]">Status integrasi</h3><div class="mt-4 space-y-3">@foreach($connections as [$name,$ready])<div class="flex items-center justify-between gap-3 text-sm"><span class="font-semibold text-slate-700">{{ $name }}</span><span class="rounded-full px-2 py-1 text-[10px] font-black {{ $ready ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $ready ? 'Terhubung' : 'Belum terhubung' }}</span></div>@endforeach</div></div><div class="rounded-xl bg-[#0B1B3F] p-5 text-white"><h3 class="font-black">Kontrol & keamanan</h3><ul class="mt-3 space-y-2 text-xs leading-relaxed text-slate-300"><li>Campaign baru memerlukan persetujuan.</li><li>Pesan massal dan scraping tidak diizinkan.</li><li>Refund dan perubahan harga tidak otomatis.</li><li>Seluruh tindakan dicatat dalam audit log.</li></ul></div></aside>
            </div>
        </div>
    </section>

    <div x-show="newTask" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/55 p-4" @keydown.escape.window="newTask=false">
        <div @click.outside="newTask=false" class="w-full max-w-xl rounded-xl bg-white shadow-2xl"><header class="flex items-start justify-between border-b border-slate-200 px-6 py-5"><div><p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Tugas baru</p><h2 class="mt-1 text-xl font-black text-[#0B1B3F]">Delegasikan pekerjaan</h2></div><button type="button" @click="newTask=false" class="rounded-lg p-1 text-2xl text-slate-400 hover:bg-slate-100">×</button></header><form method="POST" action="{{ route('platform.ai-team.tasks.store') }}" class="space-y-4 p-6">@csrf<label class="block text-sm font-bold text-slate-700">Anggota tim<select name="platform_ai_agent_id" required class="mt-1.5 w-full rounded-xl border-slate-300 text-sm">@foreach($agents as $agent)<option value="{{ $agent->id }}">{{ $agent->name }} · {{ $agent->department }}</option>@endforeach</select></label><label class="block text-sm font-bold text-slate-700">Nama tugas<input name="title" required maxlength="180" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm" placeholder="Apa yang perlu dikerjakan?"></label><label class="block text-sm font-bold text-slate-700">Instruksi<textarea name="summary" required maxlength="2000" rows="4" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm" placeholder="Jelaskan hasil yang diharapkan dan batasannya"></textarea></label><div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-bold text-slate-700">Tingkat risiko<select name="risk" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm"><option value="low">Rendah</option><option value="medium">Sedang</option><option value="high">Tinggi</option></select></label><label class="text-sm font-bold text-slate-700">Target selesai<input name="scheduled_for" type="datetime-local" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm"></label></div><div class="flex justify-end gap-2 border-t border-slate-100 pt-4"><button type="button" @click="newTask=false" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700">Batal</button><button class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white">Buat tugas</button></div></form></div>
    </div>

    <div x-show="moveTask !== null" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/55 p-4" @keydown.escape.window="moveTask=null">
        @foreach($tasks as $task)
        <form x-show="moveTask === {{ $task->id }}" @click.outside="moveTask=null" method="POST" action="{{ route('platform.ai-team.tasks.move',$task) }}" class="w-full max-w-md rounded-xl bg-white shadow-2xl">@csrf @method('PATCH')<header class="flex items-start justify-between border-b border-slate-200 px-6 py-5"><div><p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Perbarui progres</p><h2 class="mt-1 text-lg font-black leading-snug text-[#0B1B3F]">{{ $task->title }}</h2></div><button type="button" @click="moveTask=null" class="rounded-lg p-1 text-2xl text-slate-400 hover:bg-slate-100">×</button></header><div class="space-y-4 p-6"><div class="rounded-xl bg-slate-50 p-4 text-xs text-slate-600"><p><span class="text-slate-400">Anggota</span><strong class="mt-1 block text-sm text-slate-800">{{ $task->agent->name }}</strong></p><p class="mt-3 leading-relaxed">{{ $task->summary }}</p></div><label class="block text-sm font-bold text-slate-700">Pindahkan ke<select name="status" required class="mt-1.5 w-full rounded-xl border-slate-300 text-sm"><option value="proposed" @selected($task->status==='proposed')>To Do</option><option value="approved" @selected($task->status==='approved')>In Progress</option><option value="completed" @selected($task->status==='completed')>Completed</option><option value="rejected" @selected($task->status==='rejected')>Blocked</option></select></label><div class="flex justify-end gap-2 border-t border-slate-100 pt-4"><button type="button" @click="moveTask=null" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700">Batal</button><button class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white">Simpan perubahan</button></div></div></form>
        @endforeach
    </div>
</div>
@endsection
