@props(['name', 'label', 'desc' => null, 'checked' => false, 'hidden' => true])
<label {{ $attributes->class(['flex cursor-pointer items-start justify-between gap-4 rounded-lg border border-[#E5E7EB] bg-white p-3.5 transition-colors hover:border-[#16A34A]/50']) }}>
    <span class="min-w-0">
        <span class="block text-sm font-semibold text-[#0B1B3F]">{{ $label }}</span>
        @if($desc)<span class="mt-0.5 block text-xs text-[#64748B]">{{ $desc }}</span>@endif
    </span>
    @if($hidden)<input type="hidden" name="{{ $name }}" value="0">@endif
    <input type="checkbox" name="{{ $name }}" value="1" class="peer sr-only" @checked($checked)>
    <span class="relative mt-0.5 h-6 w-11 shrink-0 rounded-full bg-slate-300 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition-transform peer-checked:bg-[#16A34A] peer-checked:after:translate-x-5 peer-focus-visible:ring-2 peer-focus-visible:ring-[#16A34A]/40"></span>
</label>
