@php
    if (! isset($scrollTo)) {
        $scrollTo = 'body';
    }

    $scrollIntoViewJsSnippet = ($scrollTo !== false)
        ? <<<JS
           (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
        JS
        : '';
@endphp

<div>
    @if ($paginator->hasPages())
        <nav role="navigation" aria-label="{{ $paginationLabel ?? 'Paginación' }}" class="flex flex-col items-center justify-between gap-3 sm:flex-row">
            <p class="text-xs text-slate-500" aria-live="polite">
                Mostrando
                <span class="font-mono font-semibold text-slate-800">{{ number_format($paginator->firstItem() ?? 0, 0, ',', '.') }}</span>
                a
                <span class="font-mono font-semibold text-slate-800">{{ number_format($paginator->lastItem() ?? 0, 0, ',', '.') }}</span>
                de
                <span class="font-mono font-semibold text-slate-800">{{ number_format($paginator->total(), 0, ',', '.') }}</span>
                resultados
            </p>

            <div class="flex flex-wrap items-center justify-center gap-1.5">
                @if ($paginator->onFirstPage())
                    <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}" class="inline-flex cursor-not-allowed items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-medium text-slate-300">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        <span class="hidden sm:inline">Anterior</span>
                    </span>
                @else
                    <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" aria-label="{{ __('pagination.previous') }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-600 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-600/20 disabled:cursor-wait disabled:opacity-70">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        <span class="hidden sm:inline">Anterior</span>
                    </button>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span aria-hidden="true" class="px-1.5 text-xs text-slate-400">{{ $element }}</span>
                    @elseif (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span wire:key="paginator-{{ $paginator->getPageName() }}-page-{{ $page }}" aria-current="page" class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg border border-brand-900 bg-brand-900 px-2.5 font-mono text-xs font-semibold text-white shadow-sm">
                                    {{ $page }}
                                </span>
                            @else
                                <button type="button" wire:key="paginator-{{ $paginator->getPageName() }}-page-{{ $page }}" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" aria-label="Ir a página {{ $page }}" class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg border border-slate-200 bg-white px-2.5 font-mono text-xs font-medium text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-600/20">
                                    {{ $page }}
                                </button>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" aria-label="{{ __('pagination.next') }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-600 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-600/20 disabled:cursor-wait disabled:opacity-70">
                        <span class="hidden sm:inline">Siguiente</span>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                @else
                    <span aria-disabled="true" aria-label="{{ __('pagination.next') }}" class="inline-flex cursor-not-allowed items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-medium text-slate-300">
                        <span class="hidden sm:inline">Siguiente</span>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </span>
                @endif
            </div>
        </nav>
    @endif
</div>
