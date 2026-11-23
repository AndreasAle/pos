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
        'attachments' => $message->attachments->map(fn ($file) => ['name' => $file->original_name, 'kind' => $file->kind, 'size' => $file->size])->values(),
        'time' => $message->created_at->translatedFormat('d M, H:i'),
    ])->values())->all();
    $routePayload = $agents->mapWithKeys(fn ($agent) => [$agent->id => route('platform.ai-team.chat', $agent)])->all();
    $readRoutePayload = $agents->mapWithKeys(fn ($agent) => [$agent->id => route('platform.ai-team.read', $agent)])->all();
    $unreadPayload = $agents->mapWithKeys(fn ($agent) => [$agent->id => (int) ($unreadCounts[$agent->id] ?? 0)])->all();
    $promptPayload = $agents->mapWithKeys(fn ($agent) => [$agent->id => data_get($profiles, $agent->key.'.prompts', [])])->all();
    $taskPayload = $tasks->mapWithKeys(fn ($task) => [$task->id => [
        'id' => $task->id, 'title' => $task->title, 'summary' => $task->summary,
        'agent' => (string) $task->platform_ai_agent_id, 'agent_name' => $task->agent->name,
        'status' => $task->status, 'risk' => $task->risk,
        'approval' => ! $task->requires_approval ? 'none' : ($task->approved_at ? 'approved' : 'waiting'),
        'labels' => data_get($task->payload, 'labels', []),
    ]])->all();
    $taskColumns = [
        'proposed' => ['label' => 'Menunggu', 'description' => 'Perlu ditinjau', 'dot' => 'bg-amber-400', 'surface' => 'bg-amber-50/40'],
        'approved' => ['label' => 'Berjalan', 'description' => 'Sedang dikerjakan', 'dot' => 'bg-sky-500', 'surface' => 'bg-sky-50/40'],
        'completed' => ['label' => 'Selesai', 'description' => 'Hasil telah tersedia', 'dot' => 'bg-emerald-500', 'surface' => 'bg-emerald-50/40'],
        'rejected' => ['label' => 'Terhambat', 'description' => 'Ditolak atau terhenti', 'dot' => 'bg-rose-500', 'surface' => 'bg-rose-50/40'],
    ];
@endphp

<div
    x-data="aiTeamWorkspace(@js($selectedAgentId), @js($chatPayload), @js($routePayload), @js($readRoutePayload), @js($unreadPayload), @js($promptPayload), @js($taskPayload))"
    class="space-y-5"
