@props(['route', 'label' => 'Tanya Yoka', 'tag' => 'AI'])
@php $active = request()->routeIs($route) || request()->routeIs($route . '.*'); @endphp
<a href="{{ route($route) }}"
   {{ $attributes }}
   @mouseenter="tipShow($event, @js($label))" @mouseleave="tipHide()" @focus="tipShow($event, @js($label))" @blur="tipHide()"
   @if($active) aria-current="page" @endif
   class="group relative my-1 flex h-12 items-center gap-3 overflow-hidden rounded-xl px-3 text-[14px] font-bold leading-none text-white shadow-sm ring-1 ring-inset transition-all duration-200 hover:-translate-y-px hover:shadow-md
          bg-gradient-to-br from-[#0B1B3F] via-[#0F3D3E] to-[#0A5C3A]
          {{ $active ? 'ring-[#4ADE80] shadow-md' : 'ring-white/10' }}"
   :class="collapsed ? 'lg:justify-center lg:px-0' : ''">
    {{-- kilau halus --}}
    <span class="pointer-events-none absolute -right-6 -top-8 h-20 w-20 rounded-full bg-[#4ADE80]/25 blur-2xl transition-opacity group-hover:opacity-100"></span>

    <span class="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-[#4ADE80] to-[#16A34A] text-[#052E1B] shadow-inner">
        <svg class="h-[18px] w-[18px]" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l1.9 5.6L19.5 9.5l-5.6 1.9L12 17l-1.9-5.6L4.5 9.5l5.6-1.9L12 2zm7 11l.95 2.55L22.5 16.5l-2.55.95L19 20l-.95-2.55-2.55-.95 2.55-.95L19 13zM5.5 14l.7 1.8 1.8.7-1.8.7-.7 1.8-.7-1.8-1.8-.7 1.8-.7.7-1.8z"/></svg>
    </span>

    <span class="relative truncate" :class="collapsed ? 'lg:hidden' : ''">{{ $label }}</span>
    <span class="relative ml-auto rounded-full bg-[#4ADE80]/20 px-2 py-0.5 text-[10px] font-extrabold tracking-wider text-[#86EFAC] ring-1 ring-inset ring-[#4ADE80]/30" :class="collapsed ? 'lg:hidden' : ''">{{ $tag }}</span>
</a>
