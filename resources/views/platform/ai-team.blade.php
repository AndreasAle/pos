@extends('layouts.platform')
@section('title', 'Tim AI')

@section('content')
@php
    $agentStyles = [
        'chief' => ['bg-emerald-900','bg-emerald-50 text-emerald-800 border-emerald-200'],
        'marketing' => ['bg-cyan-700','bg-cyan-50 text-cyan-800 border-cyan-200'],
        'ads' => ['bg-sky-700','bg-sky-50 text-sky-800 border-sky-200'],
        'finance' => ['bg-amber-600','bg-amber-50 text-amber-800 border-amber-200'],
        'accounting' => ['bg-teal-700','bg-teal-50 text-teal-800 border-teal-200'],
        'cs' => ['bg-rose-700','bg-rose-50 text-rose-800 border-rose-200'],
        'sales' => ['bg-violet-700','bg-violet-50 text-violet-800 border-violet-200'],
    ];
    $selectedAgentId = (int) request('agent', $agents->first()?->id);
    if (! $agents->contains('id', $selectedAgentId)) $selectedAgentId = (int) $agents->first()?->id;
    $chatPayload = $conversations->map(fn ($items) => $items->map(fn ($message) => [
        'id' => $message->id, 'role' => $message->role, 'content' => $message->content,
        'mode' => data_get($message->meta, 'mode'), 'admin' => $message->admin?->name,
        'time' => $message->created_at->translatedFormat('d M, H:i'),
    ])->values())->all();
    $routePayload = $agents->mapWithKeys(fn ($agent) => [$agent->id => route('platform.ai-team.chat', $agent)])->all();
    $readRoutePayload = $agents->mapWithKeys(fn ($agent) => [$agent->id => route('platform.ai-team.read', $agent)])->all();
    $unreadPayload = $agents->mapWithKeys(fn ($agent) => [$agent->id => (int) ($unreadCounts[$agent->id] ?? 0)])->all();
    $promptPayload = $agents->mapWithKeys(fn ($agent) => [$agent->id => data_get($profiles, $agent->key.'.prompts', [])])->all();
    $openTasks = $tasks->whereIn('status', ['proposed','approved'])->count();
    $yokaConnection = collect($connections)->firstWhere(0, 'Yoka AI');
    $aiReady = $yokaConnection[1] ?? false;
@endphp

<div
    x-data="aiTeamWorkspace(@js($selectedAgentId), @js($chatPayload), @js($routePayload), @js($readRoutePayload), @js($unreadPayload), @js($promptPayload))"
    class="space-y-5"
