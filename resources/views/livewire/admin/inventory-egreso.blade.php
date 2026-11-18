<div>
<section class="w-full overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm">
    <header class="flex flex-col gap-5 border-b border-slate-100 px-6 py-6 sm:px-8 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex items-start gap-3.5">
            <div class="mt-0.5 shrink-0 rounded-lg border border-rose-100 bg-rose-50 p-2.5 text-brand-700">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M7 7h11m0 0l-4-4m4 4l-4 4M17 17H6m0 0l4 4m-4-4l4-4"/>
                </svg>
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h2 class="text-xl font-bold tracking-tight text-slate-900">Egreso de repuestos</h2>
                </div>
                <p class="mt-1 text-sm text-slate-500">Registra la entrega de un repuesto a una unidad y a la persona que lo recibe.</p>
            </div>
        </div>
        <a href="{{ route('dashboard', ['module' => 'admin.movements', 'tipo' => 'salida', 'allTime' => '1']) }}"
            class="inline-flex cursor-pointer items-center justify-center gap-1.5 self-start rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50 lg:self-auto">
            <span class="material-icons text-[17px]" aria-hidden="true">history</span>
            Ver todas las salidas
        </a>
    </header>

    <div class="border-b border-slate-100 px-6 py-5 sm:px-8">
        <div class="space-y-4 rounded-xl border border-slate-200/90 bg-slate-50 p-4">
            <div class="grid grid-cols-1 items-center gap-3.5 md:grid-cols-12">
                <div class="relative md:col-span-6">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <input type="search" wire:model.live.debounce.300ms="search" aria-label="Buscar por SIGICOV o descripción" placeholder="Buscar por SIGICOV o descripción..."
                        class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-10 pr-3.5 text-sm text-slate-800 transition placeholder:text-slate-400 focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-600/10">
                </div>
                <div class="grid grid-cols-3 gap-2 md:col-span-6">
                    <input type="search" wire:model.live.debounce.300ms="search_pasillo" aria-label="Filtrar por pasillo" placeholder="Pasillo"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-center font-mono text-sm uppercase text-slate-800 transition placeholder:font-sans placeholder:normal-case placeholder:text-slate-400 focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-600/10">
                    <input type="search" wire:model.live.debounce.300ms="search_estante" aria-label="Filtrar por estante" placeholder="Estante"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-center font-mono text-sm uppercase text-slate-800 transition placeholder:font-sans placeholder:normal-case placeholder:text-slate-400 focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-600/10">
                    <input type="search" wire:model.live.debounce.300ms="search_peldano" aria-label="Filtrar por peldaño" placeholder="Peldaño"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-center font-mono text-sm uppercase text-slate-800 transition placeholder:font-sans placeholder:normal-case placeholder:text-slate-400 focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-600/10">
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
                        @php($typeStock = (int) $stockByType->get($inventoryType->id, 0))
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
                    @php($unassignedStock = (int) $stockByType->get('unassigned', 0))
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
            @if($search || $search_pasillo || $search_estante || $search_peldano || $typeFilter)
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

    @if($success)
        <div class="mx-6 mt-5 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800 sm:mx-8" role="status">{{ $success }}</div>
    @endif
    @if($error)
        <div class="mx-6 mt-5 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800 sm:mx-8" role="alert">{{ $error }}</div>
    @endif

    @if($items->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/90 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                        <th class="whitespace-nowrap py-3.5 pl-6 pr-3">SIGICOV</th>
                        <th class="min-w-60 px-3 py-3.5">Descripción</th>
                        <th class="whitespace-nowrap px-3 py-3.5">Tipo</th>
                        <th class="whitespace-nowrap px-3 py-3.5">Ubicación física</th>
                        <th class="whitespace-nowrap py-3.5 pl-3 pr-6 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200/70 bg-white text-sm">
                    @foreach($items as $item)
                        <tr class="group transition-colors hover:bg-slate-50/90">
                            <td class="whitespace-nowrap py-3.5 pl-6 pr-3">
                                <span class="rounded border border-slate-200/60 bg-slate-100/80 px-2 py-1 font-mono text-xs font-semibold text-slate-900">{{ $item->sigicov ?: 'NO DISPONIBLE' }}</span>
                            </td>
                            <td class="min-w-60 px-3 py-3.5 font-medium leading-snug text-slate-800">{{ $item->descripcion ?: 'NO DISPONIBLE' }}</td>
                            <td class="whitespace-nowrap px-3 py-3.5">{{ $item->inventoryType?->name ?: 'Sin tipo' }}</td>
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
                            <td class="whitespace-nowrap py-3.5 pl-3 pr-6 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    @if(filled($item->sigicov))
                                        <button type="button" wire:click="openBarcodeModal({{ $item->id }})" aria-label="Ver código de barras SIGICOV {{ $item->sigicov }}" title="Ver código de barras" class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-xs font-medium text-slate-700 transition hover:border-brand-600/40 hover:bg-rose-50/50 hover:text-brand-900">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" d="M4 5v14M7 5v14m3-14v14m4-14v14m3-14v14m3-14v14"/>
                                            </svg>
                                            <span>Ver código</span>
                                        </button>
                                    @endif
                                    <button type="button" wire:click="openSalidaModal({{ $item->id }})" aria-label="Registrar entrega de {{ $item->descripcion }} a una unidad" title="Registrar entrega a una unidad" class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-600/30 focus:ring-offset-2">
                                        Registrar entrega
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @include('livewire.partials.inventory-pagination', ['items' => $items, 'paginationId' => 'inventory-egreso'])
    @else
        <div class="px-6 py-14 text-center">
            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h3 class="text-sm font-semibold text-slate-900">No se encontraron repuestos</h3>
            <p class="mx-auto mt-1 max-w-sm text-xs text-slate-500">No hay registros que coincidan con los criterios de búsqueda actuales.</p>
            @if($search || $search_pasillo || $search_estante || $search_peldano)
                <button type="button" wire:click="resetFilters" class="mt-4 inline-flex cursor-pointer items-center rounded-lg bg-rose-50 px-3.5 py-2 text-xs font-medium text-brand-700 transition hover:bg-rose-100">Restablecer filtros</button>
            @endif
        </div>
    @endif
</section>

@if($showSalidaModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="outgoing-modal-title">
        <div class="w-full max-w-md overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
            <div class="p-6">
                <div class="mb-5 flex items-start gap-4">
                    <div class="shrink-0 rounded-full border border-rose-100 bg-rose-50 p-2.5 text-brand-700">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 16l4-4m0 0l-4-4m4 4H7M3 12h4"/>
                        </svg>
                    </div>
                    <div>
                        <h3 id="outgoing-modal-title" class="text-base font-bold text-slate-900">Confirmar entrega de repuesto</h3>
                        <p class="mt-1 text-sm text-slate-500">Selecciona la unidad y a quién se entrega. Se descontará 1 unidad del inventario.</p>
                    </div>
                </div>

                @if($error)
                    <div role="alert" class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">{{ $error }}</div>
                @endif
                @include('livewire.admin.partials.salida-form', ['unidades' => $unidades, 'motivos' => $motivos])
            </div>
            <div class="flex items-center justify-end gap-2.5 border-t border-slate-100 bg-slate-50 px-6 py-3.5">
                <button type="button" wire:click="closeSalidaModal" class="cursor-pointer rounded-lg border border-slate-200 px-4 py-2 text-xs font-medium text-slate-600 transition hover:bg-slate-100">Cancelar</button>
                <button type="button" wire:click="confirmSalida" wire:loading.attr="disabled" wire:target="confirmSalida" class="cursor-pointer rounded-lg bg-brand-600 px-4 py-2 text-xs font-medium text-white shadow-sm transition hover:bg-brand-700 disabled:cursor-wait disabled:opacity-70">
                    <span wire:loading.remove wire:target="confirmSalida">Confirmar entrega</span>
                    <span wire:loading wire:target="confirmSalida">Registrando entrega...</span>
                </button>
            </div>
        </div>
    </div>
@endif
@include('livewire.admin.partials.inventory-barcode-modal', ['barcodeItem' => $barcodeItem])
</div>
