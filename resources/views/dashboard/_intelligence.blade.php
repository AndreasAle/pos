<section class="xl:col-span-2 space-y-4" x-data="yokaIntelligence()">
    <div class="rounded-2xl border border-violet-200 bg-gradient-to-br from-[#0B1B3F] via-[#172554] to-[#312e81] text-white overflow-hidden shadow-sm">
        <div class="px-5 sm:px-6 py-5 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-white/10 ring-1 ring-white/20 flex items-center justify-center">
                    <svg class="w-6 h-6 text-violet-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l1.7 4.3L18 9l-4.3 1.7L12 15l-1.7-4.3L6 9l4.3-1.7L12 3zM18.5 15l.9 2.1 2.1.9-2.1.9-.9 2.1-.9-2.1-2.1-.9 2.1-.9.9-2.1z"/></svg>
                </div>
                <div>
                    <p class="text-[11px] uppercase tracking-[.2em] font-bold text-violet-200">Yoka Intelligence</p>
                    <h2 class="text-xl font-semibold">Data. Keputusan. Tindakan.</h2>
                    <p class="text-sm text-slate-300 mt-0.5">Insight proaktif dari aktivitas bisnis kamu.</p>
                </div>
            </div>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('intelligence.refresh') }}">@csrf
                    <button class="h-10 px-4 rounded-lg bg-white/10 hover:bg-white/20 text-sm font-semibold ring-1 ring-white/20">Perbarui insight</button>
                </form>
                <button type="button" @click="chatOpen = !chatOpen" class="h-10 px-4 rounded-lg bg-violet-500 hover:bg-violet-400 text-sm font-semibold shadow-lg shadow-violet-950/30">Tanya Yoka</button>
            </div>
        </div>

        @if($insights->isNotEmpty())
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-px bg-white/10 border-t border-white/10">
            @foreach($insights as $insight)
            @php
                $tone = match($insight->severity) { 'critical' => 'text-red-300', 'warning' => 'text-amber-300', 'positive' => 'text-emerald-300', default => 'text-sky-300' };
                $dot = match($insight->severity) { 'critical' => 'bg-red-400', 'warning' => 'bg-amber-400', 'positive' => 'bg-emerald-400', default => 'bg-sky-400' };
            @endphp
            <article id="insight-{{ $insight->id }}" class="relative bg-[#101d43]/95 px-5 py-4 group">
                <button type="button" @click="dismissInsight({{ $insight->id }})" title="Sembunyikan" class="absolute right-3 top-3 p-1 text-slate-500 hover:text-white opacity-0 group-hover:opacity-100">&times;</button>
                <div class="flex items-center gap-2 text-[11px] uppercase tracking-wider font-bold {{ $tone }}"><span class="w-2 h-2 rounded-full {{ $dot }}"></span>{{ str_replace('_', ' ', $insight->type) }}</div>
                <h3 class="mt-2 font-semibold pr-5">{{ $insight->title }}</h3>
                <p class="mt-1 text-sm leading-relaxed text-slate-300">{{ $insight->summary }}</p>
                @if($insight->action_route && Route::has($insight->action_route))
                <a href="{{ route($insight->action_route) }}" @click="readInsight({{ $insight->id }})" class="inline-flex mt-3 text-sm font-semibold text-violet-300 hover:text-white">{{ $insight->action_label }} &rarr;</a>
                @endif
            </article>
            @endforeach
        </div>
        @else
        <div class="px-6 py-5 border-t border-white/10 text-sm text-slate-300">Belum ada anomali penting. Yoka akan terus memantau omzet, stok, margin, outlet, dan aktivitas order.</div>
        @endif
    </div>

    <div x-show="chatOpen" x-cloak x-transition class="rounded-2xl border border-violet-200 bg-white shadow-xl overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200">
            <div><h3 class="font-semibold text-[#0B1B3F]">Tanya Yoka</h3><p class="text-xs text-slate-500">Jawaban berasal dari data bisnis kamu, bukan tebakan.</p></div>
            <button @click="chatOpen=false" class="w-8 h-8 rounded-lg hover:bg-slate-100 text-slate-500">&times;</button>
        </div>
        <div x-ref="messages" class="h-80 overflow-y-auto p-4 sm:p-5 space-y-3 bg-slate-50/70">
            <template x-if="messages.length === 0"><div class="max-w-xl mx-auto text-center py-7"><p class="font-semibold text-[#0B1B3F]">Apa yang ingin kamu ketahui?</p><p class="text-sm text-slate-500 mt-1">Coba pertanyaan cepat di bawah.</p></div></template>
            <template x-for="(message, i) in messages" :key="i"><div class="flex" :class="message.role === 'user' ? 'justify-end' : 'justify-start'"><div class="max-w-[88%] sm:max-w-[75%] rounded-2xl px-4 py-3 text-sm leading-relaxed whitespace-pre-wrap" :class="message.role === 'user' ? 'bg-[#16A34A] text-white rounded-br-md' : 'bg-white border border-slate-200 text-slate-700 rounded-bl-md shadow-sm'" x-text="message.content"></div></div></template>
            <div x-show="loading" class="flex justify-start"><div class="rounded-2xl rounded-bl-md bg-white border border-slate-200 px-4 py-3 text-sm text-slate-500">Yoka sedang membaca data...</div></div>
        </div>
        <div class="px-4 pt-3 flex gap-2 overflow-x-auto">
            <template x-for="prompt in prompts"><button type="button" @click="question=prompt; ask()" class="shrink-0 rounded-full border border-violet-200 bg-violet-50 px-3 py-1.5 text-xs font-semibold text-violet-700 hover:bg-violet-100" x-text="prompt"></button></template>
        </div>
        <form @submit.prevent="ask" class="p-4 flex gap-2">
            <input x-model="question" maxlength="500" autocomplete="off" placeholder="Contoh: produk paling laris bulan ini?" class="min-w-0 flex-1 h-11 rounded-xl border border-slate-300 px-4 text-sm focus:ring-2 focus:ring-violet-400 focus:border-violet-400" />
            <button :disabled="loading || !question.trim()" class="h-11 px-5 rounded-xl bg-[#16A34A] disabled:opacity-50 text-white text-sm font-semibold">Kirim</button>
        </form>
    </div>
