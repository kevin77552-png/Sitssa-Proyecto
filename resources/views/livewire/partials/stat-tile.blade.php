<article class="flex min-h-[128px] flex-col justify-center rounded-xl border border-slate-200 border-l-4 border-l-brand-900 bg-white px-5 py-4 shadow-sm">
    <p class="text-sm font-semibold tracking-wide text-slate-600">{{ $label }}</p>
    <p class="mt-1 text-3xl font-bold leading-tight tracking-tight text-brand-900">{{ $value }}</p>
    <p class="mt-1.5 min-h-5 text-sm text-slate-500">{{ $hint ?? '' }}</p>
</article>
