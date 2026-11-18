@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ $paginationLabel ?? 'Paginación' }}" class="flex flex-col items-center justify-between gap-3 sm:flex-row">
        <div class="flex w-full justify-between sm:hidden">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" class="inline-flex cursor-not-allowed items-center rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-300">
                    {{ __('pagination.previous') }}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-600/20">
                    {{ __('pagination.previous') }}
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-600/20">
                    {{ __('pagination.next') }}
                </a>
            @else
                <span aria-disabled="true" class="inline-flex cursor-not-allowed items-center rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-300">
                    {{ __('pagination.next') }}
                </span>
            @endif
        </div>

        <p class="hidden text-xs text-slate-500 sm:block" aria-live="polite">
            Mostrando
            <span class="font-mono font-semibold text-slate-800">{{ number_format($paginator->firstItem() ?? 0, 0, ',', '.') }}</span>
            a
            <span class="font-mono font-semibold text-slate-800">{{ number_format($paginator->lastItem() ?? 0, 0, ',', '.') }}</span>
            de
            <span class="font-mono font-semibold text-slate-800">{{ number_format($paginator->total(), 0, ',', '.') }}</span>
            resultados
        </p>

        <div class="hidden items-center gap-1.5 sm:flex">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}" class="inline-flex cursor-not-allowed items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-medium text-slate-300">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    <span>Anterior</span>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-600 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-600/20">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    <span>Anterior</span>
                </a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span aria-hidden="true" class="px-1.5 text-xs text-slate-400">{{ $element }}</span>
                @elseif (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg border border-brand-900 bg-brand-900 px-2.5 font-mono text-xs font-semibold text-white shadow-sm">
                                {{ $page }}
                            </span>
                        @else
                            <a href="{{ $url }}" aria-label="Ir a página {{ $page }}" class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg border border-slate-200 bg-white px-2.5 font-mono text-xs font-medium text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-600/20">
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-600 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-600/20">
                    <span>Siguiente</span>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            @else
                <span aria-disabled="true" aria-label="{{ __('pagination.next') }}" class="inline-flex cursor-not-allowed items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-medium text-slate-300">
                    <span>Siguiente</span>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </span>
            @endif
        </div>
    </nav>
@endif
