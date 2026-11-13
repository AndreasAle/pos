@props(['label', 'name' => null, 'required' => false, 'hint' => null])
<div {{ $attributes->class(['min-w-0']) }}>
    <label @if($name) for="{{ $name }}" @endif class="mb-1.5 flex items-baseline gap-1.5 text-sm font-semibold text-[#0B1B3F]">
        <span>{{ $label }}@if($required)<span class="ml-0.5 text-red-500">*</span>@endif</span>
        @if(isset($aside))<span class="ml-auto text-xs font-medium text-[#64748B]">{{ $aside }}</span>@endif
    </label>
    {{ $slot }}
    @if($hint)<p class="mt-1.5 text-xs text-[#64748B]">{{ $hint }}</p>@endif
    @if($name)@error($name)<p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror @endif
</div>
