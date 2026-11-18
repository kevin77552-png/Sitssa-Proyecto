<div class="w-full overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm">
    @if(session('message'))
        <div role="status" class="border-b border-emerald-200 bg-emerald-50 px-6 py-3 text-sm text-emerald-800 sm:px-8">
            {{ session('message') }}
        </div>
    @endif

    <header class="flex flex-col gap-4 border-b border-slate-100 bg-white px-6 py-6 sm:flex-row sm:items-center sm:justify-between sm:px-8">
        <div class="flex items-start gap-3.5">
            <div class="mt-0.5 shrink-0 rounded-lg border border-[#4A0C12]/10 bg-[#4A0C12]/5 p-2.5 text-[#4A0C12]">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
            </div>
            <div>
                <h1 class="text-xl font-bold tracking-tight text-slate-900">Exportación de datos</h1>
                <p class="mt-0.5 text-sm text-slate-500">Descarga respaldos estructurados de los registros actuales del inventario.</p>
            </div>
        </div>

        <span class="inline-flex items-center gap-1.5 self-start rounded-full border border-slate-200/80 bg-slate-100 px-3 py-1 font-mono text-xs font-semibold text-slate-700 sm:self-auto">
            {{ number_format($totalRecords) }} {{ $totalRecords === 1 ? 'registro' : 'registros' }} totales
        </span>
    </header>

    <form wire:submit.prevent="export" class="space-y-7 p-6 sm:p-8">
        <section>
            <div class="mb-3.5 flex items-center gap-2">
                <span class="h-4 w-1.5 rounded-full bg-[#4A0C12]"></span>
                <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500">1. Formato de archivo</h2>
            </div>

            <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2" id="gridFormatos">
                <label @class([
                    'relative flex cursor-pointer select-none flex-col rounded-xl p-4 transition',
                    'border-2 border-[#4A0C12] bg-[#4A0C12]/[0.03]' => $exportFormat === 'csv',
                    'border border-slate-200 bg-white hover:border-slate-300' => $exportFormat !== 'csv',
                ])>
                    <input type="radio" name="exportFormat" value="csv" wire:model.live="exportFormat" class="sr-only">
                    <span class="mb-2 flex items-center justify-between">
                        <span class="rounded bg-emerald-100 px-2 py-0.5 font-mono text-xs font-bold text-emerald-800">.CSV</span>
                        <span @class([
                            'h-4 w-4 rounded-full bg-white',
                            'border-4 border-[#4A0C12]' => $exportFormat === 'csv',
                            'border border-slate-300' => $exportFormat !== 'csv',
                        ])></span>
                    </span>
                    <span class="text-sm font-bold text-slate-900">Valores separados</span>
                    <span class="mt-1 text-[11px] leading-relaxed text-slate-500">Ideal para migraciones, respaldos ligeros e importación en otros sistemas.</span>
                </label>

                <label @class([
                    'relative flex cursor-pointer select-none flex-col rounded-xl p-4 transition',
                    'border-2 border-[#4A0C12] bg-[#4A0C12]/[0.03]' => $exportFormat === 'xlsx',
                    'border border-slate-200 bg-white hover:border-slate-300' => $exportFormat !== 'xlsx',
                ])>
                    <input type="radio" name="exportFormat" value="xlsx" wire:model.live="exportFormat" class="sr-only">
                    <span class="mb-2 flex items-center justify-between">
                        <span class="rounded bg-sky-100 px-2 py-0.5 font-mono text-xs font-bold text-sky-800">.XLSX</span>
                        <span @class([
                            'h-4 w-4 rounded-full bg-white',
                            'border-4 border-[#4A0C12]' => $exportFormat === 'xlsx',
                            'border border-slate-300' => $exportFormat !== 'xlsx',
                        ])></span>
                    </span>
                    <span class="text-sm font-bold text-slate-900">Microsoft Excel</span>
                    <span class="mt-1 text-[11px] leading-relaxed text-slate-500">Hoja de cálculo con encabezados, filtros y columnas ajustadas al contenido.</span>
                </label>
            </div>
            <x-input-error :messages="$errors->get('exportFormat')" class="mt-2" />
        </section>

        <hr class="border-slate-100">

        <section>
            <div class="mb-3.5 flex items-center gap-2">
                <span class="h-4 w-1.5 rounded-full bg-[#4A0C12]"></span>
                <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500">2. Registros a incluir</h2>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="inventory-export-scope" class="mb-1.5 block text-sm font-medium text-slate-700">Conjunto de datos</label>
                    <select id="inventory-export-scope" wire:model.live="scope"
                        class="w-full cursor-pointer rounded-lg border border-slate-300 bg-slate-50/50 px-3.5 py-2.5 text-sm text-slate-800 transition focus:border-[#4A0C12] focus:bg-white focus:outline-none focus:ring-4 focus:ring-[#4A0C12]/10">
                        <option value="all">Todo el inventario registrado ({{ number_format($scopeCounts['all']) }})</option>
                        <option value="available">Repuestos disponibles en almacén ({{ number_format($scopeCounts['available']) }})</option>
                        <option value="dispatched">Repuestos con al menos una salida ({{ number_format($scopeCounts['dispatched']) }})</option>
                        <option value="incomplete">Ubicación pendiente o no disponible ({{ number_format($scopeCounts['incomplete']) }})</option>
                    </select>
                    <x-input-error :messages="$errors->get('scope')" class="mt-1.5" />
                </div>

                <div class="flex flex-col justify-center rounded-lg border border-slate-200/80 bg-slate-50 px-4 py-3">
                    <span class="text-[11px] font-medium text-slate-400">Archivo resultante estimado:</span>
                    <span class="mt-1 break-all font-mono text-xs font-semibold text-slate-800">
                        inventario_sittsa_{{ $scopeSuffix }}_{{ now()->format('Ymd') }}.{{ $exportFormat === 'xlsx' ? 'xlsx' : 'csv' }}
                    </span>
                    <span class="mt-0.5 text-[11px] font-medium text-[#4A0C12]">{{ number_format($selectedRecords) }} {{ $selectedRecords === 1 ? 'fila incluida' : 'filas incluidas' }}</span>
                </div>
            </div>
        </section>

        <div class="flex flex-col-reverse items-center justify-between gap-3 border-t border-slate-100 pt-5 sm:flex-row">
            <div class="flex flex-col items-center gap-2 sm:flex-row">
                <a href="{{ route('imports.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    Importar archivo
                </a>
                <span class="text-center text-xs text-slate-400 sm:text-left">Codificación UTF-8 con soporte para tildes y caracteres especiales.</span>
            </div>

            <button type="submit" wire:loading.attr="disabled" wire:target="export"
                class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg bg-[#4A0C12] px-6 py-2.5 text-sm font-medium text-white shadow-sm shadow-[#4A0C12]/20 transition hover:bg-[#3A090E] disabled:cursor-wait disabled:opacity-70 sm:w-auto">
                <svg wire:loading.remove wire:target="export" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                <span wire:loading.remove wire:target="export">Descargar {{ strtoupper($exportFormat === 'xlsx' ? 'XLSX' : 'CSV') }}</span>
                <span wire:loading wire:target="export">Generando archivo...</span>
            </button>
        </div>
    </form>
</div>