>
    <header class="rounded-xl border border-slate-200 bg-white px-5 py-5 shadow-sm sm:px-6">
        <div class="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold text-slate-500"><span>Yoka Platform</span><span class="text-slate-300">/</span><span class="text-emerald-700">Tim AI</span></div>
                <h1 class="mt-2 text-2xl font-black tracking-tight text-[#0B1B3F] sm:text-3xl">Ruang Kerja Tim AI</h1>
                <p class="mt-1 max-w-2xl text-sm text-slate-500">Bicara langsung dengan setiap agen, lihat dasar jawabannya, lalu ubah hasil diskusi menjadi pekerjaan yang dapat dipantau.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="tab='data'; intake=true" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">Tambah data</button>
                <button type="button" @click="newTask=true" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-emerald-700">Buat tugas</button>
            </div>
        </div>
    </header>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><div class="flex items-center justify-between"><p class="text-xs font-bold uppercase tracking-wider text-slate-500">Mesin AI</p><span class="h-2.5 w-2.5 rounded-full {{ $aiReady ? 'bg-emerald-500' : 'bg-amber-500' }}"></span></div><p class="mt-2 text-lg font-black text-[#0B1B3F]">{{ $aiReady ? 'Terhubung' : 'Mode internal' }}</p><p class="mt-1 text-xs text-slate-500">{{ $aiReady ? 'Jawaban memakai model dan data Yoka' : 'Chat tersimpan, jawaban memakai data terstruktur' }}</p></article>
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-bold uppercase tracking-wider text-slate-500">Agen tersedia</p><p class="mt-2 text-2xl font-black text-sky-700">{{ $agents->where('status','active')->count() }}<span class="text-sm text-slate-400"> / {{ $agents->count() }}</span></p><p class="mt-1 text-xs text-slate-500">Masing-masing punya mandat dan histori sendiri</p></article>
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-bold uppercase tracking-wider text-slate-500">Perlu keputusan</p><p class="mt-2 text-2xl font-black text-amber-700">{{ $tasks->where('status','proposed')->count() }}</p><p class="mt-1 text-xs text-slate-500">Tidak dieksekusi tanpa persetujuan</p></article>
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-bold uppercase tracking-wider text-slate-500">Pekerjaan berjalan</p><p class="mt-2 text-2xl font-black text-emerald-700">{{ $openTasks }}</p><p class="mt-1 text-xs text-slate-500">{{ $tasks->where('status','completed')->count() }} pekerjaan sudah selesai</p></article>
    </section>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <nav class="flex overflow-x-auto border-b border-slate-200 px-3 sm:px-5">
            @foreach ([['workspace','Ruang agen'],['tasks','Pekerjaan'],['team','Pengaturan agen'],['data','Data & integrasi']] as [$key,$label])
                <button type="button" @click="tab='{{ $key }}'" class="whitespace-nowrap border-b-2 px-4 py-4 text-sm font-bold transition" :class="tab === '{{ $key }}' ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-500 hover:text-slate-800'">{{ $label }}</button>
            @endforeach
        </nav>

        <div x-show="tab === 'workspace'" x-cloak class="grid min-h-[680px] xl:grid-cols-[270px_minmax(420px,1fr)_310px]">
            <aside class="border-b border-slate-200 bg-slate-50 xl:border-b-0 xl:border-r">
                <div class="border-b border-slate-200 px-4 py-4"><h2 class="font-black text-[#0B1B3F]">Pilih anggota</h2><p class="mt-1 text-xs text-slate-500">Setiap agen memiliki konteks terpisah.</p></div>
                <div class="flex gap-2 overflow-x-auto p-3 xl:block xl:space-y-1 xl:overflow-visible">
                    @foreach($agents as $agent)
                        @php [$avatar,$badge] = $agentStyles[$agent->key] ?? ['bg-slate-700','bg-slate-50 text-slate-700 border-slate-200']; @endphp
                        <button type="button" @click="selectAgent({{ $agent->id }})" class="min-w-[230px] rounded-xl border p-3 text-left transition xl:w-full xl:min-w-0" :class="activeAgent === {{ $agent->id }} ? 'border-emerald-300 bg-white shadow-sm' : 'border-transparent hover:bg-white'">
                            <span class="flex items-center gap-3"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-xs font-black text-white {{ $avatar }}">{{ data_get($profiles,$agent->key.'.initials','AI') }}</span><span class="min-w-0 flex-1"><b class="block truncate text-sm text-slate-900">{{ $agent->name }}</b><small class="mt-0.5 block truncate text-slate-500">{{ data_get($profiles,$agent->key.'.title',$agent->department) }}</small></span><span x-show="unread[{{ $agent->id }}] > 0" x-text="unread[{{ $agent->id }}]" class="flex h-6 min-w-6 items-center justify-center rounded-full bg-emerald-600 px-1.5 text-[10px] font-black text-white"></span><span x-show="unread[{{ $agent->id }}] === 0" class="h-2 w-2 rounded-full {{ $agent->status === 'active' ? 'bg-emerald-500' : 'bg-slate-400' }}"></span></span>
                            <span class="mt-3 flex items-center justify-between text-[11px]"><span class="text-slate-500">{{ $agent->open_tasks_count }} tugas terbuka</span><span class="font-bold {{ data_get($snapshots,$agent->id.'.ai_connected') ? 'text-emerald-700' : 'text-amber-700' }}">{{ data_get($snapshots,$agent->id.'.ai_connected') ? 'AI siap' : 'Mode internal' }}</span></span>
                        </button>
                    @endforeach
                </div>
            </aside>

            <div class="flex min-h-[680px] min-w-0 flex-col bg-white">
                @foreach($agents as $agent)
                    @php [$avatar,$badge] = $agentStyles[$agent->key] ?? ['bg-slate-700','bg-slate-50 text-slate-700 border-slate-200']; @endphp
                    <template x-if="activeAgent === {{ $agent->id }}">
                        <div class="flex min-h-0 flex-1 flex-col">
                            <header class="flex items-center gap-3 border-b border-slate-200 px-4 py-4 sm:px-5">
                                <span class="flex h-11 w-11 items-center justify-center rounded-xl text-xs font-black text-white {{ $avatar }}">{{ data_get($profiles,$agent->key.'.initials','AI') }}</span>
                                <div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><h2 class="font-black text-[#0B1B3F]">{{ $agent->name }}</h2><span class="rounded-full border px-2 py-0.5 text-[10px] font-black {{ $badge }}">{{ $agent->department }}</span></div><p class="mt-0.5 truncate text-xs text-slate-500">{{ $agent->mission }}</p></div>
                                <form method="POST" action="{{ route('platform.ai-team.run',$agent) }}">@csrf<button class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Analisis sekarang</button></form>
                            </header>

                            <div x-ref="messageArea{{ $agent->id }}" class="flex-1 space-y-5 overflow-y-auto bg-slate-50/60 px-4 py-5 sm:px-6">
                                <div x-show="(messages[{{ $agent->id }}] || []).length === 0" class="mx-auto max-w-lg rounded-xl border border-slate-200 bg-white p-5 text-center">
                                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl text-sm font-black text-white {{ $avatar }}">{{ data_get($profiles,$agent->key.'.initials','AI') }}</span>
                                    <h3 class="mt-3 font-black text-[#0B1B3F]">Mulai diskusi dengan {{ $agent->name }}</h3>
                                    <p class="mt-2 text-sm leading-relaxed text-slate-500">Agen akan menggunakan data operasional yang tersedia. Jika koneksi belum lengkap, keterbatasannya akan disebutkan dalam jawaban.</p>
                                </div>
                                <template x-for="message in (messages[{{ $agent->id }}] || [])" :key="message.id">
                                    <div class="flex" :class="message.role === 'user' ? 'justify-end' : 'justify-start'">
                                        <div class="max-w-[88%]">
                                            <div class="rounded-2xl px-4 py-3 text-sm leading-relaxed shadow-sm" :class="message.role === 'user' ? 'rounded-br-md bg-emerald-700 text-white' : 'rounded-bl-md border border-slate-200 bg-white text-slate-700'"><p class="whitespace-pre-wrap" x-text="message.content"></p></div>
                                            <div class="mt-1 flex items-center gap-2 px-1 text-[10px] text-slate-400" :class="message.role === 'user' ? 'justify-end' : ''"><span x-text="message.role === 'user' ? (message.admin || 'Anda') : '{{ $agent->name }}'"></span><span>·</span><span x-text="message.time || 'Baru saja'"></span><span x-show="message.mode" class="rounded bg-slate-200 px-1.5 py-0.5 font-bold text-slate-600" x-text="modeLabel(message.mode)"></span></div>
                                        </div>
                                    </div>
                                </template>
                                <div x-show="sending && activeAgent === {{ $agent->id }}" class="flex justify-start"><div class="rounded-2xl rounded-bl-md border border-slate-200 bg-white px-4 py-3 text-sm text-slate-500 shadow-sm"><span class="inline-flex items-center gap-2"><span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span>{{ $agent->name }} sedang membaca data…</span></div></div>
                            </div>

                            <footer class="border-t border-slate-200 bg-white p-4">
                                <div class="mb-3 flex gap-2 overflow-x-auto pb-1">
                                    @foreach(data_get($profiles,$agent->key.'.prompts',[]) as $prompt)<button type="button" @click="sendPrompt(@js($prompt))" class="whitespace-nowrap rounded-full border border-slate-300 px-3 py-1.5 text-[11px] font-bold text-slate-600 hover:border-emerald-400 hover:text-emerald-700">{{ $prompt }}</button>@endforeach
                                </div>
                                <form @submit.prevent="sendMessage()" class="flex items-end gap-2">
                                    <textarea x-model="draft" @keydown.enter.exact.prevent="sendMessage()" rows="1" maxlength="2000" class="max-h-32 min-h-[48px] flex-1 resize-y rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="Tulis instruksi atau pertanyaan…"></textarea>
                                    <button type="submit" :disabled="sending || !draft.trim()" class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white disabled:cursor-not-allowed disabled:opacity-40" aria-label="Kirim pesan"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 12 14-7-4 14-3-6-7-1Z"/></svg></button>
                                </form>
                                <p x-show="chatError" x-text="chatError" class="mt-2 text-xs text-rose-700"></p>
                            </footer>
                        </div>
                    </template>
                @endforeach
            </div>

            <aside class="border-t border-slate-200 p-5 xl:border-l xl:border-t-0">
                @foreach($agents as $agent)
                    <div x-show="activeAgent === {{ $agent->id }}" x-cloak class="space-y-5">
                        <div><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Mandat kerja</p><p class="mt-2 text-sm leading-relaxed text-slate-700">{{ $agent->mission }}</p></div>
                        <div class="rounded-xl border border-slate-200 p-4"><h3 class="text-sm font-black text-[#0B1B3F]">Kesiapan agen</h3><div class="mt-3 space-y-3 text-xs">
                            <div class="flex items-center justify-between"><span class="text-slate-500">Status</span><b class="{{ $agent->status === 'active' ? 'text-emerald-700' : 'text-slate-500' }}">{{ $agent->status === 'active' ? 'Aktif' : 'Dijeda' }}</b></div>
                            <div class="flex items-center justify-between"><span class="text-slate-500">Model AI</span><b class="{{ data_get($snapshots,$agent->id.'.ai_connected') ? 'text-emerald-700' : 'text-amber-700' }}">{{ data_get($snapshots,$agent->id.'.ai_connected') ? 'Terhubung' : 'Belum terhubung' }}</b></div>
                            @if(data_get($snapshots,$agent->id.'.external_connection') !== null)<div class="flex items-center justify-between"><span class="text-slate-500">Kanal eksternal</span><b class="{{ data_get($snapshots,$agent->id.'.external_connection') ? 'text-emerald-700' : 'text-amber-700' }}">{{ data_get($snapshots,$agent->id.'.external_connection') ? 'Terhubung' : 'Belum terhubung' }}</b></div>@endif
                            <div class="flex items-center justify-between"><span class="text-slate-500">Tugas terbuka</span><b>{{ data_get($snapshots,$agent->id.'.open_tasks',0) }}</b></div>
                            <div class="flex items-center justify-between"><span class="text-slate-500">Terakhir bekerja</span><b>{{ $agent->last_run_at?->diffForHumans() ?? 'Belum pernah' }}</b></div>
                        </div></div>
                        <div><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Akses yang diizinkan</p><div class="mt-2 flex flex-wrap gap-2">@foreach($agent->permissions ?? [] as $permission)<span class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-bold text-slate-600">{{ str($permission)->replace('_',' ')->title() }}</span>@endforeach</div></div>
                        <div class="rounded-xl bg-[#0B1B3F] p-4 text-white"><p class="text-xs font-black uppercase tracking-wider text-emerald-300">Batas aman</p><p class="mt-2 text-xs leading-relaxed text-slate-300">{{ $agent->autonomy === 'approval' ? 'Agen hanya menyiapkan usulan. Tindakan eksternal harus disetujui terlebih dahulu.' : ($agent->autonomy === 'auto_internal' ? 'Agen dapat menganalisis data internal, tetapi tidak dapat melakukan tindakan eksternal.' : 'Agen memberikan rekomendasi; keputusan akhir tetap pada pemilik.') }}</p></div>
                    </div>
                @endforeach
            </aside>
        </div>

        <div x-show="tab === 'tasks'" x-cloak class="p-5 sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="font-black text-[#0B1B3F]">Pekerjaan tim</h2><p class="mt-1 text-sm text-slate-500">Hasil diskusi dan analisis yang perlu ditindaklanjuti.</p></div><button @click="newTask=true" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white">Buat tugas</button></div>
            <div class="mt-5 grid gap-5 xl:grid-cols-2">
                @forelse($tasks as $task)
                    <article class="rounded-xl border border-slate-200 p-5">
                        <div class="flex items-start justify-between gap-4"><div><span class="rounded-md border px-2 py-1 text-[10px] font-black uppercase {{ data_get($agentStyles,$task->agent->key.'.1','border-slate-200 bg-slate-50 text-slate-700') }}">{{ $task->agent->name }}</span><h3 class="mt-3 font-black text-[#0B1B3F]">{{ $task->title }}</h3></div><span class="rounded-full px-2.5 py-1 text-[10px] font-black {{ $task->status === 'completed' ? 'bg-emerald-50 text-emerald-700' : ($task->status === 'rejected' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700') }}">{{ ['proposed'=>'Menunggu','approved'=>'Berjalan','completed'=>'Selesai','rejected'=>'Terhambat'][$task->status] ?? $task->status }}</span></div>
                        <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ $task->summary }}</p>@if($task->recommendation)<p class="mt-3 rounded-lg bg-slate-50 p-3 text-xs leading-relaxed text-slate-600"><b class="text-slate-800">Rekomendasi:</b> {{ $task->recommendation }}</p>@endif
                        <footer class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3"><span class="text-xs font-bold {{ $task->risk === 'high' ? 'text-rose-700' : ($task->risk === 'medium' ? 'text-amber-700' : 'text-emerald-700') }}">Risiko {{ ['low'=>'rendah','medium'=>'sedang','high'=>'tinggi'][$task->risk] }}</span><button type="button" @click="moveTask={{ $task->id }}" class="text-xs font-bold text-emerald-700">Perbarui status</button></footer>
                    </article>
                @empty <div class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-sm text-slate-500"><b class="block text-slate-700">Belum ada pekerjaan</b><span class="mt-1 block">Mulai diskusi dengan agen atau buat tugas baru.</span></div> @endforelse
            </div>
        </div>

        <div x-show="tab === 'team'" x-cloak class="p-5 sm:p-6">
            <div><h2 class="font-black text-[#0B1B3F]">Pengaturan agen</h2><p class="mt-1 text-sm text-slate-500">Atur ketersediaan dan batas anggaran. Menjeda agen tidak menghapus histori percakapan.</p></div>
            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                @foreach($agents as $agent)
                    @php [$avatar,$badge] = $agentStyles[$agent->key] ?? ['bg-slate-700','bg-slate-50 text-slate-700 border-slate-200']; @endphp
                    <article class="rounded-xl border border-slate-200 p-5"><div class="flex items-start gap-3"><span class="flex h-11 w-11 items-center justify-center rounded-xl text-xs font-black text-white {{ $avatar }}">{{ data_get($profiles,$agent->key.'.initials','AI') }}</span><div class="min-w-0 flex-1"><h3 class="font-black text-[#0B1B3F]">{{ $agent->name }}</h3><p class="mt-1 text-xs leading-relaxed text-slate-500">{{ $agent->mission }}</p></div></div>
                        <form method="POST" action="{{ route('platform.ai-team.agent',$agent) }}" class="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-[1fr_1fr_auto]">@csrf @method('PATCH')<label class="text-xs font-bold text-slate-600">Status<select name="status" class="mt-1 w-full rounded-lg border-slate-300 text-sm"><option value="active" @selected($agent->status==='active')>Aktif</option><option value="paused" @selected($agent->status==='paused')>Dijeda</option></select></label><label class="text-xs font-bold text-slate-600">Anggaran harian<input name="daily_budget" type="number" min="0" max="100000000" value="{{ $agent->daily_budget }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></label><button class="self-end rounded-lg bg-[#0B1B3F] px-4 py-2.5 text-xs font-bold text-white">Simpan</button></form>
                    </article>
                @endforeach
            </div>
        </div>

        <div x-show="tab === 'data'" x-cloak class="p-5 sm:p-6">
            <div class="grid gap-6 xl:grid-cols-[1fr_340px]"><div>
                <div class="flex items-center justify-between gap-4"><div><h2 class="font-black text-[#0B1B3F]">Data operasional</h2><p class="mt-1 text-sm text-slate-500">Data ini menjadi dasar jawaban dan analisis agen.</p></div><button type="button" @click="intake=!intake" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white">Tambah data</button></div>
                <form x-show="intake" x-transition method="POST" action="{{ route('platform.ai-team.records') }}" class="mt-5 grid gap-4 rounded-xl border border-slate-200 bg-slate-50 p-5 md:grid-cols-2">@csrf
                    <label class="text-xs font-bold text-slate-700">Jenis<select name="kind" required class="mt-1.5 w-full rounded-lg border-slate-300 text-sm"><option value="content">Konten</option><option value="campaign">Campaign</option><option value="lead">Lead sales</option><option value="ticket">Tiket CS</option><option value="finance">Keuangan</option></select></label><label class="text-xs font-bold text-slate-700">Judul<input name="title" required maxlength="180" class="mt-1.5 w-full rounded-lg border-slate-300 text-sm"></label>
                    <label class="text-xs font-bold text-slate-700">Status<select name="status" required class="mt-1.5 w-full rounded-lg border-slate-300 text-sm"><option value="new">Baru</option><option value="active">Aktif</option><option value="expense">Biaya</option><option value="income">Pendapatan</option><option value="resolved">Selesai</option></select></label><label class="text-xs font-bold text-slate-700">Sumber<input name="source" required value="manual" class="mt-1.5 w-full rounded-lg border-slate-300 text-sm"></label>
                    <label class="text-xs font-bold text-slate-700">Skor 0–100<input name="score" type="number" min="0" max="100" class="mt-1.5 w-full rounded-lg border-slate-300 text-sm"></label><label class="text-xs font-bold text-slate-700">Nominal<input name="amount" type="number" min="0" class="mt-1.5 w-full rounded-lg border-slate-300 text-sm"></label><label class="text-xs font-bold text-slate-700 md:col-span-2">URL sumber<input name="url" type="url" class="mt-1.5 w-full rounded-lg border-slate-300 text-sm"></label><label class="text-xs font-bold text-slate-700 md:col-span-2">Catatan<textarea name="notes" rows="3" maxlength="2000" class="mt-1.5 w-full rounded-lg border-slate-300 text-sm"></textarea></label><button class="rounded-lg bg-[#0B1B3F] px-4 py-2.5 text-xs font-bold text-white md:col-span-2">Simpan data</button>
                </form>
                <div class="mt-5 overflow-x-auto rounded-xl border border-slate-200"><table class="w-full min-w-[700px] text-sm"><thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500"><tr><th class="px-4 py-3">Jenis</th><th class="px-4 py-3">Judul</th><th class="px-4 py-3">Sumber</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Skor</th><th class="px-4 py-3">Nominal</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($records as $record)<tr><td class="px-4 py-3 text-xs font-black uppercase text-emerald-700">{{ $record->kind }}</td><td class="px-4 py-3 font-semibold text-slate-800">{{ $record->title }}</td><td class="px-4 py-3 text-slate-500">{{ $record->source }}</td><td class="px-4 py-3 text-slate-600">{{ $record->status }}</td><td class="px-4 py-3">{{ $record->score ?? '—' }}</td><td class="px-4 py-3">{{ $record->amount !== null ? 'Rp '.number_format((float)$record->amount,0,',','.') : '—' }}</td></tr>@empty<tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Belum ada data.</td></tr>@endforelse</tbody></table></div>
            </div><aside class="space-y-4"><div class="rounded-xl border border-slate-200 p-5"><h3 class="font-black text-[#0B1B3F]">Status integrasi</h3><p class="mt-1 text-xs text-slate-500">Status ini menentukan kemampuan nyata agen.</p><div class="mt-4 space-y-3">@foreach($connections as [$name,$ready])<div class="flex items-center justify-between gap-3 text-sm"><span class="font-semibold text-slate-700">{{ $name }}</span><span class="rounded-full px-2 py-1 text-[10px] font-black {{ $ready ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $ready ? 'Terhubung' : 'Belum terhubung' }}</span></div>@endforeach</div></div><div class="rounded-xl bg-[#0B1B3F] p-5 text-white"><h3 class="font-black">Kontrol & keamanan</h3><ul class="mt-3 space-y-2 text-xs leading-relaxed text-slate-300"><li>Campaign baru memerlukan persetujuan.</li><li>Pesan massal dan scraping tidak diizinkan.</li><li>Refund dan perubahan harga tidak otomatis.</li><li>Seluruh diskusi dan tindakan dicatat.</li></ul></div></aside></div>
        </div>
    </section>

    <div x-show="newTask" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/55 p-4" @keydown.escape.window="newTask=false"><div @click.outside="newTask=false" class="w-full max-w-xl rounded-xl bg-white shadow-2xl"><header class="flex items-start justify-between border-b border-slate-200 px-6 py-5"><div><p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Tugas baru</p><h2 class="mt-1 text-xl font-black text-[#0B1B3F]">Delegasikan pekerjaan</h2></div><button type="button" @click="newTask=false" class="rounded-lg p-1 text-2xl text-slate-400">×</button></header><form method="POST" action="{{ route('platform.ai-team.tasks.store') }}" class="space-y-4 p-6">@csrf<label class="block text-sm font-bold text-slate-700">Anggota tim<select name="platform_ai_agent_id" required class="mt-1.5 w-full rounded-xl border-slate-300 text-sm">@foreach($agents as $agent)<option value="{{ $agent->id }}">{{ $agent->name }} · {{ $agent->department }}</option>@endforeach</select></label><label class="block text-sm font-bold text-slate-700">Nama tugas<input name="title" required maxlength="180" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm" placeholder="Apa yang perlu dikerjakan?"></label><label class="block text-sm font-bold text-slate-700">Instruksi<textarea name="summary" required maxlength="2000" rows="4" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm" placeholder="Jelaskan hasil yang diharapkan dan batasannya"></textarea></label><div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-bold text-slate-700">Tingkat risiko<select name="risk" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm"><option value="low">Rendah</option><option value="medium">Sedang</option><option value="high">Tinggi</option></select></label><label class="text-sm font-bold text-slate-700">Target selesai<input name="scheduled_for" type="datetime-local" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm"></label></div><div class="flex justify-end gap-2 border-t border-slate-100 pt-4"><button type="button" @click="newTask=false" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700">Batal</button><button class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white">Buat tugas</button></div></form></div></div>

    <div x-show="moveTask !== null" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/55 p-4" @keydown.escape.window="moveTask=null">
        @foreach($tasks as $task)<form x-show="moveTask === {{ $task->id }}" @click.outside="moveTask=null" method="POST" action="{{ route('platform.ai-team.tasks.move',$task) }}" class="w-full max-w-md rounded-xl bg-white shadow-2xl">@csrf @method('PATCH')<header class="border-b border-slate-200 px-6 py-5"><p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Perbarui progres</p><h2 class="mt-1 text-lg font-black text-[#0B1B3F]">{{ $task->title }}</h2></header><div class="space-y-4 p-6"><label class="block text-sm font-bold text-slate-700">Status<select name="status" required class="mt-1.5 w-full rounded-xl border-slate-300 text-sm"><option value="proposed" @selected($task->status==='proposed')>Menunggu</option><option value="approved" @selected($task->status==='approved')>Berjalan</option><option value="completed" @selected($task->status==='completed')>Selesai</option><option value="rejected" @selected($task->status==='rejected')>Terhambat</option></select></label><div class="flex justify-end gap-2"><button type="button" @click="moveTask=null" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold">Batal</button><button class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white">Simpan</button></div></div></form>@endforeach
    </div>
</div>

<script>
function aiTeamWorkspace(initialAgent, initialMessages, chatRoutes, readRoutes, initialUnread, prompts) {
    return {
        tab: 'workspace', activeAgent: initialAgent, messages: initialMessages, chatRoutes, readRoutes, unread: initialUnread, prompts,
        draft: '', sending: false, chatError: '', newTask: false, moveTask: null, intake: false,
        selectAgent(id) { this.activeAgent=id; this.draft=''; this.chatError=''; this.markRead(id); this.$nextTick(()=>this.scrollMessages()); },
        async markRead(id) { if (!this.unread[id]) return; this.unread[id]=0; try { await fetch(this.readRoutes[id],{method:'PATCH',headers:{'Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}}); } catch(error) { } },
        modeLabel(mode) { return mode === 'ai' ? 'AI + data' : (mode === 'fallback' ? 'Fallback data' : 'Data internal'); },
        sendPrompt(prompt) { this.draft=prompt; this.sendMessage(); },
        async sendMessage() {
            const content=this.draft.trim(); if (!content || this.sending) return;
            const agentId=this.activeAgent; this.sending=true; this.chatError=''; this.draft='';
            const optimistic={id:'local-'+Date.now(),role:'user',content,admin:'Anda',time:'Baru saja'};
            if (!this.messages[agentId]) this.messages[agentId]=[]; this.messages[agentId].push(optimistic); this.$nextTick(()=>this.scrollMessages());
            try {
                const response=await fetch(this.chatRoutes[agentId],{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({message:content})});
                const data=await response.json(); if(!response.ok) throw new Error(data.message || 'Pesan belum berhasil dikirim.');
                const index=this.messages[agentId].findIndex(item=>item.id===optimistic.id); if(index>=0)this.messages[agentId][index]={...data.user,admin:'Anda',time:'Baru saja'};
                this.messages[agentId].push({...data.assistant,time:'Baru saja'}); this.$nextTick(()=>this.scrollMessages());
            } catch(error) {
                this.messages[agentId]=this.messages[agentId].filter(item=>item.id!==optimistic.id); this.draft=content; this.chatError=error.message;
            } finally { this.sending=false; this.$nextTick(()=>this.scrollMessages()); }
        },
        scrollMessages() { const area=this.$refs['messageArea'+this.activeAgent]; if(area)area.scrollTop=area.scrollHeight; },
        init() { this.markRead(this.activeAgent); this.$nextTick(()=>this.scrollMessages()); }
    }
}
</script>
@endsection