>
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <nav class="flex overflow-x-auto border-b border-slate-200 px-3 sm:px-5">
            @foreach ([['workspace','Ruang agen'],['tasks','Pekerjaan'],['telegram','Telegram Office'],['memory','Memori & konteks'],['team','Pengaturan agen'],['data','Data & integrasi']] as [$key,$label])
                <button type="button" @click="selectTab('{{ $key }}')" class="whitespace-nowrap border-b-2 px-4 py-4 text-sm font-bold transition" :class="tab === '{{ $key }}' ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-500 hover:text-slate-800'">{{ $label }}</button>
            @endforeach
        </nav>

        <div x-show="tab === 'workspace'" x-cloak class="grid min-h-[560px] xl:h-[calc(100dvh-12rem)] xl:min-h-[560px] xl:grid-cols-[270px_minmax(420px,1fr)_310px]">
            <aside class="min-h-0 overflow-hidden border-b border-slate-200 bg-slate-50 xl:border-b-0 xl:border-r">
                <div class="border-b border-slate-200 px-4 py-4"><h2 class="font-black text-[#0B1B3F]">Pilih anggota</h2><p class="mt-1 text-xs text-slate-500">Setiap agen memiliki konteks terpisah.</p></div>
                <div class="flex gap-2 overflow-x-auto p-3 xl:h-[calc(100%-73px)] xl:block xl:space-y-1 xl:overflow-y-auto xl:overflow-x-hidden">
                    @foreach($agents as $agent)
                        @php [$avatar,$badge] = $agentStyles[$agent->key] ?? ['bg-slate-700','bg-slate-50 text-slate-700 border-slate-200']; @endphp
                        <button type="button" @click="selectAgent({{ $agent->id }})" class="min-w-[230px] rounded-xl border p-3 text-left transition xl:w-full xl:min-w-0" :class="activeAgent === {{ $agent->id }} ? 'border-emerald-300 bg-white shadow-sm' : 'border-transparent hover:bg-white'">
                            <span class="flex items-center gap-3"><img src="{{ asset(data_get($profiles,$agent->key.'.avatar')) }}" alt="{{ $agent->name }}" class="h-10 w-10 shrink-0 rounded-xl object-cover ring-1 ring-slate-200"><span class="min-w-0 flex-1"><b class="block truncate text-sm text-slate-900">{{ $agent->name }}</b><small class="mt-0.5 block truncate text-slate-500">{{ data_get($profiles,$agent->key.'.title',$agent->department) }}</small></span><span x-show="unread[{{ $agent->id }}] > 0" x-text="unread[{{ $agent->id }}]" class="flex h-6 min-w-6 items-center justify-center rounded-full bg-emerald-600 px-1.5 text-[10px] font-black text-white"></span><span x-show="unread[{{ $agent->id }}] === 0" class="h-2 w-2 rounded-full {{ $agent->status === 'active' ? 'bg-emerald-500' : 'bg-slate-400' }}"></span></span>
                            <span class="mt-3 flex items-center justify-between text-[11px]"><span class="text-slate-500">{{ $agent->open_tasks_count }} tugas terbuka</span><span class="font-bold {{ data_get($snapshots,$agent->id.'.ai_connected') ? 'text-emerald-700' : 'text-amber-700' }}">{{ data_get($snapshots,$agent->id.'.ai_connected') ? 'AI siap' : 'Mode internal' }}</span></span>
                        </button>
                    @endforeach
                </div>
            </aside>

            <div class="flex min-h-0 min-w-0 flex-col bg-white">
                @foreach($agents as $agent)
                    @php [$avatar,$badge] = $agentStyles[$agent->key] ?? ['bg-slate-700','bg-slate-50 text-slate-700 border-slate-200']; @endphp
                    <template x-if="activeAgent === {{ $agent->id }}">
                        <div class="flex min-h-0 flex-1 flex-col">
                            <header class="flex items-center gap-3 border-b border-slate-200 px-4 py-4 sm:px-5">
                                <img src="{{ asset(data_get($profiles,$agent->key.'.avatar')) }}" alt="{{ $agent->name }}" class="h-11 w-11 rounded-xl object-cover ring-1 ring-slate-200">
                                <div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><h2 class="font-black text-[#0B1B3F]">{{ $agent->name }}</h2><span class="rounded-full border px-2 py-0.5 text-[10px] font-black {{ $badge }}">{{ $agent->department }}</span></div><p class="mt-0.5 truncate text-xs text-slate-500">{{ $agent->mission }}</p></div>
                                <form method="POST" action="{{ route('platform.ai-team.run',$agent) }}">@csrf<button class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Analisis sekarang</button></form>
                            </header>

                            <div x-ref="messageArea{{ $agent->id }}" class="flex-1 space-y-5 overflow-y-auto bg-slate-50/60 px-4 py-5 sm:px-6">
                                <div x-show="(messages[{{ $agent->id }}] || []).length === 0" class="mx-auto max-w-lg rounded-xl border border-slate-200 bg-white p-5 text-center">
                                    <img src="{{ asset(data_get($profiles,$agent->key.'.avatar')) }}" alt="{{ $agent->name }}" class="mx-auto h-14 w-14 rounded-2xl object-cover ring-1 ring-slate-200">
                                    <h3 class="mt-3 font-black text-[#0B1B3F]">Mulai diskusi dengan {{ $agent->name }}</h3>
                                    <p class="mt-2 text-sm leading-relaxed text-slate-500">Agen akan menggunakan data operasional yang tersedia. Jika koneksi belum lengkap, keterbatasannya akan disebutkan dalam jawaban.</p>
                                </div>
                                <template x-for="message in (messages[{{ $agent->id }}] || [])" :key="message.id">
                                    <div class="flex items-end gap-2.5" :class="message.role === 'user' ? 'flex-row-reverse justify-start' : 'justify-start'">
                                        <img :src="message.role === 'user' ? @js(asset('images/ai-team/andreas.jpg')) : @js(asset(data_get($profiles,$agent->key.'.avatar')))" :alt="message.role === 'user' ? (message.admin || 'Anda') : @js($agent->name)" class="h-9 w-9 shrink-0 rounded-full object-cover ring-2 ring-white shadow-sm">
                                        <div class="max-w-[88%]">
                                            <div class="rounded-2xl px-4 py-3.5 shadow-sm" :class="message.role === 'user' ? 'rounded-br-md bg-emerald-700 text-white' : 'rounded-bl-md border border-slate-200 bg-white text-slate-700'"><template x-for="(seg,si) in segments(message.content)" :key="si"><div><p x-show="seg.type === 'text'" class="whitespace-pre-line break-words text-[14px] leading-7" x-text="seg.text"></p><div x-show="seg.type === 'chart'" x-html="seg.type === 'chart' ? chartHtml(seg.chart) : ''" class="my-3"></div></div></template><div x-show="(message.attachments || []).length" class="mt-3 space-y-1.5 border-t pt-3" :class="message.role === 'user' ? 'border-white/20' : 'border-slate-100'"><div x-show="(message.attachments || []).some(f => f.preview)" class="flex flex-wrap gap-2"><template x-for="file in (message.attachments || []).filter(f => f.preview)" :key="'p'+file.name"><img :src="file.preview" :alt="file.name" class="h-24 max-w-[180px] rounded-lg object-cover ring-1 ring-white/30"></template></div><template x-for="file in (message.attachments || []).filter(f => !f.preview)" :key="file.name"><div class="flex items-center gap-2 rounded-lg px-2.5 py-2 text-xs" :class="message.role === 'user' ? 'bg-white/10 text-white' : 'bg-slate-50 text-slate-600'"><svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M15.2 7.4 8.9 13.7a2 2 0 1 0 2.8 2.8l7-7a4 4 0 0 0-5.7-5.6l-7.7 7.7a6 6 0 1 0 8.5 8.5l6.3-6.3"/></svg><span class="truncate" x-text="file.name"></span></div></template></div></div>
                                            <div class="mt-1 flex items-center gap-2 px-1 text-[10px] text-slate-400" :class="message.role === 'user' ? 'justify-end' : ''"><span x-text="message.role === 'user' ? (message.admin || 'Anda') : '{{ $agent->name }}'"></span><span>·</span><span x-text="message.time || 'Baru saja'"></span><span x-show="message.mode" class="rounded bg-slate-200 px-1.5 py-0.5 font-bold text-slate-600" x-text="modeLabel(message.mode)"></span></div>
                                        </div>
                                    </div>
                                </template>
                                <div x-show="sending && activeAgent === {{ $agent->id }}" class="flex items-end gap-2.5"><img src="{{ asset(data_get($profiles,$agent->key.'.avatar')) }}" alt="{{ $agent->name }}" class="h-9 w-9 shrink-0 rounded-full object-cover ring-2 ring-white shadow-sm"><div class="rounded-2xl rounded-bl-md border border-slate-200 bg-white px-4 py-3 text-sm text-slate-500 shadow-sm"><span class="inline-flex items-center gap-2"><span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span>{{ $agent->name }} sedang membaca data…</span></div></div>
                            </div>

                            <footer class="border-t border-slate-200 bg-white p-4">
                                <div class="mb-3 flex gap-2 overflow-x-auto pb-1">
                                    @foreach(data_get($profiles,$agent->key.'.prompts',[]) as $prompt)<button type="button" @click="sendPrompt(@js($prompt))" class="whitespace-nowrap rounded-full border border-slate-300 px-3 py-1.5 text-[11px] font-bold text-slate-600 hover:border-emerald-400 hover:text-emerald-700">{{ $prompt }}</button>@endforeach
                                </div>
                                <div x-show="files.length" class="mb-2 flex flex-wrap gap-2"><template x-for="(file,index) in files" :key="file.name+file.size+index"><span class="relative inline-flex max-w-full items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 text-xs text-slate-600" :class="file.preview ? 'p-1' : 'px-2.5 py-1.5'"><img x-show="file.preview" :src="file.preview" :alt="file.name" class="h-16 w-16 rounded-md object-cover"><span x-show="!file.preview" class="max-w-48 truncate" x-text="file.name"></span><button type="button" @click="removeFile(index)" class="font-black text-slate-400 hover:text-rose-600" :class="file.preview ? 'absolute -right-2 -top-2 h-5 w-5 rounded-full bg-white shadow ring-1 ring-slate-200' : ''" aria-label="Hapus lampiran">×</button></span></template></div>
                                <form @submit.prevent="sendMessage()" class="flex items-end gap-2">
                                    <input type="file" x-ref="filePicker" multiple accept="{{ \App\Services\PlatformAiAttachmentService::ACCEPT }}" class="hidden" @change="pickFiles($event)">
                                    <button type="button" @click="$refs.filePicker.click()" :disabled="sending" class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-slate-300 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40" aria-label="Lampirkan file"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M15.2 7.4 8.9 13.7a2 2 0 1 0 2.8 2.8l7-7a4 4 0 0 0-5.7-5.6l-7.7 7.7a6 6 0 1 0 8.5 8.5l6.3-6.3"/></svg></button>
                                    <textarea x-model="draft" @keydown="handleMessageKeydown($event)" @paste="pasteFiles($event)" rows="1" maxlength="4000" class="max-h-32 min-h-[48px] flex-1 resize-none rounded-xl border-slate-300 px-3.5 py-3 text-sm leading-6 focus:border-emerald-500 focus:ring-emerald-500" placeholder="Tulis pesan… tempel screenshot dengan Ctrl+V (Shift+Enter baris baru)"></textarea>
                                    <button type="submit" :disabled="sending || (!draft.trim() && !files.length)" class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white disabled:cursor-not-allowed disabled:opacity-40" aria-label="Kirim pesan"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 12 14-7-4 14-3-6-7-1Z"/></svg></button>
                                </form>
                                <p x-show="chatError" x-text="chatError" class="mt-2 text-xs text-rose-700"></p>
                            </footer>
                        </div>
                    </template>
                @endforeach
            </div>

            <aside class="min-h-0 overflow-y-auto border-t border-slate-200 p-5 xl:border-l xl:border-t-0">
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

        <div x-show="tab === 'tasks'" x-cloak class="bg-slate-50/70 p-4 sm:p-6">
            <header class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div><p class="text-xs font-black uppercase tracking-[.16em] text-emerald-700">Operasi tenaga kerja digital</p><h1 class="mt-1 text-2xl font-black text-[#0B1B3F]">AI Workspace</h1><p class="mt-1 text-sm text-slate-500">Temukan pekerjaan, tinjau hasil nyata, dan ambil keputusan tanpa mencampur status tugas, approval, dan eksekusi.</p></div>
                    <div class="flex items-center gap-2"><div class="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-1" aria-label="Pilih tampilan"><button type="button" @click="taskView='board'" class="rounded-md px-3 py-2 text-xs font-bold" :class="taskView==='board' ? 'bg-white text-[#0B1B3F] shadow-sm' : 'text-slate-500'">Papan</button><button type="button" @click="taskView='list'" class="rounded-md px-3 py-2 text-xs font-bold" :class="taskView==='list' ? 'bg-white text-[#0B1B3F] shadow-sm' : 'text-slate-500'">Daftar</button></div><button @click="newTask=true" class="rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-emerald-700">Buat tugas</button></div>
                </div>
                <div class="mt-5 grid gap-2 sm:grid-cols-2 xl:grid-cols-5">
                    @foreach ([['Semua tugas',$taskMetrics['total']],['Menunggu',$taskMetrics['proposed']],['Berjalan',$taskMetrics['approved']],['Terhambat',$taskMetrics['rejected']],['Perlu approval',$taskMetrics['approval']]] as [$label,$value])
                    <div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5"><p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ $label }}</p><p class="mt-1 text-xl font-black text-[#0B1B3F]">{{ $value }}</p></div>
                    @endforeach
                </div>
                <div class="mt-4 grid gap-2 md:grid-cols-2 xl:grid-cols-[minmax(240px,1fr)_repeat(4,minmax(130px,auto))_auto]">
                    <label class="relative"><span class="sr-only">Cari tugas</span><svg class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" stroke-width="2"/><path d="m20 20-4-4" stroke-width="2"/></svg><input x-model.debounce.250ms="taskSearch" class="w-full rounded-lg border-slate-300 py-2 pl-9 pr-3 text-sm" placeholder="Cari judul, #ID, deskripsi, agen…"></label>
                    <select x-model="taskAgent" class="rounded-lg border-slate-300 py-2 text-xs font-semibold"><option value="">Semua agen</option>@foreach($agents as $agent)<option value="{{ $agent->id }}">{{ $agent->name }}</option>@endforeach</select>
                    <select x-model="taskStatus" class="rounded-lg border-slate-300 py-2 text-xs font-semibold"><option value="">Semua status</option>@foreach($taskColumns as $key=>$column)<option value="{{ $key }}">{{ $column['label'] }}</option>@endforeach</select>
                    <select x-model="taskRisk" class="rounded-lg border-slate-300 py-2 text-xs font-semibold"><option value="">Semua risiko</option><option value="high">Tinggi</option><option value="medium">Sedang</option><option value="low">Rendah</option></select>
                    <select x-model="taskApproval" class="rounded-lg border-slate-300 py-2 text-xs font-semibold"><option value="">Semua approval</option><option value="waiting">Menunggu approval</option><option value="approved">Sudah disetujui</option><option value="none">Tanpa approval</option></select>
                    <button type="button" @click="resetTaskFilters()" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50">Reset</button>
                </div>
                <p class="mt-3 text-xs text-slate-500"><span x-text="visibleTaskCount()"></span> dari {{ $tasks->count() }} tugas yang dimuat ditampilkan.@if($taskDatasetIsLimited) Pencarian dan filter mencakup 100 tugas terbaru dari total {{ $taskMetrics['total'] }}; metrik di atas tetap menghitung seluruh tugas aktif.@endif</p>
            </header>

            <div x-show="taskView === 'board'" class="mt-4 overflow-x-auto pb-3">
                <div class="grid h-[calc(100dvh-26rem)] min-h-[420px] max-h-[800px] min-w-[1080px] grid-cols-4 gap-3" aria-label="Papan Kanban pekerjaan tim">
                    @foreach($taskColumns as $status => $column)
                        @php $columnTasks = $tasks->where('status', $status); @endphp
                        <section
                            class="flex min-h-0 flex-col overflow-hidden rounded-xl border border-slate-200 {{ $column['surface'] }} p-3 transition"
                            :class="kanbanOver === '{{ $status }}' ? 'border-emerald-400 ring-2 ring-emerald-100' : ''"
                            @dragover.prevent="kanbanOver='{{ $status }}'"
                            @dragleave.self="kanbanOver=null"
                            @drop.prevent="dropTask('{{ $status }}')"
                        >
                            <header class="flex items-center justify-between px-1 pb-3">
                                <div class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full {{ $column['dot'] }}"></span><div><h3 class="text-sm font-black text-[#0B1B3F]">{{ $column['label'] }}</h3><p class="text-[10px] text-slate-500">{{ $column['description'] }}</p></div></div>
                                <span class="flex h-6 min-w-6 items-center justify-center rounded-full border border-slate-200 bg-white px-1.5 text-[10px] font-black text-slate-500">{{ $columnTasks->count() }}</span>
                            </header>

                            <div class="flex min-h-0 flex-1 flex-col gap-2.5 overflow-y-auto overscroll-contain pr-1 [scrollbar-gutter:stable]">
                                @forelse($columnTasks as $task)
                                    @php
                                        $taskPeople = collect([
                                            $task->platform_ai_agent_id,
                                            $task->delegated_by_agent_id,
                                            data_get($task->payload, 'agent_ids', []),
                                            data_get($task->payload, 'participant_agent_ids', []),
                                            data_get($task->payload, 'collaborator_agent_ids', []),
                                        ])->flatten()->filter()->unique()->map(fn ($id) => $agents->firstWhere('id', (int) $id))->filter()->values();
                                    @endphp
                                    <article
                                        x-show="taskMatches({{ $task->id }})"
                                        draggable="true"
                                        @dragstart="startTaskDrag($event, {{ $task->id }}, @js($task->type), '{{ $task->status }}')"
                                        @dragend="endTaskDrag()"
                                        tabindex="0" role="button" @click="openTask({{ $task->id }}, $el)" @keydown.enter.prevent="openTask({{ $task->id }}, $el)" @keydown.space.prevent="openTask({{ $task->id }}, $el)"
                                        class="cursor-pointer rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm transition hover:border-emerald-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                        :class="draggedTask?.id === {{ $task->id }} ? 'opacity-40' : ''"
                                    >
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="flex -space-x-2" aria-label="Agen yang terlibat: {{ $taskPeople->pluck('name')->join(', ') }}">
                                                @foreach($taskPeople->take(5) as $person)
                                                    <img src="{{ asset(data_get($profiles,$person->key.'.avatar')) }}" alt="{{ $person->name }}" title="{{ $person->name }} · {{ data_get($profiles,$person->key.'.title',$person->department) }}" class="h-8 w-8 rounded-full border-2 border-white object-cover shadow-sm ring-1 ring-slate-200">
                                                @endforeach
                                                @if($taskPeople->count() > 5)<span class="flex h-8 min-w-8 items-center justify-center rounded-full border-2 border-white bg-slate-100 px-1 text-[9px] font-black text-slate-600 shadow-sm">+{{ $taskPeople->count()-5 }}</span>@endif
                                            </div>
                                            <span class="text-[10px] font-bold text-slate-400">#{{ $task->id }}</span>
                                        </div>
                                        <h4 class="mt-3 text-sm font-black leading-5 text-[#0B1B3F]">{{ $task->title }}</h4>
                                        <p class="mt-2 max-h-[4.5rem] overflow-hidden text-xs leading-6 text-slate-500">{{ $task->summary }}</p>
                                        <div class="mt-3 flex flex-wrap gap-1.5">@if($task->requires_approval)<span class="rounded bg-violet-50 px-1.5 py-1 text-[9px] font-bold text-violet-700">{{ $task->approved_at ? 'Disetujui' : 'Perlu approval' }}</span>@endif @if($task->scheduled_for)<span class="rounded bg-slate-100 px-1.5 py-1 text-[9px] font-bold {{ $task->scheduled_for->isPast() && $task->status !== 'completed' ? 'text-rose-700' : 'text-slate-600' }}">{{ $task->scheduled_for->translatedFormat('d M') }}</span>@endif @if($task->executions->first())<span class="rounded bg-sky-50 px-1.5 py-1 text-[9px] font-bold text-sky-700">Run: {{ $task->executions->first()->status }}</span>@endif</div>
                                        <footer class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3">
                                            <span class="text-[10px] font-bold {{ $task->risk === 'high' ? 'text-rose-700' : ($task->risk === 'medium' ? 'text-amber-700' : 'text-emerald-700') }}">Risiko {{ ['low'=>'rendah','medium'=>'sedang','high'=>'tinggi'][$task->risk] }}</span>
                                            <span class="text-[10px] text-slate-400">{{ $task->updated_at->diffForHumans() }}</span>
                                        </footer>
                                        <form id="kanban-task-{{ $task->id }}" method="POST" action="{{ route('platform.ai-team.tasks.move',$task) }}" class="hidden">@csrf @method('PATCH')<input name="status" value="{{ $task->status }}"></form>
                                    </article>
                                @empty
                                    <div class="flex min-h-28 flex-1 items-center justify-center rounded-xl border border-dashed border-slate-300 px-4 text-center text-xs text-slate-400">Tarik kartu ke sini</div>
                                @endforelse
                            </div>
                        </section>
                    @endforeach
                </div>
            </div>
            <div x-show="taskView === 'list'" x-cloak class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"><div class="overflow-x-auto"><table class="w-full min-w-[940px] text-left"><thead class="border-b border-slate-200 bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500"><tr><th class="px-4 py-3">Tugas</th><th class="px-4 py-3">Agen</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Risiko</th><th class="px-4 py-3">Approval</th><th class="px-4 py-3">Deadline</th><th class="px-4 py-3">Diperbarui</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach($tasks as $task)<tr x-show="taskMatches({{ $task->id }})" tabindex="0" @click="openTask({{ $task->id }}, $el)" @keydown.enter.prevent="openTask({{ $task->id }}, $el)" class="cursor-pointer text-xs hover:bg-emerald-50/40 focus:bg-emerald-50 focus:outline-none"><td class="px-4 py-3"><b class="block text-sm text-[#0B1B3F]">{{ $task->title }}</b><span class="mt-0.5 block max-w-md truncate text-slate-500">#{{ $task->id }} · {{ $task->summary }}</span></td><td class="px-4 py-3 font-bold text-slate-700">{{ $task->agent->name }}</td><td class="px-4 py-3">{{ data_get($taskColumns,$task->status.'.label',$task->status) }}</td><td class="px-4 py-3 capitalize">{{ ['low'=>'Rendah','medium'=>'Sedang','high'=>'Tinggi'][$task->risk] }}</td><td class="px-4 py-3">{{ !$task->requires_approval ? 'Tidak wajib' : ($task->approved_at ? 'Disetujui' : 'Menunggu') }}</td><td class="px-4 py-3">{{ $task->scheduled_for?->translatedFormat('d M Y, H:i') ?? '—' }}</td><td class="px-4 py-3 text-slate-500">{{ $task->updated_at->diffForHumans() }}</td></tr>@endforeach</tbody></table></div><div x-show="visibleTaskCount() === 0" class="border-t border-slate-100 p-10 text-center text-sm text-slate-500">Tidak ada tugas yang cocok dengan filter.</div></div>
            <p class="mt-3 text-xs text-slate-400">Perpindahan kolom mengubah status pekerjaan. Status eksekusi hanya ditampilkan bila ada run yang tercatat. Komunikasi eksternal tetap melalui approval khusus.</p>
        </div>

        <div x-show="tab === 'telegram'" x-cloak class="space-y-6 p-5 sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="font-black text-[#0B1B3F]">Telegram Virtual Office</h2><p class="mt-1 text-sm text-slate-500">Kanal komunikasi privat untuk tujuh agen. Dashboard tetap menjadi pusat approval dan sumber kebenaran.</p></div><span class="w-fit rounded-full px-3 py-1 text-xs font-black {{ $telegramGroup?->enabled ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">{{ $telegramGroup?->enabled ? 'Terhubung' : 'Belum aktif' }}</span></div>

            <div class="grid gap-5 xl:grid-cols-[1.2fr_.8fr]">
                <section class="rounded-xl border border-slate-200 bg-white p-5"><div class="flex items-center justify-between gap-3"><div><h3 class="font-black text-[#0B1B3F]">Koneksi tujuh bot</h3><p class="mt-1 text-xs text-slate-500">Token hanya dibaca dari environment server dan tidak pernah ditampilkan.</p></div><div class="flex items-center gap-2"><span class="text-xs font-bold text-slate-500">{{ $telegramBots->where('status','active')->count() }}/7 aktif</span><form method="POST" action="{{ route('platform.ai-team.telegram.health') }}">@csrf<button class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-[10px] font-bold text-slate-600">Cek koneksi</button></form></div></div><div class="mt-4 grid gap-3 sm:grid-cols-2">@foreach($telegramBots as $bot)<article class="rounded-xl border border-slate-200 p-3"><div class="flex items-center gap-3"><img src="{{ asset(data_get($profiles,$bot->agent->key.'.avatar')) }}" class="h-10 w-10 rounded-xl object-cover"><div class="min-w-0 flex-1"><b class="block text-sm text-[#0B1B3F]">{{ $bot->agent->name }}</b><span class="block truncate text-[11px] text-slate-500">{{ $bot->username ? '@'.$bot->username : 'Username belum diatur' }}</span></div><span class="h-2.5 w-2.5 rounded-full {{ $bot->status==='active' ? 'bg-emerald-500' : 'bg-slate-300' }}"></span></div><div class="mt-3 flex justify-between text-[10px] text-slate-400"><span>Webhook {{ $bot->webhook_status }}</span><span>{{ $bot->last_health_at?->diffForHumans() ?? 'Belum dicek' }}</span></div>@if($bot->last_error)<p class="mt-2 truncate text-[10px] text-rose-600">{{ $bot->last_error }}</p>@endif</article>@endforeach</div></section>

                <section class="rounded-xl border border-slate-200 bg-white p-5"><h3 class="font-black text-[#0B1B3F]">Tautkan pemilik</h3><p class="mt-1 text-xs leading-5 text-slate-500">Otorisasi memakai Telegram user ID, bukan username. Kode hanya berlaku 10 menit dan sekali pakai.</p>@if(session('telegram_link_code'))<div class="mt-4 rounded-xl bg-emerald-50 p-4 text-center"><p class="text-[10px] font-black uppercase tracking-wider text-emerald-700">Kirim ke salah satu bot</p><code class="mt-2 block text-xl font-black text-emerald-900">/link {{ session('telegram_link_code') }}</code></div>@endif<div class="mt-4 rounded-xl bg-slate-50 p-3 text-xs"><div class="flex justify-between"><span class="text-slate-500">Status</span><b>{{ $telegramIdentity?->status ?? 'belum terhubung' }}</b></div>@if($telegramIdentity?->telegram_user_id)<div class="mt-2 flex justify-between"><span class="text-slate-500">Telegram ID</span><b>{{ $telegramIdentity->telegram_user_id }}</b></div>@endif</div><form method="POST" action="{{ route('platform.ai-team.telegram.link') }}" class="mt-3">@csrf<button class="w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white">Buat kode tautan</button></form>@if($telegramIdentity)<form method="POST" action="{{ route('platform.ai-team.telegram.revoke') }}" class="mt-2" onsubmit="return confirm('Cabut semua akses Telegram Office?')">@csrf @method('DELETE')<button class="w-full rounded-xl border border-rose-200 px-4 py-2.5 text-xs font-bold text-rose-700">Cabut akses</button></form>@endif</section>
            </div>

            @if($telegramGroup)
            @php $notify=(array)$telegramGroup->notification_settings; @endphp
            <section class="rounded-xl border border-slate-200 bg-white p-5"><h3 class="font-black text-[#0B1B3F]">Pengaturan grup</h3><p class="mt-1 text-xs text-slate-500">{{ $telegramGroup->title }} · ID {{ $telegramGroup->telegram_chat_id }}</p><form method="POST" action="{{ route('platform.ai-team.telegram.group',$telegramGroup) }}" class="mt-4 grid gap-4 md:grid-cols-3">@csrf @method('PATCH')<input type="hidden" name="mode" value="smart_discussion"><div class="rounded-xl bg-emerald-50 p-3 text-xs text-emerald-800"><b>Percakapan natural aktif</b><p class="mt-1 leading-5">Ngobrol biasa tanpa mention. Sistem memilih maksimal tiga rekan yang relevan.</p></div><label class="text-xs font-bold text-slate-700">Jam briefing<input name="briefing_time" type="time" value="{{ data_get($notify,'briefing_time','08:00') }}" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm"></label><label class="text-xs font-bold text-slate-700">Severity minimum<select name="severity" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm">@foreach(['low'=>'Rendah','medium'=>'Sedang','high'=>'Tinggi'] as $value=>$label)<option value="{{ $value }}" @selected(data_get($notify,'severity','medium')===$value)>{{ $label }}</option>@endforeach</select></label><label class="text-xs font-bold text-slate-700">Quiet mulai<input name="quiet_start" type="time" value="{{ data_get($notify,'quiet_start','22:00') }}" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm"></label><label class="text-xs font-bold text-slate-700">Quiet selesai<input name="quiet_end" type="time" value="{{ data_get($notify,'quiet_end','07:00') }}" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm"></label><label class="text-xs font-bold text-slate-700">Maks. alert/hari<input name="max_alerts_per_day" type="number" min="0" max="20" value="{{ data_get($notify,'max_alerts_per_day',5) }}" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm"></label><label class="flex items-center gap-2 text-xs font-bold text-slate-700"><input type="hidden" name="enabled" value="0"><input name="enabled" value="1" type="checkbox" @checked($telegramGroup->enabled) class="rounded border-slate-300 text-emerald-600">Integrasi aktif</label><label class="flex items-center gap-2 text-xs font-bold text-slate-700"><input type="hidden" name="daily_briefing" value="0"><input name="daily_briefing" value="1" type="checkbox" @checked(data_get($notify,'daily_briefing')) class="rounded border-slate-300 text-emerald-600">Briefing harian</label><button class="rounded-xl bg-[#0B1B3F] px-4 py-2.5 text-xs font-bold text-white">Simpan pengaturan</button></form></section>
            @endif

            <section class="rounded-xl border border-slate-200 bg-white p-5"><h3 class="font-black text-[#0B1B3F]">Aktivitas integrasi</h3><div class="mt-4 overflow-x-auto"><table class="w-full min-w-[700px] text-left text-xs"><thead class="border-b border-slate-200 text-slate-400"><tr><th class="py-2">Waktu</th><th>Bot</th><th>Status</th><th>Chat</th><th>Kesalahan</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($telegramUpdates as $update)<tr><td class="py-3 text-slate-500">{{ $update->created_at->translatedFormat('d M H:i') }}</td><td class="font-bold text-slate-700">{{ $update->bot?->agent?->name }}</td><td><span class="rounded-full bg-slate-100 px-2 py-1 font-bold">{{ $update->status }}</span></td><td>{{ $update->telegram_chat_id }}</td><td class="max-w-xs truncate text-rose-600">{{ $update->error ?? '—' }}</td></tr>@empty<tr><td colspan="5" class="py-8 text-center text-slate-400">Belum ada webhook masuk.</td></tr>@endforelse</tbody></table></div></section>

            <aside class="rounded-xl bg-[#0B1B3F] p-5 text-white"><h3 class="font-black">Batas aman Telegram</h3><p class="mt-2 text-xs leading-6 text-slate-300">Maksimal tiga agen untuk Smart Discussion dan empat agen untuk meeting, satu ronde. Pesan bot tidak memicu bot lain. Approval sensitif, pengeluaran, pesan eksternal, refund, harga, dan iklan tetap hanya melalui Operations Center.</p></aside>
        </div>

        <div x-show="tab === 'memory'" x-cloak class="p-5 sm:p-6">
            <div><h2 class="font-black text-[#0B1B3F]">Memori & konteks pemilik</h2><p class="mt-1 text-sm text-slate-500">Konteks terkonfirmasi selalu lebih kuat daripada inferensi agen. Data aktual dari tool tetap menjadi sumber angka utama.</p></div>
            <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.4fr)]">
                <section class="rounded-xl border border-slate-200 bg-white p-5">
                    <h3 class="font-black text-[#0B1B3F]">Owner Context</h3>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500">Simpan tujuan, prioritas, batas anggaran, gaya komunikasi, atau kebijakan yang harus diketahui semua agen.</p>
                    <form method="POST" action="{{ route('platform.ai-team.owner-context') }}" class="mt-4 space-y-3">@csrf
                        <label class="block text-xs font-bold text-slate-700">Jenis konteks<select name="key" required class="mt-1.5 w-full rounded-xl border-slate-300 text-sm"><option value="company_priorities">Prioritas perusahaan</option><option value="quarterly_objectives">Tujuan kuartal</option><option value="marketing_direction">Arah marketing</option><option value="financial_constraints">Batas keuangan</option><option value="communication_style">Gaya komunikasi</option><option value="decision_preferences">Cara mengambil keputusan</option><option value="product_positioning">Posisi produk</option><option value="prohibited_actions">Tindakan terlarang</option><option value="important_metrics">Metrik utama</option><option value="active_initiatives">Inisiatif aktif</option></select></label>
                        <label class="block text-xs font-bold text-slate-700">Label<input name="label" required maxlength="120" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm" placeholder="Contoh: Prioritas Q4"></label>
                        <label class="block text-xs font-bold text-slate-700">Isi<textarea name="value" required maxlength="4000" rows="4" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm" placeholder="Tulis fakta atau kebijakan yang sudah kamu konfirmasi."></textarea></label>
                        <button class="w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white">Simpan konteks terkonfirmasi</button>
                    </form>
                    <div class="mt-5 space-y-2 border-t border-slate-100 pt-4">
                        @forelse($ownerContexts as $context)
                            <div class="rounded-xl bg-slate-50 p-3"><div class="flex items-center justify-between gap-2"><b class="text-xs text-[#0B1B3F]">{{ $context->label }}</b><span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-black text-emerald-700">Terkonfirmasi</span></div><p class="mt-1.5 text-xs leading-relaxed text-slate-600">{{ $context->value }}</p></div>
                        @empty <p class="rounded-xl border border-dashed border-slate-300 p-4 text-center text-xs text-slate-500">Belum ada konteks pemilik.</p> @endforelse
                    </div>
                </section>
                <section class="rounded-xl border border-slate-200 bg-white p-5">
                    <h3 class="font-black text-[#0B1B3F]">Ingatan agen</h3><p class="mt-1 text-xs text-slate-500">Hanya pernyataan eksplisit yang disimpan otomatis. Kamu dapat mengoreksi atau menonaktifkannya.</p>
                    <div class="mt-4 space-y-3">
                        @forelse($memories as $memory)
                            <article class="rounded-xl border border-slate-200 p-4">
                                <div class="flex flex-wrap items-center gap-2"><span class="rounded-md bg-slate-100 px-2 py-1 text-[10px] font-black uppercase text-slate-600">{{ $memory->category }}</span>@if($memory->agent)<span class="text-[11px] font-bold text-emerald-700">{{ $memory->agent->name }}</span>@endif<span class="ml-auto text-[10px] text-slate-400">Versi {{ $memory->version }}</span></div>
                                <form method="POST" action="{{ route('platform.ai-team.memories.update',$memory) }}" class="mt-3 space-y-2">@csrf @method('PATCH')
                                    <textarea name="statement" required maxlength="1000" rows="2" class="w-full rounded-xl border-slate-300 text-sm">{{ data_get($memory->content,'statement') }}</textarea>
                                    <div class="flex items-center gap-2"><label class="text-xs font-bold text-slate-600">Prioritas <input name="importance" type="number" min="1" max="100" value="{{ $memory->importance }}" class="ml-1 w-20 rounded-lg border-slate-300 text-xs"></label><button class="ml-auto rounded-lg bg-[#0B1B3F] px-3 py-2 text-xs font-bold text-white">Koreksi & konfirmasi</button></div>
                                </form>
                                <form method="POST" action="{{ route('platform.ai-team.memories.deactivate',$memory) }}" class="mt-2 text-right">@csrf @method('DELETE')<button class="text-xs font-bold text-rose-700">Nonaktifkan memori</button></form>
                            </article>
                        @empty <p class="rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">Belum ada memori jangka panjang. Pernyataan eksplisit seperti “Mulai sekarang fokus pada konversi trial” akan disimpan.</p> @endforelse
                    </div>
                </section>
            </div>
        </div>

        <div x-show="tab === 'team'" x-cloak class="p-5 sm:p-6">
            <div><h2 class="font-black text-[#0B1B3F]">Pengaturan agen</h2><p class="mt-1 text-sm text-slate-500">Atur ketersediaan dan batas anggaran. Menjeda agen tidak menghapus histori percakapan.</p></div>
            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                @foreach($agents as $agent)
                    @php [$avatar,$badge] = $agentStyles[$agent->key] ?? ['bg-slate-700','bg-slate-50 text-slate-700 border-slate-200']; @endphp
                    <article class="rounded-xl border border-slate-200 p-5"><div class="flex items-start gap-3"><img src="{{ asset(data_get($profiles,$agent->key.'.avatar')) }}" alt="{{ $agent->name }}" class="h-11 w-11 rounded-xl object-cover ring-1 ring-slate-200"><div class="min-w-0 flex-1"><h3 class="font-black text-[#0B1B3F]">{{ $agent->name }}</h3><p class="mt-1 text-xs leading-relaxed text-slate-500">{{ $agent->mission }}</p></div></div>
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

    <div x-show="moveTask !== null" x-cloak class="fixed inset-0 z-[70] flex justify-end bg-slate-950/45" @keydown.escape.window="closeTask()" role="dialog" aria-modal="true" aria-label="Detail tugas">
        @foreach($tasks as $task)
        @php
            $latestExecution = $task->executions->first();
            $createdActivity = $taskActivities->get($task->id, collect())->firstWhere('action', 'ai.task.create');
        @endphp
        @if($task->type === 'whatsapp_draft' && $task->status === 'proposed')
        <form x-show="moveTask === {{ $task->id }}" @click.outside="moveTask=null" method="POST" action="{{ route('platform.ai-team.decision',$task) }}" class="w-full max-w-md rounded-xl bg-white shadow-2xl">@csrf @method('PATCH')<header class="flex items-start justify-between border-b border-slate-200 px-6 py-5"><div><p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Draf WhatsApp</p><h2 class="mt-1 text-lg font-black leading-snug text-[#0B1B3F]">{{ $task->title }}</h2></div><button type="button" @click="moveTask=null" class="rounded-lg p-1 text-2xl text-slate-400 hover:bg-slate-100">×</button></header><div class="space-y-3 p-6"><p class="text-xs text-slate-500">Tujuan: <strong class="text-slate-800">{{ data_get($task->payload,'phone') }}</strong>. Pesan hanya terkirim setelah Anda menyetujui; boleh disunting dulu.</p><textarea name="message" rows="6" maxlength="600" class="w-full rounded-xl border-slate-300 text-sm">{{ $task->summary }}</textarea>@if($task->error)<p class="text-xs font-semibold text-rose-700">{{ $task->error }}</p>@endif<div class="flex justify-end gap-2 border-t border-slate-100 pt-4"><button name="decision" value="reject" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700">Tolak</button><button name="decision" value="approve" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white">Setujui &amp; kirim</button></div></div></form>
        @else
        <div x-data="{ detailTab: 'overview' }" x-show="moveTask === {{ $task->id }}" @click.outside="closeTask()" class="flex h-full w-full max-w-[780px] flex-col overflow-hidden bg-white shadow-2xl">
            <header class="flex items-start justify-between border-b border-slate-200 px-5 py-4 sm:px-7">
                <div class="min-w-0 pr-4"><div class="flex flex-wrap items-center gap-2"><span class="rounded-md border px-2 py-1 text-[9px] font-black uppercase {{ data_get($agentStyles,$task->agent->key.'.1','border-slate-200 bg-slate-50 text-slate-700') }}">{{ $task->agent->name }}</span><span class="text-xs text-slate-400">Tugas #{{ $task->id }}</span></div><h2 class="mt-2 text-xl font-black leading-snug text-[#0B1B3F]">{{ $task->title }}</h2></div>
                <button type="button" x-ref="taskClose{{ $task->id }}" @click="closeTask()" class="rounded-lg p-1 text-2xl text-slate-400 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500" aria-label="Tutup detail tugas">×</button>
            </header>
            <nav class="flex gap-1 overflow-x-auto border-b border-slate-200 px-5" aria-label="Bagian detail tugas">@foreach(['overview'=>'Ringkasan','output'=>'Output AI','activity'=>'Aktivitas'] as $detailKey=>$detailLabel)<button type="button" @click="detailTab='{{ $detailKey }}'" class="whitespace-nowrap border-b-2 px-3 py-3 text-xs font-bold" :class="detailTab==='{{ $detailKey }}' ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-500'">{{ $detailLabel }}</button>@endforeach @if($latestExecution)<button type="button" @click="detailTab='execution'" class="whitespace-nowrap border-b-2 px-3 py-3 text-xs font-bold" :class="detailTab==='execution' ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-500'">Eksekusi</button>@endif</nav>

            <div class="grid min-h-0 flex-1 overflow-y-auto lg:grid-cols-[minmax(0,1fr)_280px] lg:overflow-hidden">
                <main class="space-y-6 p-5 sm:p-7 lg:overflow-y-auto">
                    <form x-show="detailTab === 'overview'" method="POST" action="{{ route('platform.ai-team.tasks.update',$task) }}" class="space-y-6">@csrf @method('PATCH')
                        <label class="block"><span class="text-[11px] font-black uppercase tracking-[.12em] text-slate-500">Judul</span><input name="title" required maxlength="180" value="{{ $task->title }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold leading-6 text-[#0B1B3F] shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"></label>
                        <label class="block"><span class="text-[11px] font-black uppercase tracking-[.12em] text-slate-500">Deskripsi &amp; catatan</span><textarea name="summary" required maxlength="4000" rows="6" class="mt-2 min-h-40 w-full resize-y rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm leading-7 text-slate-700 shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">{{ $task->summary }}</textarea></label>
                        <div class="grid gap-5">
                            <label class="block text-[11px] font-black uppercase tracking-[.12em] text-slate-500">PIC agen<select name="platform_ai_agent_id" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-3 text-sm font-semibold normal-case tracking-normal text-slate-700 shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected($task->platform_ai_agent_id===$agent->id)>{{ $agent->name }} · {{ data_get($profiles,$agent->key.'.title',$agent->department) }}</option>@endforeach</select></label>
                            <label class="block text-[11px] font-black uppercase tracking-[.12em] text-slate-500">Deadline<input name="scheduled_for" type="datetime-local" value="{{ $task->scheduled_for?->format('Y-m-d\TH:i') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-3 text-sm font-semibold normal-case tracking-normal text-slate-700 shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"></label>
                            <label class="block text-[11px] font-black uppercase tracking-[.12em] text-slate-500">Risiko<select name="risk" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-3 text-sm font-semibold normal-case tracking-normal text-slate-700 shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"><option value="low" @selected($task->risk==='low')>Rendah</option><option value="medium" @selected($task->risk==='medium')>Sedang</option><option value="high" @selected($task->risk==='high')>Tinggi</option></select></label>
                            <label class="block text-[11px] font-black uppercase tracking-[.12em] text-slate-500">Label<input name="labels" maxlength="500" value="{{ implode(', ', data_get($task->payload,'labels',[])) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium normal-case tracking-normal text-slate-700 shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" placeholder="marketing, prioritas"></label>
                        </div>
                        <div class="flex justify-end"><button class="rounded-xl bg-[#0B1B3F] px-5 py-2.5 text-sm font-bold text-white">Simpan detail</button></div>
                    </form>

                    <section x-show="detailTab === 'output'" x-cloak><h3 class="text-xs font-black uppercase tracking-wider text-slate-400">Output yang tersimpan</h3>@if($task->recommendation || $task->result || $task->error)<div class="mt-3 space-y-3 rounded-xl bg-slate-50 p-4 text-sm leading-6 text-slate-600">@if($task->recommendation)<p><b class="text-slate-800">Rekomendasi:</b> {{ $task->recommendation }}</p>@endif @if(data_get($task->result,'report'))<p><b class="text-slate-800">Laporan:</b> {{ data_get($task->result,'report') }}</p>@endif @if($task->error)<p class="text-rose-700"><b>Kendala:</b> {{ $task->error }}</p>@endif</div>@else<div class="mt-3 rounded-xl border border-dashed border-slate-300 p-8 text-center"><b class="text-sm text-slate-700">Belum ada output AI</b><p class="mt-1 text-xs text-slate-500">Hasil akan muncul di sini setelah agen menyimpan hasil pekerjaan.</p></div>@endif</section>

                    @if($latestExecution)<section x-show="detailTab === 'execution'" x-cloak class="space-y-4"><div><h3 class="text-xs font-black uppercase tracking-wider text-slate-400">Eksekusi tercatat</h3><p class="mt-1 text-sm text-slate-500">Data berikut berasal dari record run, bukan dari posisi kartu.</p></div><div class="grid gap-3 sm:grid-cols-2">@foreach($task->executions as $execution)<article class="rounded-xl border border-slate-200 p-4 text-xs"><div class="flex justify-between"><b class="text-[#0B1B3F]">Run #{{ $execution->id }}</b><span class="font-bold uppercase text-sky-700">{{ $execution->status }}</span></div><dl class="mt-3 space-y-2 text-slate-500"><div class="flex justify-between"><dt>Percobaan</dt><dd class="font-bold text-slate-700">{{ $execution->attempt }}</dd></div><div class="flex justify-between"><dt>Mulai</dt><dd class="font-bold text-slate-700">{{ $execution->started_at?->translatedFormat('d M, H:i:s') }}</dd></div><div class="flex justify-between"><dt>Selesai</dt><dd class="font-bold text-slate-700">{{ $execution->finished_at?->translatedFormat('d M, H:i:s') ?? 'Belum' }}</dd></div></dl>@if($execution->error)<p class="mt-3 rounded-lg bg-rose-50 p-2 text-rose-700">{{ $execution->error }}</p>@endif</article>@endforeach</div></section>@endif

                    <section x-show="detailTab === 'overview'"><div class="flex items-center justify-between"><h3 class="text-xs font-black uppercase tracking-wider text-slate-400">Lampiran</h3><span class="text-[10px] text-slate-400">Maks. 10 MB</span></div><div class="mt-2 space-y-2">@forelse(data_get($task->payload,'attachments',[]) as $attachment)<a href="{{ route('platform.ai-team.tasks.attachments.download',[$task,$attachment['id']]) }}" class="flex items-center justify-between rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-bold text-slate-700 hover:border-emerald-300"><span class="truncate">{{ $attachment['name'] }}</span><span class="ml-3 shrink-0 text-slate-400">{{ number_format(($attachment['size'] ?? 0)/1024,1) }} KB</span></a>@empty<p class="rounded-xl border border-dashed border-slate-300 p-4 text-center text-xs text-slate-400">Belum ada lampiran.</p>@endforelse</div><form method="POST" enctype="multipart/form-data" action="{{ route('platform.ai-team.tasks.attachments',$task) }}" class="mt-2 flex gap-2">@csrf<input type="file" name="attachment" required class="min-w-0 flex-1 rounded-xl border border-slate-300 text-xs file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-2.5 file:font-bold"><button class="rounded-xl border border-slate-300 px-3 text-xs font-bold">Unggah</button></form></section>

                    <section x-show="detailTab === 'activity'" x-cloak><h3 class="text-xs font-black uppercase tracking-wider text-slate-400">Komentar &amp; aktivitas</h3><form method="POST" action="{{ route('platform.ai-team.tasks.comments',$task) }}" class="mt-2 flex gap-2">@csrf<input name="comment" required maxlength="1000" class="min-w-0 flex-1 rounded-xl border-slate-300 text-sm" placeholder="Tulis komentar atau arahan…"><button class="rounded-xl bg-emerald-600 px-4 text-xs font-bold text-white">Kirim</button></form><div class="mt-4 space-y-3">@forelse($taskActivities->get($task->id,collect())->take(30) as $activity)<div class="flex gap-3"><span class="mt-1 h-2 w-2 shrink-0 rounded-full {{ $activity->action === 'ai.task.comment' ? 'bg-emerald-500' : 'bg-slate-300' }}"></span><div class="min-w-0"><p class="text-xs leading-5 text-slate-600"><b class="text-slate-800">{{ $activity->admin?->name ?? 'Sistem / AI' }}</b> {{ data_get($activity->metadata,'comment',$activity->description) }}</p><p class="text-[10px] text-slate-400">{{ $activity->created_at->translatedFormat('d M Y, H:i') }}</p></div></div>@empty<p class="text-xs text-slate-400">Belum ada aktivitas tercatat.</p>@endforelse</div></section>
                </main>

                <aside class="space-y-5 border-t border-slate-200 bg-slate-50 p-5 lg:overflow-y-auto lg:border-l lg:border-t-0">
                    <form method="POST" action="{{ route('platform.ai-team.tasks.move',$task) }}">@csrf @method('PATCH')<label class="text-xs font-black uppercase tracking-wider text-slate-400">Status<select name="status" required class="mt-2 w-full rounded-xl border-slate-300 bg-white text-sm"><option value="proposed" @selected($task->status==='proposed')>Menunggu</option><option value="approved" @selected($task->status==='approved')>Berjalan</option><option value="completed" @selected($task->status==='completed')>Selesai</option><option value="rejected" @selected($task->status==='rejected')>Terhambat</option></select></label><button class="mt-2 w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white">Perbarui status</button></form>
                    <div class="space-y-3 rounded-xl border border-slate-200 bg-white p-4 text-xs"><div class="flex justify-between gap-3"><span class="text-slate-500">Pemilik</span><b class="text-right text-slate-700">{{ $task->agent->name }}</b></div><div class="flex justify-between gap-3"><span class="text-slate-500">Dibuat oleh</span><b class="text-right text-slate-700">{{ $createdActivity?->admin?->name ?? (data_get($task->payload,'created_manually') ? 'Admin platform' : 'Sistem / AI') }}</b></div><div class="flex justify-between gap-3"><span class="text-slate-500">Dibuat</span><b class="text-right text-slate-700">{{ $task->created_at->translatedFormat('d M Y, H:i') }}</b></div><div class="flex justify-between gap-3"><span class="text-slate-500">Approval</span><b class="text-right text-slate-700">{{ !$task->requires_approval ? 'Tidak wajib' : ($task->approved_at ? 'Disetujui' : 'Menunggu') }}</b></div><div class="flex justify-between gap-3"><span class="text-slate-500">Eksekusi</span><b class="text-right text-slate-700">{{ $latestExecution ? ucfirst($latestExecution->status) : 'Belum ada run' }}</b></div></div>
                    <div><p class="text-xs font-black uppercase tracking-wider text-slate-400">Label</p><div class="mt-2 flex flex-wrap gap-1.5">@forelse(data_get($task->payload,'labels',[]) as $label)<span class="rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-bold text-emerald-800">{{ $label }}</span>@empty<span class="text-xs text-slate-400">Belum ada label</span>@endforelse</div></div>
                    <form method="POST" action="{{ route('platform.ai-team.tasks.archive',$task) }}" onsubmit="return confirm('Arsipkan tugas ini?')">@csrf @method('DELETE')<button class="w-full rounded-xl border border-rose-200 bg-white px-4 py-2.5 text-xs font-bold text-rose-700 hover:bg-rose-50">Arsipkan tugas</button></form>
                </aside>
            </div>
        </div>
        @endif
        @endforeach
    </div>
</div>

<script>
function aiTeamWorkspace(initialAgent, initialMessages, chatRoutes, readRoutes, initialUnread, prompts, taskData) {
    return {
        tab: sessionStorage.getItem('yokaAiTeamTab') || 'workspace', activeAgent: initialAgent, messages: initialMessages, chatRoutes, readRoutes, unread: initialUnread, prompts,
        draft: '', files: [], sending: false, chatError: '', newTask: false, moveTask: null, intake: false,
        taskData, taskView: localStorage.getItem('yokaAiTaskView') || 'board', taskSearch: '', taskAgent: '', taskStatus: '', taskRisk: '', taskApproval: '', taskTrigger: null,
        draggedTask: null, kanbanOver: null,
        startTaskDrag(event, id, type, status) {
            this.draggedTask={id,type,status};
            this.kanbanOver=null;
            event.dataTransfer.effectAllowed='move';
            event.dataTransfer.setData('text/plain',String(id));
        },
        endTaskDrag() { this.draggedTask=null; this.kanbanOver=null; },
        dropTask(status) {
            const task=this.draggedTask;
            this.kanbanOver=null;
            if (!task || task.status === status) { this.draggedTask=null; return; }
            if (task.type === 'whatsapp_draft' && ['approved','completed'].includes(status)) {
                this.moveTask=task.id;
                this.draggedTask=null;
                return;
            }
            const form=document.getElementById(`kanban-task-${task.id}`);
            if (!form) { this.draggedTask=null; return; }
            form.querySelector('[name="status"]').value=status;
            this.draggedTask=null;
            form.requestSubmit();
        },
        taskMatches(id) {
            const task=this.taskData[id]; if(!task) return false;
            const query=this.taskSearch.trim().toLowerCase();
            const haystack=[task.id,task.title,task.summary,task.agent_name,...(task.labels||[])].join(' ').toLowerCase();
            return (!query || haystack.includes(query)) && (!this.taskAgent || task.agent===this.taskAgent) && (!this.taskStatus || task.status===this.taskStatus) && (!this.taskRisk || task.risk===this.taskRisk) && (!this.taskApproval || task.approval===this.taskApproval);
        },
        visibleTaskCount() { return Object.keys(this.taskData).filter(id=>this.taskMatches(id)).length; },
        resetTaskFilters() { this.taskSearch=''; this.taskAgent=''; this.taskStatus=''; this.taskRisk=''; this.taskApproval=''; },
        openTask(id, trigger=null) { this.taskTrigger=trigger || document.activeElement; this.moveTask=id; const url=new URL(location.href); url.searchParams.set('tab','tasks'); url.searchParams.set('task',id); history.replaceState({},'',url); this.$nextTick(()=>this.$refs['taskClose'+id]?.focus()); },
        closeTask() { const trigger=this.taskTrigger; this.moveTask=null; const url=new URL(location.href); url.searchParams.delete('task'); history.replaceState({},'',url); this.$nextTick(()=>trigger?.focus()); },
        selectTab(tab) { this.tab=tab; sessionStorage.setItem('yokaAiTeamTab',tab); const url=new URL(location.href); url.searchParams.set('tab',tab); history.replaceState({},'',url); },
        selectAgent(id) { this.activeAgent=id; this.draft=''; this.files=[]; this.chatError=''; this.markRead(id); this.$nextTick(()=>this.scrollMessages()); },
        async markRead(id) { if (!this.unread[id]) return; this.unread[id]=0; try { await fetch(this.readRoutes[id],{method:'PATCH',headers:{'Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}}); } catch(error) { } },
        modeLabel(mode) { return mode === 'ai' ? 'AI + data' : (mode === 'fallback' ? 'Fallback data' : 'Data internal'); },
        sendPrompt(prompt) { this.draft=prompt; this.sendMessage(); },
        handleMessageKeydown(event) {
            if (event.key !== 'Enter' || event.shiftKey || event.isComposing || event.keyCode === 229) return;
            event.preventDefault();
            event.stopPropagation();
            this.sendMessage();
        },
        pickFiles(event) { this.addFiles(Array.from(event.target.files || [])); event.target.value=''; },
        pasteFiles(event) {
            const images=Array.from(event.clipboardData?.items || []).filter(item=>item.kind==='file' && item.type.startsWith('image/')).map(item=>item.getAsFile()).filter(Boolean);
            if (!images.length) return;
            event.preventDefault();
            const stamp=new Date().toISOString().slice(0,19).replace(/[:T]/g,'-');
            this.addFiles(images.map((file,i)=>new File([file],`tempelan-${stamp}-${this.files.length+i+1}.${(file.type.split('/')[1]||'png').replace('jpeg','jpg')}`,{type:file.type})));
        },
        addFiles(incoming) {
            this.chatError='';
            const allowed=['pdf','xlsx','xls','csv','txt','md','json','png','jpg','jpeg','webp'];
            for (const file of incoming) {
                const extension=(file.name.split('.').pop() || '').toLowerCase();
                if (!allowed.includes(extension)) { this.chatError=`${file.name}: format file belum didukung.`; continue; }
                if (file.size > 5*1024*1024) { this.chatError=`${file.name}: ukuran maksimal 5 MB.`; continue; }
                if (this.files.length >= 6) { this.chatError='Maksimal 6 file dalam satu pesan.'; break; }
                if (this.files.some(existing=>existing.name===file.name && existing.size===file.size)) continue;
                if (file.type.startsWith('image/')) file.preview=URL.createObjectURL(file);
                this.files.push(file);
            }
        },
        segments(content) {
            const out=[]; const re=/```grafik\s*([\s\S]*?)```/g; let last=0, m;
            const text=String(content || '');
            while ((m=re.exec(text))) {
                if (m.index>last) out.push({type:'text',text:text.slice(last,m.index).trim()});
                try { const chart=JSON.parse(m[1]); if (Array.isArray(chart.items) && chart.items.length) out.push({type:'chart',chart}); } catch(e) {}
                last=re.lastIndex;
            }
            if (last<text.length) out.push({type:'text',text:text.slice(last).trim()});
            return out.filter(seg=>seg.type==='chart' || seg.text);
        },
        chartHtml(c) {
            const esc=s=>String(s ?? '').replace(/[&<>"']/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
            const items=c.items.slice(0,12).map(i=>({label:i.label,value:Number(i.value)||0,shown:i.formatted ?? ((Number(i.value)||0).toLocaleString('id-ID')+(c.unit?(' '+c.unit):''))}));
            const max=Math.max(...items.map(i=>i.value),1);
            let body='';
            if (c.kind==='line' && items.length>1) {
                const w=320,h=120,pad=8; const pts=items.map((it,i)=>[pad+i*(w-2*pad)/(items.length-1), h-pad-(it.value/max)*(h-2*pad)]);
                body=`<svg viewBox="0 0 ${w} ${h}" class="h-32 w-full"><polyline fill="none" stroke="#16A34A" stroke-width="2.5" points="${pts.map(p=>p.join(',')).join(' ')}"/>${pts.map((p,i)=>`<circle cx="${p[0]}" cy="${p[1]}" r="3" fill="#16A34A"><title>${esc(items[i].label)}: ${esc(items[i].shown)}</title></circle>`).join('')}</svg><div class="mt-1 flex justify-between text-[10px] text-slate-500">${items.map(i=>`<span>${esc(i.label)}</span>`).join('')}</div>`;
            } else {
                body=items.map(i=>`<div class="flex items-center gap-2 text-xs"><span class="w-28 shrink-0 truncate text-slate-600" title="${esc(i.label)}">${esc(i.label)}</span><span class="h-3 flex-1 rounded-full bg-slate-100"><span class="block h-3 rounded-full bg-[#16A34A]" style="width:${Math.max(2,Math.round(i.value/max*100))}%"></span></span><span class="w-20 shrink-0 text-right font-bold text-[#0B1B3F]">${esc(i.shown)}</span></div>`).join('<div class="h-1.5"></div>');
            }
            return `<div class="rounded-xl border border-slate-200 bg-white p-3"><p class="text-xs font-black text-[#0B1B3F]">${esc(c.title)}</p>${c.subtitle?`<p class="text-[11px] text-slate-500">${esc(c.subtitle)}</p>`:''}<div class="mt-2">${body}</div>${c.note?`<p class="mt-2 text-[10px] text-slate-400">${esc(c.note)}</p>`:''}</div>`;
        },
        removeFile(index) { this.files.splice(index,1); },
        async sendMessage() {
            const content=this.draft.trim(); if ((!content && !this.files.length) || this.sending) return;
            const agentId=this.activeAgent; const selectedFiles=[...this.files]; const question=content || 'Tolong baca file ini dan beri tahu aku temuan pentingnya.';
            this.sending=true; this.chatError=''; this.draft=''; this.files=[];
            const optimistic={id:'local-'+Date.now(),role:'user',content:question,admin:'Anda',time:'Baru saja',attachments:selectedFiles.map(file=>({name:file.name,size:file.size,preview:file.preview}))};
            if (!this.messages[agentId]) this.messages[agentId]=[]; this.messages[agentId].push(optimistic); this.$nextTick(()=>this.scrollMessages());
            try {
                const body=new FormData(); if(content)body.append('message',content); selectedFiles.forEach(file=>body.append('files[]',file));
                const response=await fetch(this.chatRoutes[agentId],{method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body});
                const data=await response.json(); if(!response.ok) throw new Error(data.message || 'Pesan belum berhasil dikirim.');
                const index=this.messages[agentId].findIndex(item=>item.id===optimistic.id); if(index>=0)this.messages[agentId][index]={...data.user,admin:'Anda',time:'Baru saja',attachments:optimistic.attachments};
                this.messages[agentId].push({...data.assistant,time:'Baru saja'}); this.$nextTick(()=>this.scrollMessages());
            } catch(error) {
                this.messages[agentId]=this.messages[agentId].filter(item=>item.id!==optimistic.id); this.draft=content; this.files=selectedFiles; this.chatError=error.message;
            } finally { this.sending=false; this.$nextTick(()=>this.scrollMessages()); }
        },
        scrollMessages() { const area=this.$refs['messageArea'+this.activeAgent]; if(area)area.scrollTop=area.scrollHeight; },
        init() { const params=new URLSearchParams(location.search); if(params.get('tab')) this.tab=params.get('tab'); if(params.get('task') && this.taskData[params.get('task')]) this.moveTask=Number(params.get('task')); this.$watch('taskView',value=>localStorage.setItem('yokaAiTaskView',value)); this.markRead(this.activeAgent); this.$nextTick(()=>this.scrollMessages()); }
    }
}
</script>
@endsection
