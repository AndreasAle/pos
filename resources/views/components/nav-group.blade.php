@props(['title', 'routes' => []])
@php
    $hasActive = $routes ? request()->routeIs(...$routes) : false;
    $key = 'yoka.nav.' . \Illuminate\Support\Str::slug($title);
@endphp
{{-- Grup menu yang bisa diciutkan. Saat sidebar collapsed, semua item tampil & judul jadi garis pemisah. --}}
<div class="pt-3.5" x-data="{
        open: (() => { if (@js($hasActive)) return true; try { return localStorage.getItem(@js($key)) !== '0' } catch (e) { return true } })(),
        flip() { this.open = !this.open; try { localStorage.setItem(@js($key), this.open ? '1' : '0') } catch (e) {} }
     }">
    <button type="button" @click="flip()" :aria-expanded="open"
            class="flex h-9 w-full cursor-pointer items-center justify-between rounded-lg px-3 text-[12px] font-semibold uppercase tracking-[.075em] transition-colors hover:bg-[#F7F8F9] hover:text-[#344054]" :class="[collapsed ? 'lg:hidden' : '', open ? 'text-[#667085]' : 'text-[#7C8698]']">
        <span>{{ $title }}</span>
        <svg class="h-[18px] w-[18px] text-[#98A2B3] transition-transform duration-200" :class="open ? '' : '-rotate-90'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
    </button>
    <div class="hidden border-t border-[#E5E7EB] mx-2 mb-1" :class="collapsed ? 'lg:block' : ''"></div>
    <div class="space-y-0.5" x-show="open || (collapsed && window.innerWidth >= 1024)">
        {{ $slot }}
    </div>
</div>