</section>

@push('scripts')
<script>
function yokaIntelligence() {
    return {
        chatOpen: false, loading: false, question: '', conversationId: null, messages: [],
        prompts: ['Omzet hari ini?', 'Produk terlaris bulan ini', 'Stok apa yang menipis?', 'Jam paling ramai?', 'Bandingkan outlet'],
        init() { fetch('{{ route('intelligence.history') }}', {headers:{Accept:'application/json'}}).then(r=>r.ok?r.json():null).then(d=>{if(d){this.conversationId=d.conversation_id;this.messages=(d.messages||[]).map(m=>({role:m.role,content:m.content}))}}).catch(()=>{}); },
        async ask() {
            const text=this.question.trim(); if(!text||this.loading)return;
            this.messages.push({role:'user',content:text}); this.question=''; this.loading=true; this.scroll();
            try {
                const res=await fetch('{{ route('intelligence.ask') }}',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},body:JSON.stringify({question:text,conversation_id:this.conversationId})});
                const data=await res.json(); if(!res.ok)throw new Error(data.message||'Gagal membaca data.');
                this.conversationId=data.conversation_id; this.messages.push({role:'assistant',content:data.answer});
            } catch(e) { this.messages.push({role:'assistant',content:'Maaf, '+e.message}); }
            finally { this.loading=false; this.scroll(); }
        },
        scroll(){this.$nextTick(()=>{this.$refs.messages.scrollTop=this.$refs.messages.scrollHeight})},
        async dismissInsight(id){await this.post('{{ url('/intelligence/insights') }}/'+id+'/dismiss');document.getElementById('insight-'+id)?.remove()},
        readInsight(id){this.post('{{ url('/intelligence/insights') }}/'+id+'/read')},
        post(url){return fetch(url,{method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'}})},
    }
}
</script>
@endpush
