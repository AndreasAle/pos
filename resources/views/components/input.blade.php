@props(['name', 'type' => 'text', 'prefix' => null])
@php $bad = $errors->has($name); @endphp
<div class="relative">
    @if($prefix)<span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm font-semibold text-[#64748B]">{{ $prefix }}</span>@endif
    <input type="{{ $type }}" name="{{ $name }}" id="{{ $name }}"
        {{ $attributes->class([
            'h-11 w-full rounded-lg border bg-white text-sm text-[#0B1B3F] placeholder:text-slate-400 transition focus:outline-none focus:ring-2',
            $prefix ? 'pl-11 pr-3.5' : 'px-3.5',
            $bad ? 'border-red-300 focus:border-red-400 focus:ring-red-200' : 'border-[#E5E7EB] focus:border-[#16A34A] focus:ring-[#16A34A]/30',
        ]) }}>
</div>
