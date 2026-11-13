@props(['variant' => 'dark'])
@php
    $current = $appLocale ?? app()->getLocale();
    $wrap = $variant === 'dark'
        ? 'bg-white/10 ring-1 ring-inset ring-white/15'
        : 'bg-slate-100 ring-1 ring-inset ring-[#E5E7EB]';
    $on  = $variant === 'dark' ? 'bg-white shadow-sm' : 'bg-white shadow-sm ring-1 ring-[#E5E7EB]';
    $off = 'opacity-60 hover:opacity-100';
    $langs = ['id' => 'Bahasa Indonesia', 'en' => 'English'];
@endphp
<div {{ $attributes->class(['inline-flex h-8 items-center gap-0.5 rounded-lg p-0.5', $wrap]) }} role="group" aria-label="Language">
    @foreach($langs as $code => $name)
    <a href="{{ route('lang.switch', $code) }}" title="{{ $name }}" aria-label="{{ $name }}" hreflang="{{ $code }}" lang="{{ $code }}"
       @if($current === $code) aria-current="true" @endif
       class="inline-flex h-7 w-9 items-center justify-center rounded-md transition-all {{ $current === $code ? $on : $off }}">
        @if($code === 'id')
        {{-- Indonesia: merah di atas, putih di bawah --}}
        <svg class="h-[15px] w-[22px] rounded-[3px] ring-1 ring-black/15" viewBox="0 0 22 15" aria-hidden="true">
            <rect width="22" height="7.5" fill="#E70011"/><rect y="7.5" width="22" height="7.5" fill="#FFFFFF"/>
        </svg>
        @else
        {{-- Inggris (Union Jack) --}}
        <svg class="h-[15px] w-[22px] rounded-[3px] ring-1 ring-black/15" viewBox="0 0 60 40" aria-hidden="true">
            <rect width="60" height="40" fill="#012169"/>
            <path d="M0 0l60 40M60 0L0 40" stroke="#FFFFFF" stroke-width="8"/>
            <path d="M0 0l60 40M60 0L0 40" stroke="#C8102E" stroke-width="3"/>
            <path d="M30 0v40M0 20h60" stroke="#FFFFFF" stroke-width="13"/>
            <path d="M30 0v40M0 20h60" stroke="#C8102E" stroke-width="7"/>
        </svg>
        @endif
    </a>
    @endforeach
</div>
