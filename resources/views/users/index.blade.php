@extends('layouts.app')
@section('title', 'Manajemen User')
@section('page-title', 'Manajemen User')

@section('content')
@php
    $trash = 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16';
    $roleTone  = ['owner' => 'green', 'admin' => 'blue', 'cashier' => 'gray', 'kitchen' => 'amber', 'warehouse' => 'gray'];
    $roleLabel = ['owner' => 'Owner', 'admin' => 'Admin', 'cashier' => 'Kasir', 'kitchen' => 'Dapur', 'warehouse' => 'Gudang'];
@endphp

<div class="page text-[#0B1B3F] pt-2">

    <div class="fade-up flex flex-wrap items-end justify-between gap-3 mb-4" style="--i:0">
        <div class="flex flex-wrap items-center gap-3">
            <p class="text-sm text-[#64748B]">Atur akun staf dan hak akses mereka.</p>
            <span class="inline-flex items-center gap-1.5 h-8 px-3 rounded-full bg-[#ECFDF3] text-[#15803D] text-sm font-semibold">
                <span class="w-1.5 h-1.5 rounded-full bg-[#16A34A]"></span>{{ number_format($users->total()) }} user
            </span>
        </div>
        <x-btn :href="route('users.create')">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Tambah User
        </x-btn>
    </div>

    <section class="fade-up rounded-xl border border-[#E5E7EB] bg-white overflow-hidden" style="--i:1">
        @if($users->count())
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap">
                <thead>
                    <tr class="bg-slate-50 border-b border-[#E5E7EB]">
                        <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">User</th>
                        <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Email</th>
                        <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Role</th>
                        <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Outlet</th>
                        <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E5E7EB]">
                    @foreach($users as $user)
                    <tr class="hover:bg-[#ECFDF3]/50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-[#ECFDF3] text-sm font-bold text-[#16A34A]">{{ strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                                <div class="min-w-0">
                                    <p class="font-semibold">{{ $user->name }}@if($user->id === auth()->id()) <span class="ml-1 text-xs font-medium text-[#64748B]">(Anda)</span>@endif</p>
                                    @if($user->phone)<p class="text-xs text-[#64748B]">{{ $user->phone }}</p>@endif
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-slate-700">{{ $user->email }}</td>
                        <td class="px-6 py-4"><x-pill :tone="$roleTone[$user->role] ?? 'gray'">{{ $roleLabel[$user->role] ?? ucfirst($user->role) }}</x-pill></td>
                        <td class="px-6 py-4 text-slate-700">{{ $user->outlet?->name ?? 'Semua outlet' }}</td>
                        <td class="px-6 py-4"><x-pill :tone="$user->is_active ? 'green' : 'red'">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</x-pill></td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-end gap-1.5">
                                @if($user->id !== auth()->id())
                                <form method="POST" action="{{ route('users.toggle', $user) }}">
                                    @csrf @method('PATCH')
                                    <x-btn type="submit" size="sm" :variant="$user->is_active ? 'secondary' : 'soft'">{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</x-btn>
                                </form>
                                @endif
                                <x-btn :href="route('users.edit', $user)" size="sm" variant="secondary">Edit</x-btn>
                                @if($user->id !== auth()->id())
                                <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('Hapus user ini?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="flex h-8 w-8 items-center justify-center rounded-lg text-[#64748B] transition-colors hover:bg-red-50 hover:text-red-600" title="Hapus" aria-label="Hapus user">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $trash }}"/></svg>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <ul class="md:hidden divide-y divide-[#E5E7EB]">
            @foreach($users as $user)
            <li class="p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#ECFDF3] text-sm font-bold text-[#16A34A]">{{ strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ $user->name }}</p>
                            <p class="truncate text-xs text-[#64748B]">{{ $user->email }}</p>
                        </div>
                    </div>
                    <x-pill :tone="$user->is_active ? 'green' : 'red'">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</x-pill>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-[#64748B]">
                    <x-pill :tone="$roleTone[$user->role] ?? 'gray'">{{ $roleLabel[$user->role] ?? ucfirst($user->role) }}</x-pill>
                    <span>{{ $user->outlet?->name ?? 'Semua outlet' }}</span>
                </div>
                <div class="mt-4 flex items-center gap-2">
                    @if($user->id !== auth()->id())
                    <form method="POST" action="{{ route('users.toggle', $user) }}">
                        @csrf @method('PATCH')
                        <x-btn type="submit" size="sm" :variant="$user->is_active ? 'secondary' : 'soft'">{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</x-btn>
                    </form>
                    @endif
                    <x-btn :href="route('users.edit', $user)" size="sm" variant="secondary">Edit</x-btn>
                    @if($user->id !== auth()->id())
                    <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('Hapus user ini?')">
                        @csrf @method('DELETE')
                        <x-btn type="submit" size="sm" variant="danger-outline">Hapus</x-btn>
                    </form>
                    @endif
                </div>
            </li>
            @endforeach
        </ul>
        @else
        <x-empty title="Belum ada user" text="Tambahkan staf agar bisa masuk dan bekerja di aplikasi." icon="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
            <x-btn :href="route('users.create')">Tambah user</x-btn>
        </x-empty>
        @endif
    </section>

    <div class="mt-5">{{ $users->links() }}</div>
</div>
@endsection
