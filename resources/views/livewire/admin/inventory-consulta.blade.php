<div>
@php
    $selectedBarcodeIds = collect($selectedBarcodeItems)->map(fn ($id) => (string) $id);
@endphp
<section class="w-full overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm">
    <header class="flex flex-col gap-5 border-b border-slate-100 px-6 py-6 sm:px-8 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex items-start gap-3.5">
            <div class="mt-0.5 shrink-0 rounded-lg border border-rose-100 bg-rose-50 p-2.5 text-brand-700">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/>
                </svg>
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h2 class="text-xl font-bold tracking-tight text-slate-900">Consulta de repuestos</h2>
                </div>
                <p class="mt-1 text-sm text-slate-500">
                    Explora el inventario registrado.
                    @if(auth()->user()?->role === 'almacenista' && !$showExits)
                        Usa <span class="font-medium text-slate-700">Reportar daño o pérdida</span> para pedir al gerente una eliminación del inventario; no es una salida de repuesto.
                    @endif
                </p>
            </div>
        </div>

        <div class="flex flex-col gap-2 self-start sm:flex-row lg:self-auto">
            @if(!$showExits)
            <div class="flex items-center gap-2">
                <button type="button" x-on:click="window.openInventoryBarcodePrintTab()" wire:click="printSelectedBarcodes" wire:loading.attr="disabled" wire:target="printSelectedBarcodes" @disabled(count($selectedBarcodeItems) === 0) class="inline-flex items-center gap-2 rounded-lg border border-brand-700 bg-brand-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-800 focus:outline-none focus:ring-2 focus:ring-brand-600/30 disabled:cursor-not-allowed disabled:opacity-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 9V3h12v6M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v7H6z"/>
                    </svg>
                    Etiquetas ({{ count($selectedBarcodeItems) }}/50)
                </button>
                @if(count($selectedBarcodeItems) > 0)
                    <button type="button" wire:click="$set('selectedBarcodeItems', [])" class="rounded-lg px-2 py-2 text-xs font-medium text-slate-500 transition hover:bg-slate-100 hover:text-slate-800">Limpiar</button>
                @endif
            </div>
            @endif

            <div class="inline-flex rounded-lg shadow-sm">
                <label for="formatoExportar" class="sr-only">Formato de exportación</label>
                <select id="formatoExportar" wire:model="exportFormat" class="cursor-pointer rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3.5 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-600/20">
                    <option value="csv">CSV</option>
                    <option value="xlsx">Excel (.xlsx)</option>
                </select>
                <button type="button" wire:click="export" wire:loading.attr="disabled" wire:target="export" class="inline-flex items-center gap-2 rounded-r-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-400/40 disabled:cursor-wait disabled:opacity-70">
                    <svg wire:loading.remove wire:target="export" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    <svg wire:loading wire:target="export" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="export">{{ $showExits ? 'Exportar salidas' : 'Exportar' }}</span>
                    <span wire:loading wire:target="export">Preparando...</span>
                </button>
            </div>
        </div>
    </header>

    <nav class="flex flex-wrap gap-2 border-b border-slate-100 px-6 py-4 sm:px-8" aria-label="Sección de inventario">
        <button
            type="button"
            wire:click="showActiveInventory"
            aria-pressed="{{ $showExits ? 'false' : 'true' }}"
            class="inline-flex items-center gap-2 rounded-lg border px-3.5 py-2 text-sm font-semibold transition {{ $showExits ? 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' : 'border-brand-700 bg-brand-700 text-white' }}"
        >
            En inventario <span class="rounded-md bg-white/20 px-1.5 py-0.5 font-mono text-xs">{{ $activeItemCount }}</span>
        </button>
        <button
            type="button"
            wire:click="showUnitExits"
            aria-pressed="{{ $showExits ? 'true' : 'false' }}"
            class="inline-flex items-center gap-2 rounded-lg border px-3.5 py-2 text-sm font-semibold transition {{ $showExits ? 'border-slate-700 bg-slate-700 text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}"
        >
            Salidas a unidades <span class="rounded-md bg-black/5 px-1.5 py-0.5 font-mono text-xs">{{ $exitCount }}</span>
        </button>
    </nav>

    @if($lowStockTypes->isNotEmpty())
        <div class="mx-6 mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-950 sm:mx-8" role="status" aria-live="polite">
            <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                <h3 class="text-sm font-bold">Baja existencia por tipo</h3>
                <p class="text-xs">Tipos con menos de {{ $lowStockThreshold }} unidades activas:</p>
            </div>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach($lowStockTypes as $lowStockType)
                    <span data-low-stock-type="{{ $lowStockType['id'] }}" class="inline-flex items-center gap-1.5 rounded-md border border-amber-300 bg-white px-2.5 py-1.5 text-xs font-semibold">
                        {{ $lowStockType['name'] }}
                        <span class="rounded bg-amber-100 px-1.5 py-0.5 font-mono">{{ $lowStockType['stock'] }}</span>
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    <div class="border-b border-slate-100 px-6 py-5 sm:px-8">
        <div class="space-y-4 rounded-xl border border-slate-200/90 bg-slate-50 p-4">
            <div class="grid grid-cols-1 items-center gap-3.5 md:grid-cols-12">
                <div class="relative md:col-span-5">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <input type="search" wire:model.live.debounce.300ms="search" aria-label="Buscar por SIGICOV o descripción" placeholder="Buscar por SIGICOV o descripción..."
                        class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-10 pr-3.5 text-sm text-slate-800 transition placeholder:text-slate-400 focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-600/10">
                </div>

                <div class="grid grid-cols-3 gap-2 md:col-span-4">
                    <input type="search" wire:model.live.debounce.300ms="search_pasillo" aria-label="Filtrar por pasillo" placeholder="Pasillo"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-center font-mono text-sm uppercase text-slate-800 transition placeholder:font-sans placeholder:normal-case placeholder:text-slate-400 focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-600/10">
                    <input type="search" wire:model.live.debounce.300ms="search_estante" aria-label="Filtrar por estante" placeholder="Estante"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-center font-mono text-sm uppercase text-slate-800 transition placeholder:font-sans placeholder:normal-case placeholder:text-slate-400 focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-600/10">
                    <input type="search" wire:model.live.debounce.300ms="search_peldano" aria-label="Filtrar por peldaño" placeholder="Peldaño"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-center font-mono text-sm uppercase text-slate-800 transition placeholder:font-sans placeholder:normal-case placeholder:text-slate-400 focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-600/10">
                </div>

                <div class="flex items-center md:col-span-3 md:justify-end">
                    <label class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 md:w-auto">
                        <input type="checkbox" wire:model.live="incompleteOnly" class="rounded border-slate-300 text-brand-600 focus:ring-brand-600/20">
                        <span>Solo datos incompletos</span>
                    </label>
                </div>
            </div>

            <div class="border-t border-slate-200/70 pt-3">
                <div class="min-w-0">
                <div class="inventory-type-scroll flex min-w-0 items-center gap-2 overflow-x-auto pb-2" tabindex="0" role="group" aria-label="Totales disponibles por tipo de repuesto. Desliza horizontalmente para ver todos los tipos.">
                    <button
                        type="button"
                        wire:click="$set('typeFilter', '')"
                        aria-pressed="{{ $typeFilter === '' ? 'true' : 'false' }}"
                        aria-label="Todos los tipos: {{ $totalStock }} unidades"
                        @class([
                            'inline-flex shrink-0 cursor-pointer items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold transition',
                            'border-slate-800 bg-slate-800 text-white shadow-sm' => $typeFilter === '',
                            'border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100' => $typeFilter !== '',
                        ])
                    >
                        <span>Todos</span>
                        <span @class([
                            'rounded-md px-1.5 py-0.5 font-mono text-[11px]',
                            'bg-white/15 text-white' => $typeFilter === '',
                            'border border-slate-200 bg-white text-slate-700' => $typeFilter !== '',
                        ])>{{ $totalStock }}</span>
                    </button>
                    @foreach($inventoryTypes as $inventoryType)
                        @php
                            $typeStock = (int) $stockByType->get($inventoryType->id, 0);
                        @endphp
                        <button
                            type="button"
                            wire:click="$set('typeFilter', '{{ $inventoryType->id }}')"
                            aria-pressed="{{ (string) $typeFilter === (string) $inventoryType->id ? 'true' : 'false' }}"
                            aria-label="{{ $inventoryType->name }}: {{ $typeStock }} {{ $typeStock === 1 ? 'unidad' : 'unidades' }}"
                            @class([
                                'inline-flex shrink-0 cursor-pointer items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold transition',
                                'border-brand-600 bg-brand-600 text-white shadow-sm' => (string) $typeFilter === (string) $inventoryType->id,
                                'border-slate-200 bg-white text-slate-700 hover:border-brand-600/40 hover:bg-rose-50/50' => (string) $typeFilter !== (string) $inventoryType->id,
                            ])
                        >
                            <span>{{ $inventoryType->name }}</span>
                            <span @class([
                                'rounded-md px-1.5 py-0.5 font-mono text-[11px]',
                                'bg-white/20 text-white' => (string) $typeFilter === (string) $inventoryType->id,
                                'bg-slate-100 text-slate-700' => (string) $typeFilter !== (string) $inventoryType->id,
                            ])>{{ $typeStock }}</span>
                        </button>
                    @endforeach
                    @php
                        $unassignedStock = (int) $stockByType->get('unassigned', 0);
                    @endphp
                    <button
                        type="button"
                        wire:click="$set('typeFilter', 'unassigned')"
                        aria-pressed="{{ $typeFilter === 'unassigned' ? 'true' : 'false' }}"
                        aria-label="Sin tipo asignado: {{ $unassignedStock }} {{ $unassignedStock === 1 ? 'unidad' : 'unidades' }}"
                        @class([
                            'inline-flex shrink-0 cursor-pointer items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold transition',
                            'border-brand-600 bg-brand-600 text-white shadow-sm' => $typeFilter === 'unassigned',
                            'border-slate-200 bg-white text-slate-700 hover:border-brand-600/40 hover:bg-rose-50/50' => $typeFilter !== 'unassigned',
                        ])
                    >
                        <span>Sin tipo</span>
                        <span @class([
                            'rounded-md px-1.5 py-0.5 font-mono text-[11px]',
                            'bg-white/20 text-white' => $typeFilter === 'unassigned',
                            'bg-slate-100 text-slate-700' => $typeFilter !== 'unassigned',
                        ])>{{ $unassignedStock }}</span>
                    </button>
                </div>
                <p class="mt-0.5 text-right text-[11px] text-slate-400">Desliza horizontalmente para ver todos los tipos</p>
                </div>
            </div>

            @if($search || $search_descripcion || $search_pasillo || $search_estante || $search_peldano || $typeFilter || $incompleteOnly)
                <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200/70 pt-2 text-xs text-slate-500">
                    <button type="button" wire:click="resetFilters" class="inline-flex cursor-pointer items-center gap-1 font-medium text-brand-700 hover:text-brand-800">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        Limpiar filtros activos
                    </button>
                </div>
            @endif
        </div>
    </div>

    @if(session()->has('success'))
        <div class="mx-6 mt-5 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800 sm:mx-8">{{ session('success') }}</div>
    @endif
    @if(session()->has('info'))
        <div class="mx-6 mt-5 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 sm:mx-8">{{ session('info') }}</div>
    @endif
    @if($deletionFeedback)
        <div @class([
            'mx-6 mt-5 rounded-lg border p-3 text-sm sm:mx-8',
            'border-emerald-200 bg-emerald-50 text-emerald-800' => $deletionFeedbackType === 'success',
            'border-amber-200 bg-amber-50 text-amber-800' => $deletionFeedbackType === 'info',
        ]) role="status">{{ $deletionFeedback }}</div>
    @endif
    @error('selectedBarcodeItems')
        <div class="mx-6 mt-5 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 sm:mx-8" role="alert">{{ $message }}</div>
    @enderror

    @if($showExits && !$exitsAvailable)
        <div class="m-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 sm:m-8" role="status">
            El historial de salidas a unidades no está disponible en este entorno.
        </div>
    @elseif($showExits && $items->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/90 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                        <th class="whitespace-nowrap py-3.5 pl-6 pr-3">Fecha y hora</th>
                        <th class="whitespace-nowrap px-3 py-3.5">SIGICOV</th>
                        <th class="min-w-60 px-3 py-3.5">Descripción</th>
                        <th class="whitespace-nowrap px-3 py-3.5">Unidad</th>
                        <th class="whitespace-nowrap px-3 py-3.5">Entregado a</th>
                        <th class="whitespace-nowrap px-3 py-3.5">Unidades</th>
                        <th class="whitespace-nowrap px-3 py-3.5">Responsable</th>
                        <th class="min-w-48 py-3.5 pl-3 pr-6">Motivo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200/70 bg-white text-sm">
                    @foreach($items as $movement)
                        <tr class="transition-colors hover:bg-slate-50/90">
                            <td class="whitespace-nowrap py-3.5 pl-6 pr-3 font-mono text-xs text-slate-500">
                                {{ $movement->occurred_at?->format('d/m/Y H:i') ?? 'NO DISPONIBLE' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-3.5">
                                <span class="rounded border border-slate-200/60 bg-slate-100/80 px-2 py-1 font-mono text-xs font-semibold text-slate-900">{{ $movement->item?->sigicov ?: 'NO DISPONIBLE' }}</span>
                            </td>
                            <td class="min-w-60 px-3 py-3.5 font-medium leading-snug text-slate-800">{{ $movement->item?->descripcion ?: 'NO DISPONIBLE' }}</td>
                            <td class="whitespace-nowrap px-3 py-3.5">
                                <span class="block font-mono text-xs font-semibold text-slate-800">{{ $movement->vehiculo_placa }}</span>
                                <span class="mt-0.5 block text-xs text-slate-500">{{ $movement->vehiculo_descripcion ?: 'Sin descripción' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3.5 text-slate-700">{{ $movement->entregado_a ?: 'NO DISPONIBLE' }}</td>
                            <td class="whitespace-nowrap px-3 py-3.5 font-mono font-semibold text-rose-700">{{ abs((int) $movement->delta) }}</td>
                            <td class="whitespace-nowrap px-3 py-3.5 text-slate-600">{{ $movement->actor_name ?: 'NO DISPONIBLE' }}</td>
                            <td class="min-w-48 py-3.5 pl-3 pr-6 text-slate-600">{{ $movement->motivo ?: 'NO DISPONIBLE' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @include('livewire.partials.inventory-pagination', ['items' => $items, 'paginationId' => 'inventory-unit-exits'])
    @elseif($showExits)
        <div class="px-6 py-14 text-center">
            <h3 class="text-sm font-semibold text-slate-900">No hay salidas a unidades</h3>
            <p class="mx-auto mt-1 max-w-sm text-xs text-slate-500">Las entregas registradas a una unidad aparecerán aquí.</p>
        </div>
    @elseif($items->isNotEmpty())
        @php
            $barcodeSelectableIds = $items->getCollection()
                ->filter(fn ($item) => filled($item->sigicov))
                ->pluck('id')
                ->map(fn ($id) => (string) $id);
            $allCurrentPageSelected = $barcodeSelectableIds->isNotEmpty()
                && $barcodeSelectableIds->diff($selectedBarcodeIds)->isEmpty();
        @endphp
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/90 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                        <th class="whitespace-nowrap py-3.5 pl-6 pr-2">
                            <input type="checkbox" wire:click="toggleCurrentPageSelection" @checked($allCurrentPageSelected) @disabled($barcodeSelectableIds->isEmpty()) aria-label="Seleccionar todos los repuestos con SIGICOV de esta página" class="rounded border-slate-300 text-brand-600 focus:ring-brand-600/20">
                        </th>
                        <th class="whitespace-nowrap py-3.5 pl-2 pr-3">SIGICOV</th>
                        <th class="min-w-60 px-3 py-3.5">Descripción</th>
                        <th class="whitespace-nowrap px-3 py-3.5">Tipo</th>
                        <th class="whitespace-nowrap px-3 py-3.5">Ubicación física</th>
                        <th class="whitespace-nowrap px-3 py-3.5">Auditoría (fecha / usuario)</th>
                        @if($isAdministrator)
                            <th class="whitespace-nowrap px-3 py-3.5">Disponible / total mes</th>
                            <th class="whitespace-nowrap px-3 py-3.5">Origen / lote</th>
                        @endif
                        <th class="whitespace-nowrap px-3 py-3.5">Resp. accesorios</th>
                        <th class="whitespace-nowrap px-3 py-3.5" title="Fecha y hora en que el artículo se registró en el sistema">Fecha de registro</th>
                        <th class="whitespace-nowrap py-3.5 pl-3 pr-6 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200/70 bg-white text-sm">
                    @foreach($items as $item)
                        <tr class="group transition-colors hover:bg-slate-50/90">
                            <td class="whitespace-nowrap py-3.5 pl-6 pr-2">
                                @if(filled($item->sigicov))
                                    <input type="checkbox" wire:model="selectedBarcodeItems" value="{{ $item->id }}" @checked($selectedBarcodeIds->contains((string) $item->id)) @disabled(count($selectedBarcodeItems) >= 50 && ! $selectedBarcodeIds->contains((string) $item->id)) aria-label="Seleccionar etiqueta SIGICOV {{ $item->sigicov }}" class="rounded border-slate-300 text-brand-600 focus:ring-brand-600/20">
                                @endif
                            </td>
                            <td class="whitespace-nowrap py-3.5 pl-2 pr-3">
                                <span class="rounded border border-slate-200/60 bg-slate-100/80 px-2 py-1 font-mono text-xs font-semibold text-slate-900">{{ $item->sigicov ?: 'NO DISPONIBLE' }}</span>
                            </td>
                            <td class="min-w-60 px-3 py-3.5 font-medium leading-snug text-slate-800">{{ $item->descripcion ?: 'NO DISPONIBLE' }}</td>
                            <td class="min-w-40 px-3 py-3.5">
                                @if($isAdministrator)
                                    <select aria-label="Tipo para {{ $item->sigicov }}" wire:change="assignType({{ $item->id }}, $event.target.value)" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-700 focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-600/20">
                                        <option value="" @selected($item->inventory_type_id === null)>Sin tipo asignado</option>
                                        @foreach($activeInventoryTypes as $inventoryType)
                                            <option value="{{ $inventoryType->id }}" @selected((int) $item->inventory_type_id === $inventoryType->id)>{{ $inventoryType->name }}</option>
                                        @endforeach
                                        @if($item->inventoryType && ! $item->inventoryType->is_active)
                                            <option value="{{ $item->inventory_type_id }}" selected disabled>{{ $item->inventoryType->name }} (inactivo)</option>
                                        @endif
                                    </select>
                                    @error("inventoryTypes.{$item->id}") <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                                @else
                                    {{ $item->inventoryType?->name ?: 'Sin tipo' }}
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-3.5">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    @foreach(['P' => $item->pasillo, 'E' => $item->estante, 'Pel' => $item->peldaño] as $label => $value)
                                        @if(!$value || mb_strtoupper($value) === 'NO DISPONIBLE')
                                            <span class="inline-flex items-center gap-1 rounded border border-amber-200/70 bg-amber-50/80 px-2 py-0.5 text-[11px] font-medium text-amber-700/90" title="{{ $label }}: No disponible">
                                                <span class="font-normal text-amber-500/80">{{ $label }}:</span> N/D
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded border border-slate-200/80 bg-slate-100 px-2 py-0.5 font-mono text-[11px] font-medium text-slate-700">
                                                <span class="font-sans font-normal text-slate-400">{{ $label }}:</span> {{ $value }}
                                            </span>
                                        @endif
                                    @endforeach
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3.5">
                                @if($item->fecha || $item->realizado_por)
                                    <div class="text-xs">
                                        <span class="block font-medium text-slate-700">{{ $item->realizado_por ?: 'NO DISPONIBLE' }}</span>
                                        <span class="font-mono text-[11px] text-slate-400">{{ $item->fecha ?: 'NO DISPONIBLE' }}</span>
                                    </div>
                                @else
                                    <span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-400">Sin datos de auditoría</span>
                                @endif
                            </td>
                            @if($isAdministrator)
                                <td class="whitespace-nowrap px-3 py-3.5">
                                    <span class="font-semibold text-slate-700">{{ $item->inventario ?? 'NO DISPONIBLE' }}</span>
                                    <span class="block text-xs text-slate-400">Mes: {{ $item->monthly_total ?? 0 }}</span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3.5 text-xs">
                                    <span class="block text-slate-600">
                                        @if($item->origen === 'inventario_inicial' && $item->importBatch)
                                            Migrado · corte {{ $item->importBatch->cutoff_date?->format('d/m/Y') ?? 'sin fecha' }}
                                        @elseif($item->origen === 'ingreso_manual')
                                            Sistema
                                        @else
                                            Sin clasificar
                                        @endif
                                    </span>
                                    @if($item->import_batch_id)
                                        <a class="font-mono text-brand-700 underline" href="{{ route('imports.show', $item->import_batch_id) }}">Lote {{ $item->import_batch_id }}</a>
                                    @elseif($item->lote_importacion)
                                        <span class="font-mono text-slate-400">Lote {{ $item->lote_importacion }}</span>
                                    @endif
                                </td>
                            @endif
                            <td class="whitespace-nowrap px-3 py-3.5 font-mono text-xs text-slate-600">{{ $item->resp_accesorios ?: 'NO DISPONIBLE' }}</td>
                            <td class="whitespace-nowrap px-3 py-3.5 font-mono text-xs text-slate-400" title="Fecha y hora en que el artículo se registró en el sistema">{{ $item->created_at?->format('d/m/Y H:i') ?? 'NO DISPONIBLE' }}</td>
                            <td class="whitespace-nowrap py-3.5 pl-3 pr-6 text-right">
                                <div class="flex min-w-40 flex-col items-end gap-2">
                                    @if(filled($item->sigicov))
                                        <button type="button" wire:click="openBarcodeModal({{ $item->id }})" aria-label="Ver código de barras SIGICOV {{ $item->sigicov }}" class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:border-brand-600/40 hover:bg-rose-50/50 hover:text-brand-900">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" d="M4 5v14M7 5v14m3-14v14m4-14v14m3-14v14m3-14v14"/>
                                            </svg>
                                            Ver código de barras
                                        </button>
                                    @endif
                                    @if(auth()->user()?->role === 'almacenista' && (int) $item->inventario > 0)
                                        <button type="button" data-action="report-inventory-deletion" wire:click="openDeletionModal({{ $item->id }})" class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-amber-200/80 bg-amber-50 px-2.5 py-1.5 text-xs font-medium text-amber-800 transition-colors hover:border-amber-300 hover:bg-amber-100">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                            </svg>
                                            Reportar daño o pérdida
                                        </button>
                                    @elseif(auth()->user()?->role === 'almacenista')
                                        <span class="text-xs text-slate-400" title="No hay existencias disponibles para solicitar una eliminación">Sin existencias</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @include('livewire.partials.inventory-pagination', ['items' => $items, 'paginationId' => 'inventory-consulta'])
    @else
        <div class="px-6 py-14 text-center">
            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h3 class="text-sm font-semibold text-slate-900">No se encontraron repuestos</h3>
            <p class="mx-auto mt-1 max-w-sm text-xs text-slate-500">No hay registros que coincidan con los criterios de búsqueda actuales.</p>
            @if($search || $search_descripcion || $search_pasillo || $search_estante || $search_peldano || $incompleteOnly)
                <button type="button" wire:click="resetFilters" class="mt-4 inline-flex cursor-pointer items-center rounded-lg bg-rose-50 px-3.5 py-2 text-xs font-medium text-brand-700 transition hover:bg-rose-100">Restablecer filtros</button>
            @endif
        </div>
    @endif
</section>

@if($showDeletionModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="deletion-modal-title">
        <div class="w-full max-w-md overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div class="shrink-0 rounded-full border border-rose-100 bg-rose-50 p-2.5 text-brand-700">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 id="deletion-modal-title" class="text-base font-bold text-slate-900">Reportar daño o pérdida</h3>
                        <p class="mt-1 text-sm text-slate-500">Este reporte corresponde a una unidad. El gerente lo revisará; no es una salida ni descuenta existencias hasta su aprobación.</p>
                        <div class="mt-3 rounded-lg border border-slate-200/80 bg-slate-50 p-3 text-xs">
                            <span class="block font-mono font-semibold text-brand-700">SIGICOV #{{ $deletionItemSigicov }}</span>
                            <span class="mt-1 block text-slate-600">Existencias actuales: <strong>{{ $deletionItemInventory }}</strong></span>
                        </div>
                    </div>
                </div>

                <div class="mt-5 space-y-4">
                    <div>
                        <label for="deletionReason" class="mb-1.5 block text-xs font-medium text-slate-700">Describe el daño, pérdida u otro motivo</label>
                        <textarea id="deletionReason" wire:model="deletionReason" rows="3" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-600/10"></textarea>
                        @error('deletionReason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-end gap-2.5 border-t border-slate-100 bg-slate-50 px-6 py-3.5">
                <button type="button" wire:click="$set('showDeletionModal', false)" class="cursor-pointer rounded-lg border border-slate-200 px-4 py-2 text-xs font-medium text-slate-600 transition hover:bg-slate-100">Cancelar</button>
                <button id="btn-submit-deletion" type="button" wire:click="submitDeletionRequest" wire:loading.attr="disabled" wire:target="submitDeletionRequest" class="cursor-pointer rounded-lg bg-brand-600 px-4 py-2 text-xs font-medium text-white shadow-sm transition hover:bg-brand-700 disabled:cursor-wait disabled:opacity-70">
                    <span wire:loading.remove wire:target="submitDeletionRequest">Enviar reporte al gerente</span>
                    <span wire:loading wire:target="submitDeletionRequest">Enviando...</span>
                </button>
            </div>
        </div>
    </div>
@endif
@include('livewire.admin.partials.inventory-barcode-modal', ['barcodeItem' => $barcodeItem])
</div>
