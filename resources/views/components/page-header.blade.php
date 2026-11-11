@props(['title', 'subtitle' => null, 'eyebrow' => null])

<div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-slate-800 to-emerald-900 text-white px-5 py-5 sm:px-7 sm:py-6 mt-2 mb-6 shadow-lg shadow-slate-200">
    <div class="absolute -top-20 -right-12 w-64 h-64 rounded-full bg-emerald-400/20 blur-3xl pointer-events-none"></div>
    <div class="relative flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="min-w-0">
            @if($eyebrow)<p class="text-emerald-300 text-xs font-semibold uppercase tracking-wider">{{ $eyebrow }}</p>@endif
            <h2 class="text-xl sm:text-2xl font-extrabold mt-0.5">{{ $title }}</h2>
            @if($subtitle)<p class="text-slate-300 text-sm mt-1">{{ $subtitle }}</p>@endif
        </div>
        @if(isset($actions) && !$actions->isEmpty())
        <div class="flex flex-wrap gap-2 shrink-0">{{ $actions }}</div>
        @endif
    </div>
</div>
