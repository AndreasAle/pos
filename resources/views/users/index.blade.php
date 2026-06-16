@extends('layouts.app')
@section('title', 'Manajemen User')
@section('page-title', 'Manajemen User')

@section('content')
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-gray-500">{{ $users->total() }} user terdaftar</p>
    <a href="{{ route('users.create') }}"
       class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Tambah User
    </a>
</div>

<x-card :padding="false">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-50 border-b border-gray-100">
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Nama</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Email</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Role</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Outlet</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Status</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($users as $user)
            <tr class="hover:bg-gray-50/50">
                <td class="px-5 py-3.5">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
                            <span class="text-xs font-bold text-emerald-700">{{ substr($user->name, 0, 1) }}</span>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">{{ $user->name }}</p>
                            @if($user->phone)
                            <p class="text-xs text-gray-400">{{ $user->phone }}</p>
                            @endif
                        </div>
                    </div>
                </td>
                <td class="px-5 py-3.5 text-gray-600">{{ $user->email }}</td>
                <td class="px-5 py-3.5">
                    @php
                    $roleColors = ['owner'=>'purple','admin'=>'blue','cashier'=>'emerald','kitchen'=>'orange','warehouse'=>'yellow'];
                    @endphp
                    <x-badge :color="$roleColors[$user->role] ?? 'gray'">
                        {{ ucfirst($user->role) }}
                    </x-badge>
                </td>
                <td class="px-5 py-3.5 text-gray-600">{{ $user->outlet?->name ?? 'Semua Outlet' }}</td>
                <td class="px-5 py-3.5">
                    <x-badge :color="$user->is_active ? 'green' : 'red'">
                        {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                    </x-badge>
                </td>
                <td class="px-5 py-3.5 text-right">
                    <div class="flex items-center justify-end gap-2">
                        @if($user->id !== auth()->id())
                        <form method="POST" action="{{ route('users.toggle', $user) }}">
                            @csrf @method('PATCH')
                            <button class="text-xs font-medium text-gray-500 hover:text-gray-700 px-2 py-1 rounded-lg hover:bg-gray-100 transition-colors">
                                {{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                            </button>
                        </form>
                        @endif
                        <a href="{{ route('users.edit', $user) }}"
                           class="text-xs font-medium text-emerald-600 hover:text-emerald-700 px-2 py-1 rounded-lg hover:bg-emerald-50 transition-colors">
                            Edit
                        </a>
                        @if($user->id !== auth()->id())
                        <form method="POST" action="{{ route('users.destroy', $user) }}"
                              onsubmit="return confirm('Hapus user ini?')">
                            @csrf @method('DELETE')
                            <button class="text-xs font-medium text-red-600 hover:text-red-700 px-2 py-1 rounded-lg hover:bg-red-50 transition-colors">
                                Hapus
                            </button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-5 py-12 text-center text-sm text-gray-400">Belum ada user</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</x-card>
<div class="mt-4">{{ $users->links() }}</div>
@endsection
