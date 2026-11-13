@props(['name', 'rows' => 3])
<textarea name="{{ $name }}" id="{{ $name }}" rows="{{ $rows }}"
    {{ $attributes->class([
        'w-full rounded-lg border bg-white px-3.5 py-2.5 text-sm text-[#0B1B3F] placeholder:text-slate-400 transition focus:outline-none focus:ring-2',
        $errors->has($name) ? 'border-red-300 focus:border-red-400 focus:ring-red-200' : 'border-[#E5E7EB] focus:border-[#16A34A] focus:ring-[#16A34A]/30',
    ]) }}>{{ $slot }}</textarea>
