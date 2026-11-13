@extends('layouts.app')
@section('title','Audit Log')
@section('page-title','Audit Log Aktivitas')

@section('content')
@php
    $tones = [
        'created' => 'green', 'updated' => 'blue', 'deleted' => 'red', 'void' => 'red',
        'login' => 'gray', 'logout' => 'gray', 'shift_open' => 'green', 'shift_close' => 'amber',
        'stock_in' => 'green', 'stock_adjust' => 'amber', 'order_paid' => 'green',
    ];
    $dots = ['green' => 'bg-[#16A34A]', 'blue' => 'bg-sky-500', 'red' => 'bg-red-500', 'amber' => 'bg-amber-500', 'gray' => 'bg-slate-400'];
    $hasFilter = request()->hasAny(['date_from', 'date_to', 'user_id', 'action', 'search']) && (request('date_from') || request('date_to') || request('user_id') || request('action') || request('search'));
    $label = 'mb-1.5 block text-xs font-semibold uppercase tracking-wider text-[#64748B]';
@endphp

<div class="page text-[#0B1B3F] pt-2">

    <div class="fade-up flex flex-wrap items-end justify-between gap-2 mb-4" style="--i:0">
        <p class="text-sm text-[#64748B]">Jejak aktivitas staf: siapa melakukan apa, dan kapan.</p>
        <span class="inline-flex items-center gap-1.5 h-8 px-3 rounded-full bg-[#ECFDF3] text-[#15803D] text-sm font-semibold">
            <span class="w-1.5 h-1.5 rounded-full bg-[#16A34A]"></span>{{ number_format($logs->total()) }} aktivitas
        </span>
    </div>

    {{-- Filter --}}
    <form method="GET" class="fade-up mb-4 rounded-xl border border-[#E5E7EB] bg-white p-3 sm:p-4" style="--i:1">
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div>
                <label class="{{ $label }}" for="date_from">Dari</label>
                <input type="date" id="date_from" name="date_from" value="{{ request('date_from') }}"
                       class="h-10 w-full rounded-lg border border-[#E5E7EB] bg-white px-3 text-sm focus:outline-none focus:border-[#16A34A] focus:ring-2 focus:ring-[#16A34A]/30 transition">
            </div>
            <div>
                <label class="{{ $label }}" for="date_to">Sampai</label>
                <input type="date" id="date_to" name="date_to" value="{{ request('date_to') }}"
                       class="h-10 w-full rounded-lg border border-[#E5E7EB] bg-white px-3 text-sm focus:outline-none focus:border-[#16A34A] focus:ring-2 focus:ring-[#16A34A]/30 transition">
            </div>
            <div>
                <label class="{{ $label }}" for="user_id">User</label>
                <x-select name="user_id" :compact="true">
                    <option value="">Semua user</option>
                    @foreach($users as $u)
                    <option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->name }} ({{ $u->role }})</option>
                    @endforeach
                </x-select>
            </div>
            <div>
                <label class="{{ $label }}" for="action">Aksi</label>
                <x-select name="action" :compact="true">
                    <option value="">Semua aksi</option>
                    @foreach($actions as $a)
                    <option value="{{ $a }}" @selected(request('action') === $a)>{{ ucfirst(str_replace('_', ' ', $a)) }}</option>
                    @endforeach
                </x-select>
            </div>
        </div>
        <div class="mt-3 flex flex-col sm:flex-row gap-2">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-[#64748B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari aktivitas..."
                       class="h-10 w-full rounded-lg border border-[#E5E7EB] bg-white pl-10 pr-3 text-sm placeholder:text-slate-400 focus:outline-none focus:border-[#16A34A] focus:ring-2 focus:ring-[#16A34A]/30 transition">
            </div>
            <div class="flex gap-2">
                <x-btn type="submit" variant="dark" class="flex-1 sm:flex-none">Cari</x-btn>
                @if($hasFilter)<x-btn :href="route('audit.index')" variant="ghost">Reset</x-btn>@endif
            </div>
        </div>
    </form>

    {{-- Timeline --}}
    <section class="fade-up rounded-xl border border-[#E5E7EB] bg-white overflow-hidden" style="--i:2">
        <div class="flex flex-wrap items-center justify-between gap-1 border-b border-[#E5E7EB] px-5 py-4">
            <h2 class="text-base font-semibold">Riwayat aktivitas</h2>
            <span class="text-xs text-[#64748B]">50 per halaman</span>
        </div>

        @forelse($logs as $log)
        @php $tone = $tones[$log->action] ?? 'gray'; $hasChange = $log->old_values || $log->new_values; @endphp
        <div class="flex items-start gap-4 border-b border-[#E5E7EB] px-5 py-4 last:border-0 hover:bg-[#ECFDF3]/40 transition-colors" x-data="{ open: false }">
            <span class="mt-2 h-2.5 w-2.5 shrink-0 rounded-full {{ $dots[$tone] }}"></span>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <x-pill :tone="$tone">{{ ucfirst(str_replace('_', ' ', $log->action)) }}</x-pill>
                    <p class="text-sm font-medium">{{ $log->description }}</p>
                    @if($log->subject_label)<span class="text-xs text-[#64748B]">&mdash; {{ $log->subject_label }}</span>@endif
                </div>

                <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-[#64748B]">
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        {{ $log->user_name ?? 'System' }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ $log->created_at->format('d/m/Y H:i:s') }} ({{ $log->created_at->diffForHumans() }})
                    </span>
                    @if($log->ip_address)<span class="font-mono">IP {{ $log->ip_address }}</span>@endif
                    @if($hasChange)
                    <button type="button" @click="open = !open" class="inline-flex items-center gap-1 font-semibold text-[#16A34A] hover:underline">
                        <span x-text="open ? 'Sembunyikan perubahan' : 'Lihat perubahan'"></span>
                        <svg class="h-3.5 w-3.5 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    @endif
                </div>

                @if($hasChange)
                <div x-show="open" x-cloak class="mt-3 rounded-lg border border-[#E5E7EB] bg-slate-50 p-3.5 font-mono text-xs">
                    @if($log->old_values && $log->new_values)
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <p class="mb-1.5 font-sans text-[11px] font-semibold uppercase tracking-wider text-red-600">Sebelum</p>
                            @foreach($log->old_values as $key => $val)
                            <div class="flex gap-2 py-0.5"><span class="min-w-24 text-[#64748B]">{{ $key }}:</span><span class="break-all text-red-700">{{ is_array($val) ? json_encode($val) : $val }}</span></div>
                            @endforeach
                        </div>
                        <div>
                            <p class="mb-1.5 font-sans text-[11px] font-semibold uppercase tracking-wider text-[#15803D]">Sesudah</p>
                            @foreach($log->new_values as $key => $val)
                            <div class="flex gap-2 py-0.5"><span class="min-w-24 text-[#64748B]">{{ $key }}:</span><span class="break-all text-[#15803D]">{{ is_array($val) ? json_encode($val) : $val }}</span></div>
                            @endforeach
                        </div>
                    </div>
                    @elseif($log->new_values)
                    <p class="mb-1.5 font-sans text-[11px] font-semibold uppercase tracking-wider text-[#15803D]">Data dibuat</p>
                    @foreach($log->new_values as $key => $val)
                    <div class="flex gap-2 py-0.5"><span class="min-w-24 text-[#64748B]">{{ $key }}:</span><span class="break-all text-slate-700">{{ is_array($val) ? json_encode($val) : $val }}</span></div>
                    @endforeach
                    @elseif($log->old_values)
                    <p class="mb-1.5 font-sans text-[11px] font-semibold uppercase tracking-wider text-red-600">Data dihapus</p>
                    @foreach($log->old_values as $key => $val)
                    <div class="flex gap-2 py-0.5"><span class="min-w-24 text-[#64748B]">{{ $key }}:</span><span class="break-all text-red-700 line-through">{{ is_array($val) ? json_encode($val) : $val }}</span></div>
                    @endforeach
                    @endif
                </div>
                @endif
            </div>
        </div>
        @empty
        <x-empty title="Tidak ada aktivitas" :text="$hasFilter ? 'Coba ubah filter pencarian.' : 'Aktivitas staf akan tercatat di sini.'" icon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        @endforelse
    </section>

    <div class="mt-5">{{ $logs->links() }}</div>
</div>
@endsection
