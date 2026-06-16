@extends('layouts.app')
@section('title','Audit Log')
@section('page-title','Audit Log Aktivitas')

@section('content')
{{-- Filter --}}
<form method="GET" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 mb-5 flex flex-wrap items-end gap-3">
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1 uppercase">Dari</label>
        <input type="date" name="date_from" value="{{ request('date_from', today()->format('Y-m-d')) }}"
               class="px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1 uppercase">Sampai</label>
        <input type="date" name="date_to" value="{{ request('date_to', today()->format('Y-m-d')) }}"
               class="px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1 uppercase">User</label>
        <select name="user_id" class="px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
            <option value="">Semua User</option>
            @foreach($users as $u)
            <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>
                {{ $u->name }} ({{ $u->role }})
            </option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1 uppercase">Aksi</label>
        <select name="action" class="px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
            <option value="">Semua Aksi</option>
            @foreach($actions as $a)
            <option value="{{ $a }}" {{ request('action') === $a ? 'selected' : '' }}>{{ ucfirst($a) }}</option>
            @endforeach
        </select>
    </div>
    <div class="flex-1 min-w-48">
        <label class="block text-xs font-medium text-gray-500 mb-1 uppercase">Pencarian</label>
        <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari aktivitas..."
                   class="pl-9 w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
        </div>
    </div>
    <button type="submit"
            class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-2.5 rounded-xl text-sm transition-colors">
        Cari
    </button>
    <a href="{{ route('audit.index') }}" class="text-sm text-gray-500 hover:text-gray-700 py-2.5 px-3 rounded-xl hover:bg-gray-100">Reset</a>
</form>

<x-card :padding="false">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-900">{{ $logs->total() }} aktivitas ditemukan</h3>
        <span class="text-xs text-gray-400">Menampilkan 50 per halaman</span>
    </div>
    <div class="divide-y divide-gray-50">
        @forelse($logs as $log)
        @php
        $colors = [
            'created' => ['dot'=>'bg-emerald-500','text'=>'text-emerald-700','bg'=>'bg-emerald-50'],
            'updated' => ['dot'=>'bg-blue-500',   'text'=>'text-blue-700',   'bg'=>'bg-blue-50'],
            'deleted' => ['dot'=>'bg-red-500',    'text'=>'text-red-700',    'bg'=>'bg-red-50'],
            'void'    => ['dot'=>'bg-red-500',    'text'=>'text-red-700',    'bg'=>'bg-red-50'],
            'login'   => ['dot'=>'bg-gray-400',   'text'=>'text-gray-600',   'bg'=>'bg-gray-50'],
            'logout'  => ['dot'=>'bg-gray-400',   'text'=>'text-gray-600',   'bg'=>'bg-gray-50'],
            'shift_open'  => ['dot'=>'bg-emerald-500','text'=>'text-emerald-700','bg'=>'bg-emerald-50'],
            'shift_close' => ['dot'=>'bg-orange-500', 'text'=>'text-orange-700', 'bg'=>'bg-orange-50'],
            'stock_in'    => ['dot'=>'bg-emerald-500','text'=>'text-emerald-700','bg'=>'bg-emerald-50'],
            'stock_adjust'=> ['dot'=>'bg-yellow-500', 'text'=>'text-yellow-700', 'bg'=>'bg-yellow-50'],
            'order_paid'  => ['dot'=>'bg-emerald-500','text'=>'text-emerald-700','bg'=>'bg-emerald-50'],
        ];
        $c = $colors[$log->action] ?? ['dot'=>'bg-gray-400','text'=>'text-gray-600','bg'=>'bg-gray-50'];
        @endphp
        <div class="flex items-start gap-4 px-5 py-3.5 hover:bg-gray-50/50 transition-colors" x-data="{open:false}">
            {{-- Timeline dot --}}
            <div class="flex-shrink-0 mt-1">
                <span class="w-2.5 h-2.5 rounded-full {{ $c['dot'] }} block mt-0.5"></span>
            </div>

            <div class="flex-1 min-w-0">
                <div class="flex items-center flex-wrap gap-2 mb-0.5">
                    {{-- Action badge --}}
                    <span class="inline-flex items-center text-xs font-semibold px-2 py-0.5 rounded-full {{ $c['text'] }} {{ $c['bg'] }}">
                        {{ ucfirst($log->action) }}
                    </span>
                    {{-- Description --}}
                    <p class="text-sm text-gray-900">{{ $log->description }}</p>
                    @if($log->subject_label)
                    <span class="text-xs text-gray-400">— {{ $log->subject_label }}</span>
                    @endif
                </div>
                <div class="flex items-center gap-3 text-xs text-gray-400">
                    <span class="flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        {{ $log->user_name ?? 'System' }}
                    </span>
                    <span class="flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ $log->created_at->format('d/m/Y H:i:s') }}
                        ({{ $log->created_at->diffForHumans() }})
                    </span>
                    @if($log->ip_address)
                    <span>IP: {{ $log->ip_address }}</span>
                    @endif
                    @if($log->old_values || $log->new_values)
                    <button @click="open=!open" class="text-emerald-600 hover:underline">
                        <span x-show="!open">Lihat perubahan ▼</span>
                        <span x-show="open">Sembunyikan ▲</span>
                    </button>
                    @endif
                </div>

                {{-- Change detail (collapsible) --}}
                @if($log->old_values || $log->new_values)
                <div x-show="open" x-cloak class="mt-2 bg-gray-50 rounded-xl border border-gray-200 p-3 text-xs font-mono">
                    @if($log->old_values && $log->new_values)
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <p class="font-semibold text-red-600 mb-1 font-sans">Sebelum</p>
                            @foreach($log->old_values as $key => $val)
                            <div class="flex gap-2 py-0.5">
                                <span class="text-gray-500 min-w-24">{{ $key }}:</span>
                                <span class="text-red-700">{{ is_array($val) ? json_encode($val) : $val }}</span>
                            </div>
                            @endforeach
                        </div>
                        <div>
                            <p class="font-semibold text-emerald-600 mb-1 font-sans">Sesudah</p>
                            @foreach($log->new_values as $key => $val)
                            <div class="flex gap-2 py-0.5">
                                <span class="text-gray-500 min-w-24">{{ $key }}:</span>
                                <span class="text-emerald-700">{{ is_array($val) ? json_encode($val) : $val }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @elseif($log->new_values)
                    <p class="font-semibold text-emerald-600 mb-1 font-sans">Data dibuat</p>
                    @foreach($log->new_values as $key => $val)
                    <div class="flex gap-2 py-0.5">
                        <span class="text-gray-500 min-w-24">{{ $key }}:</span>
                        <span class="text-gray-700">{{ is_array($val) ? json_encode($val) : $val }}</span>
                    </div>
                    @endforeach
                    @elseif($log->old_values)
                    <p class="font-semibold text-red-600 mb-1 font-sans">Data dihapus</p>
                    @foreach($log->old_values as $key => $val)
                    <div class="flex gap-2 py-0.5">
                        <span class="text-gray-500 min-w-24">{{ $key }}:</span>
                        <span class="text-red-700 line-through">{{ is_array($val) ? json_encode($val) : $val }}</span>
                    </div>
                    @endforeach
                    @endif
                </div>
                @endif
            </div>
        </div>
        @empty
        <div class="px-5 py-16 text-center text-sm text-gray-400">
            <svg class="w-12 h-12 mx-auto mb-3 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p>Tidak ada aktivitas ditemukan</p>
        </div>
        @endforelse
    </div>
</x-card>
<div class="mt-4">{{ $logs->links() }}</div>
@endsection
