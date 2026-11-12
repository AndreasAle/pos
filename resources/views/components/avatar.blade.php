@props(['user', 'class' => 'h-8 w-8', 'text' => 'text-sm'])
@php
    $url = $user->avatarUrl();
    $initial = mb_strtoupper(mb_substr(trim($user->name), 0, 1));
@endphp
<span data-avatar-user="{{ $user->id }}" data-initial="{{ $initial }}" data-text="{{ $text }}"
      {{ $attributes->merge(['class' => 'relative inline-flex shrink-0 select-none items-center justify-center overflow-hidden rounded-full bg-[#DDF8E8] font-bold text-[#087A4E] '.$class]) }}>
    @if($url)<img src="{{ $url }}" alt="" referrerpolicy="no-referrer" class="h-full w-full object-cover">@else<span class="{{ $text }}">{{ $initial }}</span>@endif
</span>
@once
<script>
// Ganti semua avatar milik pengguna di halaman (topbar, profil) tanpa memuat ulang.
window.yokaSetAvatar = function (userId, url) {
    document.querySelectorAll('[data-avatar-user="' + userId + '"]').forEach(function (el) {
        el.textContent = '';
        if (url) {
            var img = document.createElement('img');
            img.src = url; img.alt = ''; img.referrerPolicy = 'no-referrer'; img.className = 'h-full w-full object-cover';
            el.appendChild(img);
        } else {
            var s = document.createElement('span');
            s.className = el.dataset.text || 'text-sm'; s.textContent = el.dataset.initial || '';
            el.appendChild(s);
        }
    });
};
</script>
@endonce
