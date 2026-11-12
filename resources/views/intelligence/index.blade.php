@extends('layouts.app')
@section('title', 'Tanya Yoka')
@section('page-title', 'Tanya Yoka')
@section('focus', '1')

@section('content')
@php
    $hasTrialBanner = $currentBusiness?->activeSubscription?->status === 'trial';
    $initialMessages = $activeConversation?->messages?->map(fn($m) => [
        'role' => $m->role, 'content' => $m->content,
        'hasData' => filled($m->tool_name),
        'response' => $m->response,
    ])->values() ?? collect();
@endphp

<div class="flex h-[100dvh] flex-col overflow-hidden bg-white"
     x-data="yokaChat(@js($activeConversation?->id), @js($initialMessages), @js($reportPrompt ?? null))">
    {{-- Bar mode fokus: keluar dari Yoka AI kembali ke dashboard --}}
    <header class="relative z-30 flex h-14 shrink-0 items-center justify-between gap-3 border-b border-[#E5E7EB] bg-white px-3 sm:px-5">
        <a href="{{ route('dashboard') }}" data-yoka-exit aria-label="Kembali ke Dashboard" title="Kembali ke Dashboard" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#E5E7EB] bg-white text-[#0B1B3F] transition hover:border-[#16A34A] hover:bg-[#F4FBF7]">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div class="flex min-w-0 items-center gap-2.5">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#E1F5EA] text-[#0B8F5B]"><svg class="h-4.5 w-4.5" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.5 4.5L18 9l-4.5 1.5L12 15l-1.5-4.5L6 9l4.5-1.5L12 3zM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8L19 15z"/></svg></span>
            <span class="text-[16px] font-extrabold text-[#0B1B3F]">Yoka AI</span>
            <span class="hidden max-w-[220px] truncate rounded-full bg-[#F1F5F9] px-2.5 py-1 text-[12px] font-bold text-slate-600 sm:inline">{{ $currentBusiness->name ?? '' }}</span>
        </div>
        <div class="flex w-10 justify-end"><button type="button" @click="historyOpen = true" class="flex h-10 w-10 items-center justify-center rounded-xl border border-[#E5E7EB] bg-white text-slate-600 transition hover:bg-slate-50 lg:hidden" aria-label="Riwayat percakapan"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg></button></div>
    </header>
    <div class="relative min-h-0 flex-1">
    <div class="flex h-full min-w-0">
        {{-- Riwayat percakapan --}}
        <aside class="absolute lg:relative z-20 inset-y-0 left-0 flex w-[272px] shrink-0 flex-col border-r border-[#EEF0F2] bg-[#FAFBFA] transition-transform lg:translate-x-0"
               :class="historyOpen ? 'translate-x-0 shadow-2xl lg:shadow-none' : '-translate-x-full'">
            <div class="space-y-0.5 px-3 pt-3">
                <a href="{{ route('intelligence.index') }}" class="flex h-10 items-center gap-3 rounded-lg px-3 text-[14px] font-bold text-[#0B1B3F] transition hover:bg-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                    Percakapan baru
                </a>
                <button type="button" @click="searchOpen = !searchOpen; $nextTick(() => searchOpen && $refs.search.focus())" class="flex h-10 w-full items-center gap-3 rounded-lg px-3 text-left text-[14px] font-bold text-[#0B1B3F] transition hover:bg-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M20 20l-3.5-3.5"/></svg>
                    Cari
                </button>
                <div x-show="searchOpen" x-cloak class="px-1 pb-1 pt-0.5"><input x-ref="search" x-model="search" type="text" placeholder="Cari percakapan..." class="h-9 w-full rounded-lg border border-[#E5E7EB] bg-white px-3 text-[13px] font-semibold text-slate-700 placeholder:font-medium focus:border-[#65B98E] focus:outline-none focus:ring-2 focus:ring-[#E1F5EA]"></div>
            </div>

            <div class="mt-3 flex min-h-0 flex-1 flex-col">
                <button type="button" @click="listOpen = !listOpen" class="mx-3 flex items-center gap-1.5 px-3 py-1.5 text-left text-[13px] font-bold text-slate-500 hover:text-slate-700">
                    Terbaru
                    <svg class="h-3.5 w-3.5 transition-transform" :class="listOpen ? '' : '-rotate-90'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.6"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="listOpen" class="min-h-0 flex-1 overflow-y-auto px-2 pb-2">
                    @forelse($conversations as $conversation)
                    <div id="conversation-{{ $conversation->id }}" class="group relative" data-t="{{ \Illuminate\Support\Str::lower($conversation->title ?: 'percakapan baru') }}" x-show="!search || $el.dataset.t.includes(search.toLowerCase())">
                        <a href="{{ route('intelligence.index', ['conversation' => $conversation->id]) }}" title="{{ $conversation->updated_at->diffForHumans() }}"
                           class="block truncate rounded-lg py-2 pl-3 pr-9 text-[14px] transition {{ $activeConversation?->id === $conversation->id ? 'bg-[#E8F5ED] font-bold text-[#087A4E]' : 'font-semibold text-slate-700 hover:bg-white' }}">{{ $conversation->title ?: 'Percakapan baru' }}</a>
                        <button type="button" @click.prevent="deleteConversation({{ $conversation->id }})" title="Hapus percakapan"
                                class="absolute right-1.5 top-1.5 h-7 w-7 rounded-md text-slate-400 opacity-0 hover:bg-red-50 hover:text-red-600 focus:opacity-100 group-hover:opacity-100">&times;</button>
                    </div>
                    @empty
                    <p class="px-3 py-4 text-[13px] leading-relaxed text-slate-500">Belum ada percakapan. Mulai dengan pertanyaan tentang penjualan atau operasional bisnis.</p>
                    @endforelse
                </div>
            </div>

            <div class="border-t border-[#EEF0F2] p-2" x-data="{ insightOpen: false }">
                <button type="button" @click="insightOpen = !insightOpen" class="flex h-11 w-full items-center gap-3 rounded-lg px-3 text-left transition hover:bg-white" :aria-expanded="insightOpen">
                    <span class="flex h-7 w-7 items-center justify-center rounded-md bg-[#E1F5EA] text-[#0B8F5B]"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l1.5 4.5L18 9l-4.5 1.5L12 15l-1.5-4.5L6 9l4.5-1.5L12 3z"/></svg></span>
                    <span class="flex-1 text-[14px] font-bold text-[#0B1B3F]">Insight aktif</span>
                    @if($businessInsights->count())<span class="rounded-full bg-amber-100 px-2 py-0.5 text-[12px] font-bold text-amber-800">{{ $businessInsights->count() }}</span>@endif
                    <svg class="h-3.5 w-3.5 text-slate-400 transition-transform" :class="insightOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.6"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="insightOpen" x-cloak class="max-h-56 space-y-1.5 overflow-y-auto px-1 pb-1 pt-1">
                    @forelse($businessInsights->take(5) as $insight)
                    <div class="rounded-lg bg-white px-3 py-2 ring-1 ring-[#EEF0F2]">
                        <p class="text-[13px] font-bold leading-snug text-slate-700">{{ $insight->title }}</p>
                        <p class="mt-0.5 text-[12px] text-slate-500">{{ $insight->confidence ?? 0 }}% yakin</p>
                    </div>
                    @empty
                    <p class="px-2 py-1 text-[13px] text-slate-500">Tidak ada anomali penting saat ini.</p>
                    @endforelse
                </div>
                @if(config('pos.features.intelligence_agents') && $intelligenceAgents->isNotEmpty())
                <details class="mt-1 rounded-lg px-3 py-2">
                    <summary class="cursor-pointer text-[13px] font-bold text-slate-600">Agen terjadwal</summary>
                    <div class="mt-2 space-y-2">
                        @foreach($intelligenceAgents as $agent)
                        <div class="flex items-center justify-between gap-2 text-[13px]"><span>{{ str($agent->agent_key)->replace('_', ' ')->title() }}</span><button type="button" @click="toggleAgent({{ $agent->id }}, $event)" class="rounded-full px-2 py-1 font-semibold {{ $agent->enabled ? 'bg-[#E1F5EA] text-[#087A4E]' : 'bg-slate-100 text-slate-500' }}">{{ $agent->enabled ? 'Aktif' : 'Nonaktif' }}</button></div>
                        @endforeach
                    </div>
                </details>
                @endif
            </div>
        </aside>

        <div x-show="historyOpen" x-cloak @click="historyOpen=false" class="absolute inset-0 z-10 bg-black/30 lg:hidden"></div>

        {{-- Chat utama --}}
        <section class="relative flex-1 min-w-0 flex flex-col bg-white">

            <div x-ref="messages" x-show="messages.length > 0" class="flex-1 min-h-0 overflow-y-auto scroll-smooth">
                <div class="mx-auto w-full max-w-4xl px-4 sm:px-6 py-8 sm:py-12">
                    <div x-show="messages.length > 0" class="space-y-7">
                        <template x-for="(message, i) in messages" :key="i">
                            <div class="flex items-start gap-3 sm:gap-4" :class="message.role === 'user' ? 'justify-end' : ''">
                                <div x-show="message.role !== 'user'" class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-[#CFE7D9] bg-white shadow-sm"><img src="{{ asset('images/brand/yoka-icon.png') }}" alt="Yoka POS" class="h-6 w-6 object-contain"></div>
                                <x-avatar :user="auth()->user()" x-show="message.role === 'user'" class="order-last mt-0.5 h-8 w-8 ring-1 ring-[#CFE7D9]" text="text-[13px]"/>
                                <div class="max-w-[92%] sm:max-w-[86%] min-w-0" :class="message.role !== 'user' && message.response?.blocks?.length ? 'w-full' : ''">
                                    <div x-show="message.role !== 'user' && message.response?.thinking?.steps?.length" x-data="{o:false}" class="mb-3 border-b border-[#E5E7EB] pb-2"><button type="button" @click="o=!o" class="text-[13px] text-slate-400 transition hover:text-slate-600">Memproses selama <span x-text="message.response?.thinking?.seconds"></span> detik <span x-text="o ? '⌄' : '›'"></span></button><ul x-show="o" x-cloak class="mt-2 space-y-1 text-[12px] text-slate-500"><template x-for="step in (message.response?.thinking?.steps || [])"><li x-text="'• ' + step"></li></template></ul></div>
                                    <div class="text-sm sm:text-[15px] leading-7" :class="message.role === 'user' ? 'whitespace-pre-wrap rounded-2xl rounded-br-md bg-[#E8F7EF] px-4 py-2.5 text-[#0B1B3F]' : 'yoka-md text-slate-700'" x-html="message.role === 'user' ? esc(message.content) : md(message.content, message.response?.visuals)"></div>
                                    <div x-show="message.role === 'user' && (message.attachments || message.meta?.attachments || []).length" class="mt-1 flex flex-wrap justify-end gap-1.5"><template x-for="name in (message.attachments || message.meta?.attachments || [])"><span class="rounded-md bg-slate-100 px-2 py-1 text-[12px] font-semibold text-slate-500" x-text="'📎 ' + name"></span></template></div>
                                    <div x-show="message.response?.blocks?.length" class="mt-4 space-y-3">
                                        <template x-for="(block, blockIndex) in (message.response?.blocks || [])" :key="blockIndex">
                                            <section class="overflow-hidden rounded-2xl border border-[#DDE9E2] bg-white shadow-sm" :aria-label="block.title">
                                                <div class="flex items-center justify-between border-b border-[#E5E7EB] bg-[#F8FBF9] px-4 py-3"><p class="text-xs font-bold text-[#0B1B3F]" x-text="block.title"></p><span class="rounded-full bg-[#E1F5EA] px-2 py-1 text-[12px] font-bold uppercase tracking-wider text-[#087A4E]">Data aktual</span></div>
                                                <div x-show="block.type === 'kpi'" class="grid grid-cols-2 gap-px bg-[#E5E7EB] sm:grid-cols-4"><template x-for="metric in (block.metrics || [])" :key="metric.label"><div class="bg-white px-4 py-3"><p class="text-[12px] font-semibold uppercase tracking-wide text-slate-400" x-text="metric.label"></p><p class="mt-1 text-sm font-extrabold text-[#0B1B3F]" x-text="metric.formatted"></p></div></template></div>
                                                <div x-show="block.type === 'bar_chart'" class="p-4"><p class="mb-4 text-[12px] text-slate-400"><span x-text="block.y_label"></span> · <span x-text="block.x_label"></span></p><div class="space-y-3"><template x-for="item in (block.items || [])" :key="item.label"><div class="grid grid-cols-[90px_minmax(0,1fr)_auto] items-center gap-3 text-[12px]"><span class="truncate font-semibold text-slate-600" x-text="item.label" :title="item.label"></span><div class="h-2.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-gradient-to-r from-[#0B8F5B] to-[#34C67F]" :style="`width:${barWidth(item.value, block.items)}%`"></div></div><span class="min-w-[76px] text-right font-bold text-[#0B1B3F]" x-text="item.formatted"></span></div></template></div></div>
                                                <div x-show="block.type === 'line_chart'" class="p-4"><p class="mb-2 text-[12px] text-slate-400"><span x-text="block.x_label"></span> · <span x-text="block.y_label"></span></p><div x-show="block.points?.length" class="relative h-44 rounded-xl bg-gradient-to-b from-[#F4FBF7] to-white p-3"><svg viewBox="0 0 100 50" preserveAspectRatio="none" class="h-32 w-full overflow-visible" role="img" :aria-label="block.title"><polyline :points="linePoints(block.points)" fill="none" stroke="#0B8F5B" stroke-width="1.6" vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round"></polyline><template x-for="(point,index) in lineDots(block.points)" :key="`${point.date}-${index}`"><circle :cx="point.x" :cy="point.y" r="1.8" fill="#0B8F5B" stroke="white" stroke-width="1" vector-effect="non-scaling-stroke"><title x-text="`${point.date}: ${point.formatted || point.value}`"></title></circle></template></svg><div class="mt-1 flex justify-between text-[12px] text-slate-400"><span x-text="block.points?.[0]?.date"></span><span x-text="block.points?.[block.points.length-1]?.date"></span></div></div></div>
                                                <div x-show="block.type === 'table'" class="overflow-x-auto"><table class="w-full min-w-[500px] text-left text-xs"><thead class="bg-slate-50 text-[12px] uppercase tracking-wide text-slate-400"><tr><template x-for="column in (block.columns || [])" :key="column.key"><th class="px-4 py-2.5 font-bold" x-text="column.label"></th></template></tr></thead><tbody class="divide-y divide-slate-100"><template x-for="(row, rowIndex) in (block.rows || [])" :key="rowIndex"><tr><template x-for="(column, cellIndex) in (block.columns || [])" :key="column.key"><td class="px-4 py-3 text-slate-600" :class="cellIndex === 0 ? 'font-semibold text-[#0B1B3F]' : ''" x-text="row[column.key] ?? '—'"></td></template></tr></template></tbody></table></div>
                                                <div x-show="block.type === 'action_preview'" class="p-4"><p class="text-sm font-bold text-[#0B1B3F]" x-text="block.target"></p><div class="mt-3 space-y-2"><template x-for="change in (block.changes || [])"><div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-xs"><span x-text="change.field"></span><span><span class="text-slate-400 line-through" x-text="change.before"></span><span class="mx-1">→</span><strong x-text="change.after"></strong></span></div></template></div><p class="mt-3 text-[12px] text-amber-700">Perubahan belum diterapkan. Persetujuan akan divalidasi ulang sebelum eksekusi.</p><button x-show="block.status === 'pending'" type="button" @click="approveAndExecute(block)" :disabled="actionLoading" class="mt-3 rounded-lg bg-[#0B8F5B] px-4 py-2 text-xs font-bold text-white disabled:opacity-50">Setujui & jalankan</button><p x-show="block.status === 'executed'" class="mt-3 text-xs font-bold text-[#087A4E]">Perubahan berhasil diterapkan dan tercatat di audit.</p></div>
                                            </section>
                                        </template>
                                    </div>
                                    <div x-show="message.response?.sources?.length" class="mt-4 flex flex-wrap items-center gap-2"><span class="text-[12px] font-semibold uppercase tracking-wide text-slate-400">Sumber</span><template x-for="s in (message.response?.sources || [])" :key="s.url"><a :href="s.url" target="_blank" rel="noopener noreferrer nofollow" class="inline-flex max-w-[220px] items-center gap-1.5 truncate rounded-full border border-[#E5E7EB] bg-white px-2.5 py-1 text-[12px] font-semibold text-slate-600 hover:border-[#16A34A] hover:text-[#087A4E]" :title="s.title"><span class="h-1.5 w-1.5 shrink-0 rounded-full bg-[#16A34A]"></span><span class="truncate" x-text="s.host"></span></a></template></div>
                                    <div x-show="message.response?.uncertainty?.length" class="mt-2 rounded-xl bg-amber-50 px-3 py-2 text-[12px] leading-5 text-amber-800"><template x-for="note in (message.response?.uncertainty || [])"><p x-text="note"></p></template></div>
                                    <div x-show="message.role !== 'user' && message.hasData" class="mt-2 flex flex-wrap items-center gap-2 text-[12px] text-slate-400"><span>Dihitung dari data bisnis kamu</span><span x-show="message.response?.as_of">·</span><span x-show="message.response?.as_of" x-text="`Per ${formatAsOf(message.response.as_of)}`"></span><span x-show="message.response?.timezone" class="rounded bg-slate-100 px-1.5 py-0.5" x-text="message.response.timezone"></span></div>
                                    <div x-show="message.response?.suggestions?.length" class="mt-3 flex flex-wrap gap-2"><template x-for="suggestion in (message.response?.suggestions || [])" :key="suggestion"><button type="button" @click="question=suggestion; ask()" class="rounded-full border border-[#CFE7D9] px-3 py-1.5 text-[12px] font-semibold text-[#087A4E] hover:bg-[#F0FAF4]" x-text="suggestion"></button></template></div>
                                </div>
                            </div>
                        </template>
                        <div x-show="loading && !streaming" class="flex items-start gap-4">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-[#CFE7D9] bg-white shadow-sm"><img src="{{ asset('images/brand/yoka-icon.png') }}" alt="Yoka POS" class="h-6 w-6 object-contain"></div>
                            <div class="pt-2 flex gap-1"><span class="w-2 h-2 rounded-full bg-[#16A34A] animate-bounce"></span><span class="w-2 h-2 rounded-full bg-[#16A34A] animate-bounce [animation-delay:120ms]"></span><span class="w-2 h-2 rounded-full bg-[#16A34A] animate-bounce [animation-delay:240ms]"></span><span class="ml-2 -mt-0.5 text-xs font-medium text-slate-400" x-text="status"></span></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Composer --}}
            <div class="px-3 sm:px-6" :class="messages.length === 0 ? 'flex min-h-0 flex-1 flex-col items-center overflow-y-auto pb-6 pt-4 before:flex-1 before:content-[\'\'] after:flex-1 after:content-[\'\'] [&>*]:shrink-0' : 'shrink-0 bg-gradient-to-t from-white via-white to-white/70 pb-4 pt-2'">
                <div x-show="messages.length === 0" class="mb-6 flex w-full max-w-3xl flex-col items-center text-center sm:mb-8">
                    <img src="{{ asset('images/brand/yoka-icon.png') }}" alt="Yoka" class="h-14 w-14 object-contain">
                    <h2 class="mt-5 text-[28px] font-extrabold leading-tight tracking-tight text-[#0B1B3F] sm:text-[34px]" x-text="greeting"></h2>
                    <p class="mt-2 text-[15px] font-semibold text-slate-500">Apa yang ingin kamu ketahui tentang bisnismu hari ini?</p>
                </div>
                <form @submit.prevent="ask" class="mx-auto w-full rounded-2xl border border-slate-300 bg-white p-2 shadow-[0_8px_30px_rgba(15,23,42,.08)] focus-within:border-[#65B98E] focus-within:ring-4 focus-within:ring-[#E1F5EA] transition" :class="messages.length === 0 ? 'max-w-3xl' : 'max-w-4xl'">
                    <div x-show="files.length" class="flex flex-wrap gap-1.5 px-2 pb-1 pt-0.5"><template x-for="(f,i) in files" :key="i"><span class="inline-flex items-center gap-1.5 rounded-lg bg-[#E8F7EF] px-2.5 py-1 text-[12px] font-semibold text-[#087A4E]"><span x-text="f.name"></span><button type="button" @click="files.splice(i,1)" class="text-[#087A4E]/60 hover:text-red-600" aria-label="Hapus lampiran">×</button></span></template></div>
                    <div class="flex items-end gap-2">
                        <input type="file" x-ref="picker" multiple accept="{{ \App\Services\Intelligence\AttachmentService::ACCEPT }}" class="hidden" @change="pick($event)">
                        <button type="button" @click="$refs.picker.click()" class="mb-0.5 flex h-10 w-10 items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-100 hover:text-[#0B8F5B]" aria-label="Lampirkan dokumen" title="Lampirkan dokumen (PDF, Excel, CSV, gambar)"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.5l-8.6 8.6a5 5 0 01-7.1-7.1l9-9a3.3 3.3 0 014.7 4.7l-9 9a1.7 1.7 0 01-2.4-2.4l8.3-8.3"/></svg></button>
                        <textarea x-model="question" x-ref="input" @keydown.enter="if(!$event.shiftKey){$event.preventDefault();ask()}" rows="1" maxlength="2000" placeholder="Tanya apa saja soal bisnismu..." class="max-h-32 min-h-11 flex-1 resize-none border-0 bg-transparent px-3 py-3 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-0"></textarea>
                        <button type="submit" :disabled="loading || !question.trim()" class="w-10 h-10 mb-0.5 rounded-xl bg-[#0B8F5B] hover:bg-[#087A4E] disabled:bg-slate-200 disabled:text-slate-400 text-white flex items-center justify-center transition" aria-label="Kirim">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m-6 6l6-6 6 6"/></svg>
                        </button>
                    </div>
                </form>
                <div x-show="messages.length === 0" class="mt-4 flex w-full max-w-3xl flex-wrap justify-center gap-2.5">
                    <template x-for="chip in chips" :key="chip.text">
                        <button type="button" @click="question = chip.text; ask()" class="inline-flex h-10 items-center gap-2 rounded-full border border-[#E5E7EB] bg-white px-4 text-[14px] font-bold text-[#0B1B3F] shadow-sm transition hover:border-[#9DD8BA] hover:bg-[#F4FBF7]">
                            <span class="text-[#0B8F5B]" x-text="chip.icon"></span><span x-text="chip.title"></span>
                        </button>
                    </template>
                </div>
                <div x-show="messages.length === 0" class="mt-6 w-full max-w-3xl">
                    <p class="mb-2 px-1 text-[13px] font-bold uppercase tracking-wide text-slate-400">Coba tanyakan</p>
                    <div class="divide-y divide-[#EEF0F2] overflow-hidden rounded-2xl border border-[#EEF0F2] bg-white">
                        <template x-for="idea in ideas" :key="idea">
                            <button type="button" @click="question = idea; ask()" class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left text-[14px] font-semibold text-slate-700 transition hover:bg-[#F4FBF7] hover:text-[#087A4E]">
                                <span x-text="idea"></span>
                                <svg class="h-4 w-4 shrink-0 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </template>
                    </div>
                </div>
                <p class="mt-3 text-center text-[12px] text-slate-400">Yoka dapat melakukan kesalahan. Cocokkan keputusan penting dengan laporan utama.</p>
            </div>
        </section>
    </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function yokaChat(initialConversation, initialMessages, initialQuestion) {
    return {
        historyOpen: false, search: '', searchOpen: false, listOpen: true, files: [], loading: false, streaming: false, status: 'Memahami pertanyaan…', actionLoading: false, question: initialQuestion || '', conversationId: initialConversation, messages: initialMessages || [],
        greeting: (() => { const h = new Date().getHours(), p = h < 11 ? 'pagi' : (h < 15 ? 'siang' : (h < 18 ? 'sore' : 'malam')); return 'Selamat ' + p + ', ' + @js(\Illuminate\Support\Str::of(auth()->user()->name)->before(' ')->title()->toString()) + '!' })(),
        chips: [
            {icon:'↗',title:'Ringkasan penjualan',text:'Bagaimana omzet hari ini?'},
            {icon:'★',title:'Produk terlaris',text:'Produk paling laris bulan ini'},
            {icon:'!',title:'Kondisi stok',text:'Stok apa yang perlu segera dibeli?'},
            {icon:'◷',title:'Jam ramai',text:'Jam berapa paling ramai bulan ini?'},
            {icon:'✦',title:'Ide promo',text:'Kasih ide promo untuk menaikkan omzet bulan ini'},
            {icon:'✓',title:'Audit bisnis',text:'Audit lengkap bisnisku, apa yang harus aku perbaiki?'},
        ],
        ideas: [
            'Bandingkan omzet minggu ini dengan minggu lalu',
            'Menu mana yang laris tapi margin rendah, dan kurang laku tapi margin tinggi?',
            'Buatkan caption Instagram untuk produk terlaris minggu ini',
            'Pelanggan mana yang sudah lama tidak datang dan bagaimana mengajak mereka kembali?',
            'Bagaimana tren pasar kopi susu sekarang dan posisi tokoku?',
        ],
        prompts: [
            {icon:'↗',title:'Ringkasan penjualan',text:'Bagaimana omzet hari ini?'},
            {icon:'★',title:'Produk unggulan',text:'Produk paling laris bulan ini'},
            {icon:'!',title:'Kondisi stok',text:'Stok apa yang perlu segera dibeli?'},
            {icon:'◷',title:'Waktu operasional',text:'Jam berapa paling ramai bulan ini?'},
        ],
        init(){this.scroll()},
        pick(e){const max=3,limit=5*1024*1024;for(const f of e.target.files){if(this.files.length>=max){alert('Maksimal 3 file per pesan.');break}if(f.size>limit){alert(f.name+' lebih dari 5 MB.');continue}this.files.push(f)}e.target.value=''},
        esc(t){return String(t??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))},
        // Safe mini-Markdown: everything is HTML-escaped first, then only a fixed set of tags is produced.
        md(src,visuals){
            const inline=s=>this.esc(s).replace(/\*\*(.+?)\*\*/g,'<strong class="font-bold text-[#0B1B3F]">$1</strong>').replace(/(^|[^*])\*(?!\s)(.+?)\*/g,'$1<em>$2</em>').replace(/`([^`]+)`/g,'<code class="rounded bg-slate-100 px-1 text-[0.9em]">$1</code>');
            const lines=String(src??'').replace(/\r/g,'').split('\n');let out='',list=null,i=0;
            const close=()=>{if(list){out+=`</${list}>`;list=null}};
            while(i<lines.length){
                const l=lines[i];
                if(/^\s*\|.*\|\s*$/.test(l)&&/^\s*\|[\s:|-]+\|\s*$/.test(lines[i+1]||'')){
                    close();const cells=r=>r.trim().replace(/^\||\|$/g,'').split('|').map(c=>c.trim());
                    let t='<div class="my-4 overflow-x-auto rounded-2xl border border-[#E5E7EB]"><table class="w-full text-left text-[13px]"><thead class="bg-slate-50 text-[12px] uppercase tracking-wide text-slate-500"><tr>'+cells(l).map(c=>`<th class="px-4 py-2.5 font-bold">${inline(c)}</th>`).join('')+'</tr></thead><tbody class="divide-y divide-slate-100">';i+=2;
                    while(i<lines.length&&/^\s*\|.*\|\s*$/.test(lines[i])){t+='<tr>'+cells(lines[i]).map(c=>`<td class="px-4 py-2.5">${inline(c)}</td>`).join('')+'</tr>';i++}
                    out+=t+'</tbody></table></div>';continue;
                }
                let m;
                if(m=l.match(/^\[\[visual:(\d+)\]\]$/)){close();out+=this.visual((visuals||[])[+m[1]])}
                else if(m=l.match(/^#{1,4}\s+(.*)$/)){close();out+=`<h3 class="mt-7 mb-2 text-[17px] font-extrabold leading-snug text-[#0B1B3F]">${inline(m[1])}</h3>`}
                else if(m=l.match(/^\s*\*\*(\d+[.)]\s.+?)\*\*\s*$/)){close();out+=`<h3 class="mt-7 mb-2 text-[17px] font-extrabold leading-snug text-[#0B1B3F]">${this.esc(m[1])}</h3>`}
                else if(m=l.match(/^\s*[-*•]\s+(.*)$/)){if(list!=='ul'){close();out+='<ul class="my-3 list-disc space-y-1.5 pl-6 marker:text-[#16A34A]">';list='ul'}out+=`<li>${inline(m[1])}</li>`}
                else if(m=l.match(/^\s*\d+[.)]\s+(.*)$/)){if(list!=='ol'){close();out+='<ol class="my-3 list-decimal space-y-1.5 pl-6 marker:font-bold marker:text-[#16A34A]">';list='ol'}out+=`<li>${inline(m[1])}</li>`}
                else if(m=l.match(/^>\s?(.*)$/)){close();out+=`<blockquote class="my-3 border-l-4 border-[#16A34A]/40 bg-[#F4FBF7] px-4 py-2 text-slate-600">${inline(m[1])}</blockquote>`}
                else if(/^\s*---+\s*$/.test(l)){close();out+='<hr class="my-5 border-[#E5E7EB]">'}
                else if(!l.trim()){close()}
                else{close();out+=`<p class="my-2.5">${inline(l)}</p>`}
                i++;
            }
            close();return out;
        },
        // Trusted visual cards. Content was validated server-side; every string is still escaped here.
        visual(v){
            if(!v)return '';const e=x=>this.esc(x);
            const head=(t,s)=>(t?`<p class="text-[12px] font-semibold text-slate-500">${e(t)}</p>`:'')+(s?`<p class="mt-0.5 text-[12px] text-slate-400">${e(s)}</p>`:'');
            const wrap=b=>`<div class="my-5 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-[0_1px_2px_rgba(15,23,42,.04)]">${b}</div>`;
            const note=n=>n?`<p class="mt-3 text-[12px] text-slate-400">${e(n)}</p>`:'';
            if(v.type==='chart'){
                const items=v.items||[],max=Math.max(...items.map(i=>Math.abs(i.value)),1),fmt=i=>i.formatted||new Intl.NumberFormat('id-ID').format(i.value)+(v.unit?' '+v.unit:'');
                let body;
                if(v.kind==='hbar'){body='<div class="mt-4 space-y-3">'+items.map(i=>`<div class="grid grid-cols-[110px_minmax(0,1fr)_auto] items-center gap-3 text-[12px]"><span class="truncate font-semibold text-slate-600">${e(i.label)}</span><div class="h-3 rounded-full bg-slate-100"><div class="h-3 rounded-full bg-gradient-to-r from-[#16A34A] to-[#4ADE80]" style="width:${Math.max(3,Math.round(Math.abs(i.value)/max*100))}%"></div></div><span class="font-bold text-[#0B1B3F]">${e(fmt(i))}</span></div>`).join('')+'</div>'}
                else if(v.kind==='line'){const n=items.length,pts=items.map((i,k)=>`${n===1?50:k/(n-1)*100},${46-Math.max(0,i.value)/max*42}`).join(' ');body=`<div class="mt-4"><svg viewBox="0 0 100 50" preserveAspectRatio="none" class="h-40 w-full overflow-visible"><polyline points="${pts}" fill="none" stroke="#16A34A" stroke-width="1.8" vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round"/>${items.map((i,k)=>`<circle cx="${n===1?50:k/(n-1)*100}" cy="${46-Math.max(0,i.value)/max*42}" r="1.6" fill="#16A34A" stroke="#fff" stroke-width="1" vector-effect="non-scaling-stroke"><title>${e(i.label)}: ${e(fmt(i))}</title></circle>`).join('')}</svg><div class="mt-1 flex justify-between text-[12px] text-slate-400"><span>${e(items[0].label)}</span><span>${e(items[n-1].label)}</span></div></div>`}
                else{body='<div class="mt-5 flex h-48 items-end gap-3 border-b border-slate-200">'+items.map(i=>`<div class="flex h-full flex-1 flex-col items-center justify-end gap-1.5"><span class="text-[12px] font-bold text-[#0B1B3F]">${e(fmt(i))}</span><div class="w-full max-w-[64px] rounded-t-lg bg-gradient-to-t from-[#16A34A] to-[#4ADE80]" style="height:${Math.max(4,Math.round(Math.abs(i.value)/max*78))}%"></div></div>`).join('')+'</div><div class="mt-2 flex gap-3">'+items.map(i=>`<span class="flex-1 truncate text-center text-[12px] text-slate-500">${e(i.label)}</span>`).join('')+'</div>'}
                return wrap(head(v.title,'')+(v.headline?`<p class="mt-1 text-[26px] font-extrabold tracking-tight text-[#16A34A]">${e(v.headline)}</p>`:'')+(v.subtitle?`<p class="text-[12px] text-slate-400">${e(v.subtitle)}</p>`:'')+body+note(v.note));
            }
            if(v.type==='kpi')return wrap(head(v.title,v.subtitle)+`<div class="mt-3 grid grid-cols-2 gap-px overflow-hidden rounded-xl bg-[#E5E7EB] sm:grid-cols-${Math.min(4,(v.metrics||[]).length||1)}">`+(v.metrics||[]).map(m=>`<div class="bg-white px-4 py-3"><p class="text-[12px] font-semibold uppercase tracking-wide text-slate-400">${e(m.label)}</p><p class="mt-1 text-[18px] font-extrabold text-[#0B1B3F]">${e(m.value)}</p>${m.note?`<p class="text-[12px] text-slate-400">${e(m.note)}</p>`:''}</div>`).join('')+'</div>'+note(v.note));
            if(v.type==='steps')return wrap(head(v.title,v.subtitle)+'<div class="mt-4 space-y-1">'+(v.steps||[]).map((s,k,a)=>`<div class="rounded-xl ${s.highlight?'border border-[#16A34A]/30 bg-[#F4FBF7]':''} px-3 py-2.5"><p class="text-[14px] font-bold text-[#0B1B3F]">${e(s.title)}</p>${s.text?`<p class="mt-0.5 text-[12.5px] text-slate-500">${e(s.text)}</p>`:''}</div>${k<a.length-1?'<div class="text-center text-slate-300">↓</div>':''}`).join('')+'</div>'+note(v.note));
            if(v.type==='roadmap')return wrap(head(v.title,v.subtitle)+'<div class="mt-4 space-y-4">'+(v.items||[]).map((s,k)=>`<div class="flex gap-4"><span class="mt-0.5 h-fit shrink-0 rounded-lg ${k===0?'bg-[#E1F5EA] text-[#087A4E]':'text-slate-500'} px-2.5 py-1 text-[13px] font-bold">${e(s.when)}</span><div><p class="text-[14px] font-bold text-[#0B1B3F]">${e(s.title)}</p>${s.detail?`<p class="text-[13px] font-semibold text-slate-700">${e(s.detail)}</p>`:''}${s.text?`<p class="text-[12px] text-slate-400">${e(s.text)}</p>`:''}</div></div>`).join('')+'</div>'+note(v.note));
            if(v.type==='callout'){const c={info:'border-sky-200 bg-sky-50 text-sky-900',warn:'border-amber-200 bg-amber-50 text-amber-900',success:'border-emerald-200 bg-emerald-50 text-emerald-900'}[v.tone]||'';return `<div class="my-4 rounded-2xl border ${c} px-4 py-3 text-[14px]">${v.title?`<p class="font-bold">${e(v.title)}</p>`:''}<p>${e(v.text)}</p></div>`}
            return '';
        },
        async ask(){
            const text=this.question.trim(); if(!text||this.loading)return;
            const sending=this.files.slice(); const form=new FormData(); form.append('question',text); if(this.conversationId)form.append('conversation_id',this.conversationId); sending.forEach(f=>form.append('files[]',f));
            this.messages.push({role:'user',content:text,attachments:sending.map(f=>f.name)}); this.question=''; this.files=[]; this.loading=true; this.scroll();
            this.status='Memahami pertanyaan…'; let live=null, raw='', done=null;
            const view=t=>t.replace(/```yoka[\s\S]*?```/g,'').replace(/```yoka[\s\S]*$/,'').replace(/\n\s*\**\s*SARAN\s*\**\s*:[\s\S]*$/i,'');
            try{
                const response=await fetch('{{ route('intelligence.ask.stream') }}',{method:'POST',headers:{'Accept':'text/event-stream','X-CSRF-TOKEN':'{{ csrf_token() }}'},body:form});
                if(!response.ok||!response.body){let m='Yoka gagal membaca data.';try{const j=await response.json();m=j.message||(j.errors?Object.values(j.errors).flat()[0]:m)}catch{}throw new Error(m)}
                const reader=response.body.getReader(), dec=new TextDecoder(); let buf='';
                const handle=(ev,d)=>{
                    if(ev==='status'){this.status=d.label}
                    else if(ev==='token'){
                        if(!live){this.messages.push({role:'assistant',content:'',streamed:true});live=this.messages[this.messages.length-1];this.streaming=true}
                        raw+=d.t; live.content=view(raw); this.scroll();
                    }
                    else if(ev==='done'){done=d}
                    else if(ev==='fail'){throw new Error(d.message)}
                };
                while(true){
                    const {value,done:end}=await reader.read(); if(end)break;
                    buf+=dec.decode(value,{stream:true}); let i;
                    while((i=buf.indexOf('\n\n'))>=0){
                        const block=buf.slice(0,i); buf=buf.slice(i+2);
                        const ev=(block.match(/^event: (.+)$/m)||[])[1], dl=(block.match(/^data: (.+)$/m)||[])[1];
                        if(ev&&dl)handle(ev,JSON.parse(dl));
                    }
                }
                if(!done)throw new Error('Koneksi terputus sebelum jawaban selesai.');
                const isNew=!this.conversationId; this.conversationId=done.conversation_id;
                const final={role:'assistant',content:done.answer,hasData:!!done.tool,response:done.response};
                if(live){Object.assign(live,final)}else{this.messages.push(final)}
                if(isNew) history.replaceState({},'', '{{ route('intelligence.index') }}?conversation='+done.conversation_id);
            }catch(error){
                const msg='Maaf, '+error.message;
                if(live&&!done){live.content=(live.content?live.content+'\n\n':'')+msg}else if(!done){this.messages.push({role:'assistant',content:msg})}
            }
            finally{this.loading=false;this.streaming=false;this.scroll();this.$nextTick(()=>this.$refs.input.focus())}
        },
        scroll(){this.$nextTick(()=>{if(this.$refs.messages)this.$refs.messages.scrollTop=this.$refs.messages.scrollHeight})},
        barWidth(value, items){const max=Math.max(...(items||[]).map(i=>Number(i.value)||0),1);return Math.max(3,Math.round((Number(value)||0)/max*100))},
        linePoints(points){const rows=points||[];if(!rows.length)return '';const max=Math.max(...rows.map(p=>Number(p.value)||0),1);return rows.map((p,i)=>`${rows.length===1?50:i/(rows.length-1)*100},${48-(Number(p.value)||0)/max*44}`).join(' ')},
        lineDots(points){const rows=points||[];if(!rows.length)return [];const max=Math.max(...rows.map(p=>Number(p.value)||0),1);return rows.map((p,i)=>({...p,x:rows.length===1?50:i/(rows.length-1)*100,y:48-(Number(p.value)||0)/max*44}))},
        formatAsOf(value){try{return new Intl.DateTimeFormat('id-ID',{dateStyle:'medium',timeStyle:'short'}).format(new Date(value))}catch{return value}},
        async approveAndExecute(block){
            if(this.actionLoading||!confirm(`Terapkan perubahan pada ${block.target}?`))return;this.actionLoading=true;
            try{
                const headers={'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'};
                let response=await fetch(`{{ url('/intelligence/actions') }}/${block.proposal_id}/approve`,{method:'POST',headers,body:JSON.stringify({confirmation:'SETUJUI'})});
                let data=await response.json();if(!response.ok)throw new Error(data.message||'Persetujuan gagal.');
                response=await fetch(`{{ url('/intelligence/actions') }}/${block.proposal_id}/execute`,{method:'POST',headers});data=await response.json();if(!response.ok)throw new Error(data.message||'Eksekusi gagal.');
                block.status='executed';
            }catch(error){alert(error.message)}finally{this.actionLoading=false}
        },
        async toggleAgent(id,event){const response=await fetch(`{{ url('/intelligence/agents') }}/${id}/toggle`,{method:'PATCH',headers:{'Accept':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'}});if(response.ok){const data=await response.json();event.currentTarget.textContent=data.enabled?'Aktif':'Nonaktif';window.location.reload()}},
        async deleteConversation(id){
            if(!confirm('Hapus percakapan ini?'))return;
            const response=await fetch('{{ url('/intelligence/conversations') }}/'+id,{method:'DELETE',headers:{'Accept':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'}});
            if(response.ok){document.getElementById('conversation-'+id)?.remove();if(this.conversationId===id)window.location='{{ route('intelligence.index') }}'}
        }
    }
}
</script>
@endpush
