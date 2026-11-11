@props(['title', 'routes' => []])
@php
    $hasActive = $routes ? request()->routeIs(...$routes) : false;
    $key = 'yoka.nav.' . \Illuminate\Support\Str::slug($title);
@endphp
{{-- Grup menu yang bisa diciutkan. Saat sidebar collapsed, semua item tampil & judul jadi garis pemisah. --}}
<div class="pt-2" x-data="{
        open: (() => { if (@js($hasActive)) return true; try { return localStorage.getItem(@js($key)) !== '0' } catch (e) { return true } })(),
        flip() { this.open = !this.open; try { localStorage.setItem(@js($key), this.open ? '1' : '0') } catch (e) {} }
     }">
    <button type="button" @click="flip()" :class="collapsed ? 'lg:hidden' : ''" :aria-expanded="open"
            class="w-full flex items-center justify-between px-3 h-7 rounded-md text-xs font-semibold text-gray-400 hover:text-gray-600 transition-colors">
        <span>{{ $title }}</span>
        <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="open ? '' : '-rotate-90'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
    </button>
    <div class="hidden border-t border-[#E5E7EB] mx-2 mb-1" :class="collapsed ? 'lg:block' : ''"></div>
    <div class="space-y-0.5" x-show="open || (collapsed && window.innerWidth >= 1024)">
        {{ $slot }}
    </div>
</div>
