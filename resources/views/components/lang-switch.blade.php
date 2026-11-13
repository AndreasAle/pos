@props(['variant' => 'dark'])
@php
    $current = $appLocale ?? app()->getLocale();
    $wrap = $variant === 'dark'
        ? 'bg-white/10 ring-1 ring-inset ring-white/15'
        : 'bg-slate-100 ring-1 ring-inset ring-[#E5E7EB]';
    $on = $variant === 'dark' ? 'bg-white text-[#0A5C3A] shadow-sm' : 'bg-white text-[#16A34A] shadow-sm ring-1 ring-[#E5E7EB]';
    $off = $variant === 'dark' ? 'text-white/75 hover:text-white' : 'text-[#64748B] hover:text-[#0B1B3F]';
    $langs = ['id' => ['ID', 'Bahasa Indonesia'], 'en' => ['EN', 'English']];
@endphp
<div {{ $attributes->class(['inline-flex h-8 items-center gap-0.5 rounded-lg p-0.5', $wrap]) }} role="group" aria-label="Language">
    @foreach($langs as $code => [$short, $name])
    <a href="{{ route('lang.switch', $code) }}" title="{{ $name }}" hreflang="{{ $code }}" lang="{{ $code }}"
       @if($current === $code) aria-current="true" @endif
       class="inline-flex h-7 min-w-[2.1rem] items-center justify-center rounded-md px-2 text-[12px] font-bold tracking-wide transition-colors {{ $current === $code ? $on : $off }}">{{ $short }}</a>
    @endforeach
</div>
