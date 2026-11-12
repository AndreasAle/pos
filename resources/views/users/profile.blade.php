@extends('layouts.app')
@section('title', 'Profil Saya')
@section('page-title', 'Profil Saya')
@section('page-subtitle', 'Kelola identitas, foto, dan keamanan akunmu.')

@section('content')
@php
    $hasUpload = filled($user->avatar);
    $roleLabel = ['owner' => 'Owner', 'admin' => 'Admin', 'cashier' => 'Kasir'][$user->role] ?? ucfirst($user->role);
    $input = 'h-11 w-full rounded-lg border border-[#E5E7EB] bg-white px-3.5 text-[14px] font-medium text-[#0B1B3F] placeholder:text-slate-400 focus:border-[#16A34A] focus:outline-none focus:ring-4 focus:ring-[#16A34A]/10';
@endphp
<div class="grid grid-cols-1 gap-5 pb-8 lg:grid-cols-[340px_minmax(0,1fr)]"
     x-data="profilePhoto(@js($user->id), @js(route('profile.avatar.store')), @js(route('profile.avatar.destroy')), @js($hasUpload))">

    {{-- Kartu identitas + foto --}}
    <section class="h-fit rounded-xl border border-[#E5E7EB] bg-white p-6 text-center">
        <div class="relative mx-auto h-28 w-28">
            <x-avatar :user="$user" class="h-28 w-28 ring-4 ring-[#F4FBF7]" text="text-4xl"/>
            <button type="button" @click="$refs.file.click()" class="absolute -bottom-1 -right-1 flex h-10 w-10 items-center justify-center rounded-full border-2 border-white bg-[#16A34A] text-white shadow-md transition hover:bg-[#15803D]" aria-label="Ganti foto profil" title="Ganti foto profil">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h1.2a2 2 0 001.6-.8l.8-1.2A2 2 0 019.2 4h5.6a2 2 0 011.6 1l.8 1.2a2 2 0 001.6.8H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><circle cx="12" cy="13" r="3.5"/></svg>
            </button>
            @if($user->hasGoogle())
            <span class="absolute -left-1 -top-1 flex h-9 w-9 items-center justify-center rounded-full border border-[#E5E7EB] bg-white shadow-sm" title="Masuk dengan Google" aria-label="Terhubung dengan Google">
                <svg class="h-5 w-5" viewBox="0 0 48 48"><path fill="#FFC107" d="M43.6 20.1H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 8 3l5.7-5.7C34 6.1 29.3 4 24 4 13 4 4 13 4 24s9 20 20 20 20-9 20-20c0-1.3-.1-2.6-.4-3.9z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 8 3l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-7.9l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.1H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.6-.4-3.9z"/></svg>
            </span>
            @endif
        </div>

        <h2 class="mt-4 text-[20px] font-bold leading-tight text-[#0B1B3F]">{{ $user->name }}</h2>
        <p class="mt-0.5 break-all text-[14px] text-slate-500">{{ $user->email }}</p>
        <div class="mt-3 flex flex-wrap items-center justify-center gap-2">
            <x-pill tone="green">{{ $roleLabel }}</x-pill>
            @if($user->hasGoogle())<x-pill tone="blue">Akun Google</x-pill>@endif
        </div>

        <div class="mt-5 flex flex-col gap-2">
            <button type="button" @click="$refs.file.click()" class="inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-[#E5E7EB] bg-white px-4 text-[14px] font-semibold text-[#0B1B3F] transition hover:border-[#16A34A] hover:bg-[#F4FBF7]">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 4v12m0-12l-4 4m4-4l4 4"/></svg>
                Unggah foto
            </button>
            <button type="button" x-show="hasUpload" x-cloak @click="remove()" :disabled="busy" class="h-10 rounded-lg text-[14px] font-semibold text-red-600 transition hover:bg-red-50 disabled:opacity-50">Hapus foto</button>
        </div>
        <p class="mt-3 text-[12px] text-slate-500">JPG, PNG, atau WebP · maksimal 4 MB</p>
        @if($user->google_avatar && ! $hasUpload)<p class="mt-1 text-[12px] text-slate-500">Memakai foto dari akun Google kamu.</p>@endif

        <input type="file" x-ref="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="pick($event)">
        <p x-show="note" x-cloak x-text="note" class="mt-3 rounded-lg px-3 py-2 text-[13px] font-semibold" :class="noteOk ? 'bg-[#ECFDF3] text-[#087A4E]' : 'bg-red-50 text-red-700'"></p>
    </section>

    {{-- Form --}}
    <form method="POST" action="{{ route('profile.update') }}" class="space-y-5">
        @csrf
        <section class="rounded-xl border border-[#E5E7EB] bg-white">
            <div class="border-b border-[#E5E7EB] px-6 py-4"><h3 class="text-[16px] font-bold text-[#0B1B3F]">Informasi pribadi</h3><p class="text-[13px] text-slate-500">Nama dan kontak yang tampil di aplikasi.</p></div>
            <div class="grid grid-cols-1 gap-5 p-6 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-[13px] font-semibold text-slate-600" for="name">Nama lengkap</label>
                    <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required class="{{ $input }}">
                    @error('name')<p class="mt-1 text-[13px] font-semibold text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-[13px] font-semibold text-slate-600" for="phone">No. HP</label>
                    <input id="phone" type="text" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="08xxxxxxxxxx" class="{{ $input }}">
                    @error('phone')<p class="mt-1 text-[13px] font-semibold text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-[13px] font-semibold text-slate-600">Email</label>
                    <div class="flex h-11 items-center gap-2 rounded-lg border border-[#E5E7EB] bg-[#F8FAFC] px-3.5 text-[14px] text-slate-500">
                        <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 10-8 0v4m-1 0h10a2 2 0 012 2v6a2 2 0 01-2 2H7a2 2 0 01-2-2v-6a2 2 0 012-2z"/></svg>
                        <span class="truncate">{{ $user->email }}</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-[#E5E7EB] bg-white" x-data="passwordLink(@js(route('profile.password.link')))">
            <div class="border-b border-[#E5E7EB] px-6 py-4"><h3 class="text-[16px] font-bold text-[#0B1B3F]">Keamanan</h3><p class="text-[13px] text-slate-500">Ganti password dikonfirmasi lewat email, seperti di aplikasi lain.</p></div>
            <div class="p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#ECFDF3] text-[#16A34A]"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l9 6 9-6M5 5h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/></svg></span>
                        <div>
                            <p class="text-[15px] font-semibold text-[#0B1B3F]">Password</p>
                            <p class="text-[13px] text-slate-500">Kami kirim tautan ke <span class="font-semibold text-slate-700">{{ \Illuminate\Support\Str::mask($user->email, '*', 1, max(2, mb_strpos($user->email, '@') - 1)) }}</span>. Klik tautan itu untuk membuat password baru.{{ $user->hasGoogle() ? ' Kamu masuk dengan Google, jadi password hanya perlu jika ingin masuk memakai email.' : '' }}</p>
                        </div>
                    </div>
                    <button type="button" @click="send()" :disabled="busy || cooldown > 0" class="inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-lg border border-[#E5E7EB] bg-white px-5 text-[14px] font-semibold text-[#0B1B3F] transition hover:border-[#16A34A] hover:bg-[#F4FBF7] disabled:cursor-not-allowed disabled:opacity-60">
                        <span x-text="busy ? 'Mengirim…' : (cooldown > 0 ? 'Kirim ulang (' + cooldown + 's)' : (sent ? 'Kirim ulang tautan' : 'Kirim tautan ke email'))"></span>
                    </button>
                </div>
                <div x-show="sent" x-cloak class="mt-4 rounded-lg border border-[#16A34A]/25 bg-[#ECFDF3] px-4 py-3 text-[14px] font-semibold text-[#066B45]">
                    <p x-text="message"></p>
                    <p class="mt-1 text-[13px] font-semibold text-[#087A4E]">Dikirim ke <span x-text="email"></span>. Berlaku <span x-text="minutes"></span> menit. Belum ada? Cek folder spam.</p>
                </div>
                <p x-show="error" x-cloak x-text="error" class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-[14px] font-semibold text-red-700"></p>
            </div>
        </section>
        <div class="flex justify-end">
            <button type="submit" class="inline-flex h-11 items-center rounded-lg bg-[#16A34A] px-6 text-[14px] font-semibold text-white shadow-sm transition hover:bg-[#15803D]">Simpan perubahan</button>
        </div>
    </form>

    {{-- Konfirmasi foto sebelum disimpan --}}
    <div x-show="preview" x-cloak class="fixed inset-0 z-[120] flex items-center justify-center bg-[#0B1B3F]/55 p-4" @keydown.escape.window="cancel()">
        <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl" @click.outside="cancel()">
            <h3 class="text-[18px] font-bold text-[#0B1B3F]">Gunakan foto ini?</h3>
            <p class="mt-1 text-[14px] text-slate-500">Foto akan dipotong persegi dan tampil sebagai foto profilmu.</p>
            <div class="mx-auto mt-5 h-44 w-44 overflow-hidden rounded-full ring-4 ring-[#F4FBF7]"><img :src="preview" alt="Pratinjau foto" class="h-full w-full object-cover"></div>
            <p x-show="error" x-cloak x-text="error" class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-[13px] font-semibold text-red-700"></p>
            <div class="mt-6 grid grid-cols-2 gap-3">
                <button type="button" @click="cancel()" :disabled="busy" class="h-11 rounded-lg border border-[#E5E7EB] text-[14px] font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50">Batal</button>
                <button type="button" @click="confirm()" :disabled="busy" class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-[#16A34A] text-[14px] font-semibold text-white hover:bg-[#15803D] disabled:opacity-60"><span x-text="busy ? 'Menyimpan…' : 'Ya, simpan foto'"></span></button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function passwordLink(url) {
    const token = document.querySelector('meta[name="csrf-token"]').content;
    return {
        busy: false, sent: false, message: '', email: '', minutes: 60, error: '', cooldown: 0,
        async send() {
            if (this.busy || this.cooldown > 0) return; this.busy = true; this.error = '';
            try {
                const r = await fetch(url, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token } });
                const d = await r.json().catch(() => ({}));
                if (!r.ok) throw new Error(d.message || 'Tautan gagal dikirim.');
                this.sent = true; this.message = d.message; this.email = d.email; this.minutes = d.expires_minutes;
                this.cooldown = 60; const t = setInterval(() => { if (--this.cooldown <= 0) clearInterval(t); }, 1000);
            } catch (e) { this.error = e.message; } finally { this.busy = false; }
        },
    };
}
function profilePhoto(userId, storeUrl, destroyUrl, hasUpload) {
    const token = document.querySelector('meta[name="csrf-token"]').content;
    return {
        hasUpload, busy: false, preview: null, file: null, error: '', note: '', noteOk: true,
        pick(e) {
            const f = e.target.files[0]; e.target.value = ''; if (!f) return;
            if (!/^image\/(jpeg|png|webp)$/.test(f.type)) { this.flash('Format harus JPG, PNG, atau WebP.', false); return; }
            if (f.size > 4 * 1024 * 1024) { this.flash('Ukuran foto maksimal 4 MB.', false); return; }
            this.file = f; this.error = ''; this.preview = URL.createObjectURL(f);
        },
        cancel() { if (this.busy) return; if (this.preview) URL.revokeObjectURL(this.preview); this.preview = null; this.file = null; this.error = ''; },
        async confirm() {
            if (!this.file || this.busy) return; this.busy = true; this.error = '';
            const body = new FormData(); body.append('photo', this.file);
            try {
                const r = await fetch(storeUrl, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token }, body });
                const d = await r.json().catch(() => ({}));
                if (!r.ok) throw new Error(d.message || (d.errors ? Object.values(d.errors).flat()[0] : 'Foto gagal disimpan.'));
                window.yokaSetAvatar(userId, d.url); this.hasUpload = true; this.busy = false; this.cancel(); this.flash(d.message || 'Foto profil berhasil disimpan.', true);
            } catch (e) { this.busy = false; this.error = e.message; }
        },
        async remove() {
            if (this.busy || !confirm('Hapus foto profil?')) return; this.busy = true;
            try {
                const r = await fetch(destroyUrl, { method: 'DELETE', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token } });
                const d = await r.json(); if (!r.ok) throw new Error(d.message || 'Gagal menghapus foto.');
                window.yokaSetAvatar(userId, d.url); this.hasUpload = false; this.flash(d.message, true);
            } catch (e) { this.flash(e.message, false); } finally { this.busy = false; }
        },
        flash(m, ok) { this.note = m; this.noteOk = ok; clearTimeout(this._t); this._t = setTimeout(() => this.note = '', 4000); },
    };
}
</script>
@endpush
