@props(['name', 'value', 'label', 'desc' => null, 'checked' => false])
<label {{ $attributes->class(['relative block cursor-pointer']) }}>
    <input type="radio" name="{{ $name }}" value="{{ $value }}" class="peer sr-only" @checked($checked)>
    <span class="flex items-start gap-3 rounded-lg border border-[#E5E7EB] bg-white p-3.5 transition-colors hover:border-[#16A34A]/50 peer-checked:border-[#16A34A] peer-checked:bg-[#ECFDF3] peer-checked:ring-1 peer-checked:ring-[#16A34A] peer-focus-visible:ring-2 peer-focus-visible:ring-[#16A34A]/40">
        <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2 border-slate-300 bg-white">
            <span class="h-1.5 w-1.5 rounded-full bg-[#16A34A] opacity-0 [label:has(:checked)_&]:opacity-100"></span>
        </span>
        <span class="min-w-0">
            <span class="block text-sm font-semibold text-[#0B1B3F]">{{ $label }}</span>
            @if($desc)<span class="mt-0.5 block text-xs text-[#64748B]">{{ $desc }}</span>@endif
        </span>
    </span>
</label>
