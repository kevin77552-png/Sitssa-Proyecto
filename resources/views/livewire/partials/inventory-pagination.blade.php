<div class="border-t border-slate-200/80 bg-slate-50/70 px-4 py-4 sm:px-6">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200/70 pb-3.5 text-xs">
        <div class="flex flex-wrap items-center gap-2">
            <label for="{{ $paginationId }}-per-page" class="font-medium text-slate-500">Mostrar:</label>
            <select id="{{ $paginationId }}-per-page" wire:model.live="perPage" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 font-mono text-xs font-medium text-slate-700 focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-600/20">
                @foreach([15, 20, 25, 50] as $pageSize)
                    <option value="{{ $pageSize }}">{{ $pageSize }}</option>
                @endforeach
            </select>
            <span class="text-slate-500">por página</span>
            @error('perPage')
                <span class="text-red-600" role="alert">{{ $message }}</span>
            @enderror

            <span class="mx-1 hidden h-5 w-px bg-slate-200 sm:block" aria-hidden="true"></span>

            <form wire:submit="jumpToPage" class="flex items-center gap-1.5">
                <label for="{{ $paginationId }}-page-jump" class="font-medium text-slate-500">Ir a pág:</label>
                <input
                    id="{{ $paginationId }}-page-jump"
                    type="number"
                    min="1"
                    max="{{ $items->lastPage() }}"
                    wire:model="pageJump"
                    aria-label="Número de página"
                    class="w-16 rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-center font-mono text-xs text-slate-800 focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-600/20"
                >
                <button type="submit" class="rounded-lg bg-brand-900 px-2.5 py-1.5 font-medium text-white transition hover:bg-brand-800 focus:outline-none focus:ring-2 focus:ring-brand-600/30">
                    Ir
                </button>
            </form>
        </div>
        @error('pageJump')
            <p class="w-full text-xs text-red-600" role="alert">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex flex-col items-center justify-between gap-3 pt-3 sm:flex-row">
        <p class="text-xs text-slate-500" aria-live="polite">
            Mostrando
            <span class="font-mono font-semibold text-slate-800">{{ number_format($items->firstItem() ?? 0, 0, ',', '.') }}</span>
            a
            <span class="font-mono font-semibold text-slate-800">{{ number_format($items->lastItem() ?? 0, 0, ',', '.') }}</span>
            de
            <span class="font-mono font-semibold text-slate-800">{{ number_format($items->total(), 0, ',', '.') }}</span>
            resultados
        </p>

        @if($items->hasPages())
            <nav aria-label="{{ $paginationLabel ?? 'Paginación de inventario' }}" class="inline-flex items-center gap-1.5">
                @if($items->onFirstPage())
                    <span aria-disabled="true" class="inline-flex cursor-not-allowed items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-medium text-slate-300">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        <span class="hidden sm:inline">Anterior</span>
                    </span>
                @else
                    <button type="button" wire:click="previousPage" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-600 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-600/20">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        <span class="hidden sm:inline">Anterior</span>
                    </button>
                @endif

                @php
                    $lastPage = $items->lastPage();
                    $visiblePages = $lastPage <= 7
                        ? range(1, $lastPage)
                        : array_values(array_unique(array_filter([
                            1,
                            $items->currentPage() > 3 ? 'ellipsis-before' : null,
                            ...range(max(2, $items->currentPage() - 1), min($lastPage - 1, $items->currentPage() + 1)),
                            $items->currentPage() < $lastPage - 2 ? 'ellipsis-after' : null,
                            $lastPage,
                        ], fn ($page) => $page !== null)));
                @endphp
                @foreach($visiblePages as $page)
                    @if(is_string($page))
                        <span aria-hidden="true" class="px-1.5 text-xs text-slate-400">…</span>
                    @elseif($page === $items->currentPage())
                        <span aria-current="page" class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg border border-[#4A0C12] bg-[#4A0C12] px-2.5 font-mono text-xs font-semibold text-white shadow-sm">
                            {{ $page }}
                        </span>
                    @else
                        <button type="button" wire:click="setPage({{ $page }})" aria-label="Ir a página {{ $page }}" class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg border border-slate-200 bg-white px-2.5 font-mono text-xs font-medium text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-600/20">
                            {{ $page }}
                        </button>
                    @endif
                @endforeach

                @if($items->hasMorePages())
                    <button type="button" wire:click="nextPage" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-600 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-600/20">
                        <span class="hidden sm:inline">Siguiente</span>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                @else
                    <span aria-disabled="true" class="inline-flex cursor-not-allowed items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-medium text-slate-300">
                        <span class="hidden sm:inline">Siguiente</span>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </span>
                @endif
            </nav>
        @endif
    </div>
</div>
