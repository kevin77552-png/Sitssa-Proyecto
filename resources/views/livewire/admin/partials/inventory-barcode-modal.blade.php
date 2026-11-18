@if($barcodeItem)
    <div wire:click.self="closeBarcodeModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="inventory-barcode-title">
        <section x-data="{ hasError: false, barcodeReady: false }" class="w-full max-w-lg overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
            <header class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4 sm:px-6">
                <div>
                    <h2 id="inventory-barcode-title" class="text-base font-bold text-slate-900">Código de barras del repuesto</h2>
                    <p class="mt-1 text-xs text-slate-500">{{ $barcodeItem->descripcion }}</p>
                </div>
                <button type="button" wire:click="closeBarcodeModal" aria-label="Cerrar" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </header>

            <div class="space-y-4 p-5 sm:p-6">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-center">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">SIGICOV</span>
                    <p class="mt-1 font-mono text-lg font-bold tracking-wider text-brand-900">{{ $barcodeItem->sigicov }}</p>
                    <div class="mt-4 overflow-x-auto rounded-lg bg-white px-2 py-3">
                        <svg
                            x-ref="barcode"
                            x-init="$nextTick(() => { hasError = typeof window.renderInventoryBarcode !== 'function' || !window.renderInventoryBarcode($el, @js($barcodeItem->sigicov)); barcodeReady = !hasError })"
                            class="mx-auto h-24 max-w-full"
                            role="img"
                            aria-label="Código de barras Code 128"
                        ></svg>
                        <p x-show="hasError" class="mt-2 text-xs text-rose-700" role="alert">No se pudo cargar o generar el código de barras. Actualiza la página e inténtalo nuevamente; si el problema continúa, verifica que los recursos de la aplicación estén actualizados.</p>
                    </div>
                </div>
                <p class="text-center text-xs text-slate-500">Formato Code 128 · El código representa el SIGICOV mostrado.</p>
            </div>

            <footer class="flex items-center justify-between border-t border-slate-100 bg-slate-50 px-5 py-3.5 sm:px-6">
                <button
                    type="button"
                    x-on:click="window.downloadInventoryBarcodeLabel($refs.barcode, @js($barcodeItem->sigicov), @js($barcodeItem->descripcion))"
                    x-bind:disabled="!barcodeReady"
                    class="rounded-lg border border-brand-700 bg-brand-700 px-4 py-2 text-xs font-semibold text-white transition hover:bg-brand-800 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Descargar etiqueta SVG
                </button>
                <button type="button" wire:click="closeBarcodeModal" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100">
                    Cerrar
                </button>
            </footer>
        </section>
    </div>
@endif
