@extends('layouts.app')
@section('title', 'Tanya Yoka')
@section('page-title', 'Tanya Yoka')

@section('content')
@php
    $hasTrialBanner = $currentBusiness?->activeSubscription?->status === 'trial';
    $initialMessages = $activeConversation?->messages?->map(fn($m) => [
        'role' => $m->role, 'content' => $m->content,
        'hasData' => filled($m->tool_name),
        'response' => $m->response,
    ])->values() ?? collect();
@endphp

<div class="-mx-4 sm:-mx-6 -mb-8 bg-white border-t border-[#E5E7EB] {{ $hasTrialBanner ? 'h-[calc(100vh-98px)]' : 'h-[calc(100vh-54px)]' }} min-h-[620px] overflow-hidden"
     x-data="yokaChat(@js($activeConversation?->id), @js($initialMessages), @js($reportPrompt ?? null))">
    <div class="flex h-full min-w-0">
        {{-- Riwayat percakapan internal --}}
        <aside class="absolute lg:relative z-20 inset-y-0 left-0 w-[286px] shrink-0 border-r border-[#E5E7EB] bg-[#F8FAF9] flex flex-col transition-transform lg:translate-x-0"
               :class="historyOpen ? 'translate-x-0 shadow-2xl lg:shadow-none' : '-translate-x-full'">
            <div class="p-3 border-b border-[#E5E7EB]">
                <a href="{{ route('intelligence.index') }}" class="h-11 px-3 rounded-xl bg-[#0B8F5B] hover:bg-[#087A4E] text-white flex items-center justify-center gap-2 text-sm font-semibold shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                    Percakapan baru
                </a>
            </div>

            <div class="flex-1 min-h-0 overflow-y-auto px-2 py-3">
                <p class="px-2 mb-2 text-[11px] uppercase tracking-wider font-bold text-slate-400">Riwayat</p>
                @forelse($conversations as $conversation)
                <div id="conversation-{{ $conversation->id }}" class="group relative mb-1">
                    <a href="{{ route('intelligence.index', ['conversation' => $conversation->id]) }}"
                       class="block rounded-lg py-2.5 pl-3 pr-9 text-[13px] leading-5 {{ $activeConversation?->id === $conversation->id ? 'bg-[#E1F5EA] text-[#087A4E] font-semibold' : 'text-slate-700 hover:bg-slate-100' }}">
                        <span class="block truncate">{{ $conversation->title ?: 'Percakapan baru' }}</span>
                        <span class="block text-[11px] font-normal text-slate-400">{{ $conversation->updated_at->diffForHumans() }}</span>
                    </a>
                    <button type="button" @click.prevent="deleteConversation({{ $conversation->id }})" title="Hapus percakapan"
                            class="absolute right-2 top-3 w-7 h-7 rounded-md text-slate-400 hover:bg-red-50 hover:text-red-600 opacity-0 group-hover:opacity-100 focus:opacity-100">&times;</button>
                </div>
                @empty
                <p class="px-3 py-5 text-xs leading-relaxed text-slate-400">Belum ada percakapan. Mulai dengan pertanyaan tentang penjualan atau operasional bisnis.</p>
                @endforelse
            </div>

            <div class="border-t border-[#E5E7EB] p-3">
                <div class="rounded-xl border border-[#D8EDE2] bg-white p-3">
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-lg bg-[#E1F5EA] text-[#0B8F5B] flex items-center justify-center">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l1.5 4.5L18 9l-4.5 1.5L12 15l-1.5-4.5L6 9l4.5-1.5L12 3z"/></svg>
                        </span>
                        <div><p class="text-xs font-bold text-[#0B1B3F]">Insight aktif</p><p class="text-[11px] text-slate-500">{{ $businessInsights->count() }} perhatian bisnis</p></div>
                    </div>
                    @if($businessInsights->isNotEmpty())
                    <div class="mt-3 space-y-2">
                        @foreach($businessInsights->take(3) as $insight)
                        <div class="rounded-lg bg-slate-50 px-2.5 py-2">
                            <p class="text-[11px] font-semibold text-slate-700 line-clamp-2">{{ $insight->title }}</p>
                            <p class="mt-1 text-[9px] text-slate-400">{{ $insight->confidence ?? 0 }}% confidence · {{ $insight->sample_size ?? 0 }} sampel</p>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <p class="mt-2 text-[11px] leading-relaxed text-slate-500">Tidak ada anomali penting saat ini.</p>
                    @endif
                </div>
                @if(config('pos.features.intelligence_agents') && $intelligenceAgents->isNotEmpty())
                <details class="mt-2 rounded-xl border border-[#E5E7EB] bg-white p-3">
                    <summary class="cursor-pointer text-xs font-bold text-[#0B1B3F]">Agen terjadwal</summary>
                    <div class="mt-3 space-y-2">
                        @foreach($intelligenceAgents as $agent)
                        <div class="flex items-center justify-between gap-2 text-[11px]"><span>{{ str($agent->agent_key)->replace('_', ' ')->title() }}</span><button type="button" @click="toggleAgent({{ $agent->id }}, $event)" class="rounded-full px-2 py-1 font-semibold {{ $agent->enabled ? 'bg-[#E1F5EA] text-[#087A4E]' : 'bg-slate-100 text-slate-500' }}">{{ $agent->enabled ? 'Aktif' : 'Nonaktif' }}</button></div>
                        @endforeach
                    </div>
                </details>
                @endif
            </div>
        </aside>

        <div x-show="historyOpen" x-cloak @click="historyOpen=false" class="absolute inset-0 z-10 bg-black/30 lg:hidden"></div>

        {{-- Chat utama --}}
        <section class="relative flex-1 min-w-0 flex flex-col bg-white">
            <button @click="historyOpen=true" class="absolute left-3 top-3 z-10 flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white/95 shadow-sm hover:bg-slate-50 lg:hidden" aria-label="Buka riwayat">
                <svg class="h-5 w-5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>

            <div x-ref="messages" class="flex-1 min-h-0 overflow-y-auto scroll-smooth">
                <div class="mx-auto w-full max-w-4xl px-4 sm:px-6 py-8 sm:py-12">
                    <div x-show="messages.length === 0" class="min-h-[390px] flex flex-col items-center justify-center text-center">
                        <div class="w-14 h-14 rounded-2xl bg-[#E1F5EA] text-[#0B8F5B] flex items-center justify-center ring-8 ring-[#F4FBF7]">
                            <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l1.5 4.5L18 9l-4.5 1.5L12 15l-1.5-4.5L6 9l4.5-1.5L12 3zM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8L19 15z"/></svg>
                        </div>
                        <h2 class="mt-6 text-2xl sm:text-3xl font-bold tracking-tight text-[#0B1B3F]">Apa yang ingin kamu ketahui?</h2>
                        <p class="mt-2 max-w-md text-sm leading-relaxed text-slate-500">Tanyakan omzet, produk, stok, pelanggan, outlet, pembayaran, atau shift menggunakan bahasa sehari-hari.</p>
                        <div class="mt-7 grid grid-cols-1 sm:grid-cols-2 gap-2.5 w-full max-w-xl text-left">
                            <template x-for="prompt in prompts" :key="prompt.text">
                                <button type="button" @click="question=prompt.text; ask()" class="group rounded-xl border border-[#E5E7EB] bg-white p-3.5 hover:border-[#9DD8BA] hover:bg-[#F4FBF7] transition">
                                    <div class="flex items-start gap-3"><span class="w-8 h-8 rounded-lg bg-slate-100 group-hover:bg-[#E1F5EA] text-[#0B8F5B] flex items-center justify-center text-sm" x-text="prompt.icon"></span><div><p class="text-sm font-semibold text-[#0B1B3F]" x-text="prompt.title"></p><p class="mt-0.5 text-xs text-slate-500" x-text="prompt.text"></p></div></div>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div x-show="messages.length > 0" class="space-y-7">
                        <template x-for="(message, i) in messages" :key="i">
                            <div class="flex items-start gap-3 sm:gap-4" :class="message.role === 'user' ? 'justify-end' : ''">
                                <div x-show="message.role !== 'user'" class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-[#CFE7D9] bg-white shadow-sm"><img src="{{ asset('images/brand/yoka-icon.png') }}" alt="Yoka POS" class="h-6 w-6 object-contain"></div>
                                <div class="max-w-[92%] sm:max-w-[86%] min-w-0" :class="message.role !== 'user' && message.response?.blocks?.length ? 'w-full' : ''">
                                    <div class="text-sm sm:text-[15px] leading-7 whitespace-pre-wrap" :class="message.role === 'user' ? 'rounded-2xl rounded-br-md bg-[#E8F7EF] px-4 py-2.5 text-[#0B1B3F]' : 'text-slate-700'" x-text="message.content"></div>
                                    <div x-show="message.response?.blocks?.length" class="mt-4 space-y-3">
                                        <template x-for="(block, blockIndex) in (message.response?.blocks || [])" :key="blockIndex">
                                            <section class="overflow-hidden rounded-2xl border border-[#DDE9E2] bg-white shadow-sm" :aria-label="block.title">
                                                <div class="flex items-center justify-between border-b border-[#E5E7EB] bg-[#F8FBF9] px-4 py-3"><p class="text-xs font-bold text-[#0B1B3F]" x-text="block.title"></p><span class="rounded-full bg-[#E1F5EA] px-2 py-1 text-[9px] font-bold uppercase tracking-wider text-[#087A4E]">Data aktual</span></div>
                                                <div x-show="block.type === 'kpi'" class="grid grid-cols-2 gap-px bg-[#E5E7EB] sm:grid-cols-4"><template x-for="metric in (block.metrics || [])" :key="metric.label"><div class="bg-white px-4 py-3"><p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400" x-text="metric.label"></p><p class="mt-1 text-sm font-extrabold text-[#0B1B3F]" x-text="metric.formatted"></p></div></template></div>
                                                <div x-show="block.type === 'bar_chart'" class="p-4"><p class="mb-4 text-[10px] text-slate-400"><span x-text="block.y_label"></span> · <span x-text="block.x_label"></span></p><div class="space-y-3"><template x-for="item in (block.items || [])" :key="item.label"><div class="grid grid-cols-[90px_minmax(0,1fr)_auto] items-center gap-3 text-[11px]"><span class="truncate font-semibold text-slate-600" x-text="item.label" :title="item.label"></span><div class="h-2.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-gradient-to-r from-[#0B8F5B] to-[#34C67F]" :style="`width:${barWidth(item.value, block.items)}%`"></div></div><span class="min-w-[76px] text-right font-bold text-[#0B1B3F]" x-text="item.formatted"></span></div></template></div></div>
                                                <div x-show="block.type === 'line_chart'" class="p-4"><p class="mb-2 text-[10px] text-slate-400"><span x-text="block.x_label"></span> · <span x-text="block.y_label"></span></p><div x-show="block.points?.length" class="relative h-44 rounded-xl bg-gradient-to-b from-[#F4FBF7] to-white p-3"><svg viewBox="0 0 100 50" preserveAspectRatio="none" class="h-32 w-full overflow-visible" role="img" :aria-label="block.title"><polyline :points="linePoints(block.points)" fill="none" stroke="#0B8F5B" stroke-width="1.6" vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round"></polyline><template x-for="(point,index) in lineDots(block.points)" :key="`${point.date}-${index}`"><circle :cx="point.x" :cy="point.y" r="1.8" fill="#0B8F5B" stroke="white" stroke-width="1" vector-effect="non-scaling-stroke"><title x-text="`${point.date}: ${point.formatted || point.value}`"></title></circle></template></svg><div class="mt-1 flex justify-between text-[9px] text-slate-400"><span x-text="block.points?.[0]?.date"></span><span x-text="block.points?.[block.points.length-1]?.date"></span></div></div></div>
                                                <div x-show="block.type === 'table'" class="overflow-x-auto"><table class="w-full min-w-[500px] text-left text-xs"><thead class="bg-slate-50 text-[10px] uppercase tracking-wide text-slate-400"><tr><template x-for="column in (block.columns || [])" :key="column.key"><th class="px-4 py-2.5 font-bold" x-text="column.label"></th></template></tr></thead><tbody class="divide-y divide-slate-100"><template x-for="(row, rowIndex) in (block.rows || [])" :key="rowIndex"><tr><template x-for="(column, cellIndex) in (block.columns || [])" :key="column.key"><td class="px-4 py-3 text-slate-600" :class="cellIndex === 0 ? 'font-semibold text-[#0B1B3F]' : ''" x-text="row[column.key] ?? '—'"></td></template></tr></template></tbody></table></div>
                                                <div x-show="block.type === 'action_preview'" class="p-4"><p class="text-sm font-bold text-[#0B1B3F]" x-text="block.target"></p><div class="mt-3 space-y-2"><template x-for="change in (block.changes || [])"><div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-xs"><span x-text="change.field"></span><span><span class="text-slate-400 line-through" x-text="change.before"></span><span class="mx-1">→</span><strong x-text="change.after"></strong></span></div></template></div><p class="mt-3 text-[10px] text-amber-700">Perubahan belum diterapkan. Persetujuan akan divalidasi ulang sebelum eksekusi.</p><button x-show="block.status === 'pending'" type="button" @click="approveAndExecute(block)" :disabled="actionLoading" class="mt-3 rounded-lg bg-[#0B8F5B] px-4 py-2 text-xs font-bold text-white disabled:opacity-50">Setujui & jalankan</button><p x-show="block.status === 'executed'" class="mt-3 text-xs font-bold text-[#087A4E]">Perubahan berhasil diterapkan dan tercatat di audit.</p></div>
                                            </section>
                                        </template>
                                    </div>
                                    <div x-show="message.response?.uncertainty?.length" class="mt-2 rounded-xl bg-amber-50 px-3 py-2 text-[11px] leading-5 text-amber-800"><template x-for="note in (message.response?.uncertainty || [])"><p x-text="note"></p></template></div>
                                    <div x-show="message.role !== 'user' && message.hasData" class="mt-2 flex flex-wrap items-center gap-2 text-[10px] text-slate-400"><span>Dihitung dari data bisnis kamu</span><span x-show="message.response?.as_of">·</span><span x-show="message.response?.as_of" x-text="`Per ${formatAsOf(message.response.as_of)}`"></span><span x-show="message.response?.timezone" class="rounded bg-slate-100 px-1.5 py-0.5" x-text="message.response.timezone"></span></div>
                                    <div x-show="message.response?.suggestions?.length" class="mt-3 flex flex-wrap gap-2"><template x-for="suggestion in (message.response?.suggestions || [])" :key="suggestion"><button type="button" @click="question=suggestion; ask()" class="rounded-full border border-[#CFE7D9] px-3 py-1.5 text-[11px] font-semibold text-[#087A4E] hover:bg-[#F0FAF4]" x-text="suggestion"></button></template></div>
                                </div>
                            </div>
                        </template>
                        <div x-show="loading" class="flex items-start gap-4">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-[#CFE7D9] bg-white shadow-sm"><img src="{{ asset('images/brand/yoka-icon.png') }}" alt="Yoka POS" class="h-6 w-6 object-contain"></div>
                            <div class="pt-2 flex gap-1"><span class="w-2 h-2 rounded-full bg-[#16A34A] animate-bounce"></span><span class="w-2 h-2 rounded-full bg-[#16A34A] animate-bounce [animation-delay:120ms]"></span><span class="w-2 h-2 rounded-full bg-[#16A34A] animate-bounce [animation-delay:240ms]"></span></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Composer --}}
            <div class="shrink-0 bg-gradient-to-t from-white via-white to-white/70 px-3 sm:px-6 pb-4 pt-2">
                <form @submit.prevent="ask" class="mx-auto max-w-4xl rounded-2xl border border-slate-300 bg-white p-2 shadow-[0_8px_30px_rgba(15,23,42,.08)] focus-within:border-[#65B98E] focus-within:ring-4 focus-within:ring-[#E1F5EA] transition">
                    <div class="flex items-end gap-2">
                        <textarea x-model="question" x-ref="input" @keydown.enter="if(!$event.shiftKey){$event.preventDefault();ask()}" rows="1" maxlength="500" placeholder="Tanya tentang bisnis kamu..." class="max-h-32 min-h-11 flex-1 resize-none border-0 bg-transparent px-3 py-3 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-0"></textarea>
                        <button type="submit" :disabled="loading || !question.trim()" class="w-10 h-10 mb-0.5 rounded-xl bg-[#0B8F5B] hover:bg-[#087A4E] disabled:bg-slate-200 disabled:text-slate-400 text-white flex items-center justify-center transition" aria-label="Kirim">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m-6 6l6-6 6 6"/></svg>
                        </button>
                    </div>
                </form>
                <p class="mt-2 text-center text-[10px] text-slate-400">Yoka dapat melakukan kesalahan. Cocokkan keputusan penting dengan laporan utama.</p>
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
function yokaChat(initialConversation, initialMessages, initialQuestion) {
    return {
        historyOpen: false, loading: false, actionLoading: false, question: initialQuestion || '', conversationId: initialConversation, messages: initialMessages || [],
        prompts: [
            {icon:'↗',title:'Ringkasan penjualan',text:'Bagaimana omzet hari ini?'},
            {icon:'★',title:'Produk unggulan',text:'Produk paling laris bulan ini'},
            {icon:'!',title:'Kondisi stok',text:'Stok apa yang perlu segera dibeli?'},
            {icon:'◷',title:'Waktu operasional',text:'Jam berapa paling ramai bulan ini?'},
        ],
        init(){this.scroll()},
        async ask(){
            const text=this.question.trim(); if(!text||this.loading)return;
            this.messages.push({role:'user',content:text}); this.question=''; this.loading=true; this.scroll();
            try{
                const response=await fetch('{{ route('intelligence.ask') }}',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},body:JSON.stringify({question:text,conversation_id:this.conversationId})});
                const data=await response.json(); if(!response.ok)throw new Error(data.message||'Yoka gagal membaca data.');
                const isNew=!this.conversationId; this.conversationId=data.conversation_id; this.messages.push({role:'assistant',content:data.answer,hasData:!!data.tool,response:data.response});
                if(isNew) history.replaceState({},'', '{{ route('intelligence.index') }}?conversation='+data.conversation_id);
            }catch(error){this.messages.push({role:'assistant',content:'Maaf, '+error.message});}
            finally{this.loading=false;this.scroll();this.$nextTick(()=>this.$refs.input.focus())}
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
