<section class="w-full overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm">
    <header class="flex flex-col gap-5 border-b border-slate-100 px-6 py-6 sm:px-8 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex items-start gap-3.5">
            <div class="mt-0.5 shrink-0 rounded-lg border border-[#4A0C12]/10 bg-[#4A0C12]/5 p-2.5 text-[#4A0C12]">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                </svg>
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-xl font-bold tracking-tight text-slate-900">Trazabilidad y movimientos de inventario</h1>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-[#4A0C12]/15 bg-[#4A0C12]/5 px-2.5 py-0.5 text-xs font-medium text-[#4A0C12]">
                        <span class="h-1.5 w-1.5 rounded-full bg-[#4A0C12]"></span>
                        Auditoría de inventario
                    </span>
                </div>
                <p class="mt-1 text-sm text-slate-500">
                    Bitácora de ingresos, salidas y ajustes por código
                    <span class="font-mono text-xs font-semibold text-slate-700">SIGICOV</span>.
                </p>
            </div>
        </div>

        @if($available)
            <button type="button" wire:click="exportCsv" wire:loading.attr="disabled" wire:target="exportCsv"
                    class="inline-flex cursor-pointer items-center gap-1.5 self-start rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-wait disabled:opacity-60 lg:self-auto">
                <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Exportar CSV
            </button>
        @endif
    </header>

    @unless($available)
        <div class="m-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 sm:m-8" role="status">
            {{ $unavailableMessage }}
        </div>
    @else
        <div class="space-y-4 border-b border-slate-200/80 bg-slate-50/80 px-6 py-5 sm:px-8">
            <div class="grid grid-cols-1 items-end gap-3 md:grid-cols-2 xl:grid-cols-5">
                <div class="relative xl:col-span-2">
                    <label for="movement-search" class="mb-1.5 block text-xs font-medium text-slate-600">Buscar repuesto</label>
                    <div class="pointer-events-none absolute bottom-0 left-0 flex h-9 items-center pl-3 text-slate-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <input id="movement-search" type="search" wire:model.live.debounce.400ms="search"
                           placeholder="SIGICOV o descripción..."
                           class="h-9 w-full rounded-lg border border-slate-300 bg-white pl-9 pr-3.5 text-xs text-slate-800 transition placeholder:text-slate-400 focus:border-[#4A0C12] focus:outline-none focus:ring-2 focus:ring-[#4A0C12]/15">
                </div>
                <div>
                    <label for="movement-from" class="mb-1.5 block text-xs font-medium text-slate-600">Desde</label>
                    <input id="movement-from" type="date" wire:model.live="from"
                           @disabled($allTime)
                           class="h-9 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs text-slate-700 focus:border-[#4A0C12] focus:outline-none focus:ring-2 focus:ring-[#4A0C12]/15 disabled:bg-slate-100 disabled:text-slate-400">
                </div>
                <div>
                    <label for="movement-to" class="mb-1.5 block text-xs font-medium text-slate-600">Hasta</label>
                    <input id="movement-to" type="date" wire:model.live="to"
                           @disabled($allTime)
                           class="h-9 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs text-slate-700 focus:border-[#4A0C12] focus:outline-none focus:ring-2 focus:ring-[#4A0C12]/15 disabled:bg-slate-100 disabled:text-slate-400">
                </div>
                <div class="pb-2">
                    <label for="movement-all-time" class="inline-flex cursor-pointer items-center gap-2 text-xs font-medium text-slate-600">
                        <input id="movement-all-time" type="checkbox" wire:model.live="allTime" class="rounded border-slate-300 text-[#4A0C12] focus:ring-[#4A0C12]/20">
                        Todo el historial
                    </label>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-1.5 border-t border-slate-200/70 pt-3">
                <button type="button" wire:click="$set('tipo', '')"
                        @class([
                            'inline-flex cursor-pointer items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition',
                            'border-[#4A0C12] bg-[#4A0C12] text-white shadow-sm' => $tipo === '',
                            'border-slate-200 bg-white text-slate-600 hover:bg-slate-100' => $tipo !== '',
                        ])>
                    Todos <span class="font-mono opacity-80">{{ $eventCounts->sum() }}</span>
                </button>
                @foreach($tipos as $value => $label)
                    @php
                        $dotClass = match($value) {
                            'entrada' => 'bg-emerald-500',
                            'salida' => 'bg-rose-500',
                            'ajuste' => 'bg-amber-500',
                            default => 'bg-slate-400',
                        };
                    @endphp
                    <button type="button" wire:click="$set('tipo', '{{ $value }}')"
                            @class([
                                'inline-flex cursor-pointer items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition',
                                'border-[#4A0C12] bg-[#4A0C12] text-white shadow-sm' => $tipo === $value,
                                'border-slate-200 bg-white text-slate-600 hover:bg-slate-100' => $tipo !== $value,
                            ])>
                        <span class="h-2 w-2 rounded-full {{ $dotClass }}"></span>
                        {{ $label }}
                        <span class="font-mono opacity-75">{{ $eventCounts[$value] ?? 0 }}</span>
                    </button>
                @endforeach
                <button type="button" wire:click="clearFilters" class="ml-auto inline-flex cursor-pointer items-center gap-1 font-medium text-[#4A0C12] hover:underline">
                    Limpiar filtros
                </button>
                <span wire:loading wire:target="from,to,tipo,search,clearFilters" class="text-xs text-slate-500" role="status">Filtrando...</span>
            </div>

            <div class="flex flex-col justify-between gap-2 border-t border-slate-200/70 pt-3 text-[11px] text-slate-500 sm:flex-row sm:items-center">
                <span>Mostrando <strong class="font-mono text-slate-700">{{ number_format($rows->total(), 0, ',', '.') }}</strong> movimientos registrados</span>
                <span>La cantidad acompaña al evento; el estado refleja las existencias posteriores y la ubicación física es la actual.</span>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 border-b border-slate-100 px-6 py-5 sm:grid-cols-3 sm:px-8">
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-xs text-slate-500">Entradas</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-700">{{ number_format((int) $totals->entradas, 0, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-xs text-slate-500">Salidas</p>
                <p class="mt-1 text-2xl font-semibold text-rose-700">{{ number_format((int) $totals->salidas, 0, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-xs text-slate-500">Ajustes</p>
                <p class="mt-1 text-2xl font-semibold text-amber-700">{{ number_format((int) $totals->ajustes, 0, ',', '.') }}</p>
            </div>
        </div>

        @if($byVehicle->isNotEmpty())
            <div class="border-b border-slate-100 px-6 py-5 sm:px-8">
                <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white p-4">
                    <h2 class="mb-2 text-sm font-semibold text-slate-900">Salidas por vehículo (top 10)</h2>
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th class="py-1 pr-3">Placa</th>
                                <th class="py-1 pr-3">Vehículo</th>
                                <th class="py-1 pr-3 text-right">Unidades</th>
                                <th class="py-1 text-right">Salidas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($byVehicle as $row)
                                <tr>
                                    <td class="py-1 pr-3 font-medium">{{ $row->placa }}</td>
                                    <td class="py-1 pr-3 text-slate-600">{{ $row->descripcion }}</td>
                                    <td class="py-1 pr-3 text-right">{{ number_format((int) $row->total, 0, ',', '.') }}</td>
                                    <td class="py-1 text-right">{{ $row->veces }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/70 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                        <th scope="col" class="whitespace-nowrap py-3.5 pl-6 pr-3">Fecha y hora</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-3.5">Tipo de evento</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-3.5">SIGICOV</th>
                        <th scope="col" class="min-w-52 px-3 py-3.5">Repuesto / accesorio</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-3.5">Estado resultante</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-3.5">Destino / ubicación actual</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-3.5">Entregado a</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-3.5">Responsable</th>
                        <th scope="col" class="min-w-52 py-3.5 pl-3 pr-6">Observación / motivo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200/70 bg-white text-sm" wire:loading.class="opacity-60" wire:target="from,to,tipo,search,clearFilters">
                    @forelse($rows as $movement)
                        @php
                            $movementStyle = match($movement->tipo) {
                                'entrada' => 'border-emerald-200/80 bg-emerald-50 text-emerald-800',
                                'salida' => 'border-rose-200/80 bg-rose-50 text-rose-800',
                                'ajuste' => 'border-amber-200/80 bg-amber-50 text-amber-800',
                                default => 'border-slate-200 bg-slate-100 text-slate-700',
                            };
                            $location = collect([
                                $movement->item?->pasillo ? 'P '.$movement->item->pasillo : null,
                                $movement->item?->estante ? 'E '.$movement->item->estante : null,
                                $movement->item?->peldaño ? 'Pel '.$movement->item->peldaño : null,
                            ])->filter()->join(' · ');
                        @endphp
                        <tr class="transition-colors hover:bg-slate-50/90">
                            <td class="whitespace-nowrap py-3.5 pl-6 pr-3 font-mono text-xs text-slate-500">
                                {{ $movement->occurred_at->format('d/m/Y') }}
                                <span class="block text-slate-400">{{ $movement->occurred_at->format('H:i') }}</span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3.5">
                                <span class="inline-flex items-center rounded-md border px-2.5 py-1 text-xs font-medium {{ $movementStyle }}">{{ $movement->tipoLabel() }}</span>
                                <span class="mt-1 block font-mono text-xs font-medium {{ $movement->delta < 0 ? 'text-rose-700' : 'text-slate-600' }}">
                                    {{ $movement->delta > 0 ? '+' : '' }}{{ number_format($movement->delta, 0, ',', '.') }} unidades
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3.5">
                                <span class="rounded border border-slate-200/70 bg-slate-100/80 px-2 py-1 font-mono text-xs font-semibold text-slate-900">{{ $movement->item?->sigicov ?? '—' }}</span>
                            </td>
                            <td class="px-3 py-3.5 font-medium leading-snug text-slate-800">{{ $movement->item?->descripcion ?? 'Repuesto no disponible' }}</td>
                            <td class="whitespace-nowrap px-3 py-3.5">
                                @if($movement->stock_despues > 0)
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200/70 bg-emerald-50/70 px-2.5 py-0.5 text-xs font-medium text-emerald-800">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        Con existencias
                                    </span>
                                    <span class="mt-1 block text-xs text-slate-500">{{ number_format($movement->stock_despues, 0, ',', '.') }} en almacén</span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                        <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                        Sin existencias
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-3.5">
                                @if($movement->vehiculo_placa)
                                    <span class="block font-mono text-xs font-semibold text-slate-800">{{ $movement->vehiculo_placa }}</span>
                                    @if($movement->vehiculo_descripcion)
                                        <span class="mt-0.5 block text-xs text-slate-500">{{ $movement->vehiculo_descripcion }}</span>
                                    @endif
                                @elseif($movement->tipo === 'salida')
                                    <span class="text-xs text-slate-500">Destino no registrado</span>
                                @else
                                    <span class="block text-xs font-medium text-slate-700">Almacén</span>
                                    @if($location !== '')
                                        <span class="mt-0.5 block text-xs text-slate-500">Ubicación actual: {{ $location }}</span>
                                    @endif
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-3.5 text-xs text-slate-700">{{ $movement->entregado_a ?: '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3.5 text-xs font-medium text-slate-700">{{ $movement->actor_name ?: '—' }}</td>
                            <td class="py-3.5 pl-3 pr-6 text-xs text-slate-500">{{ $movement->motivo ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center">
                                <p class="text-sm font-semibold text-slate-900">No hay movimientos que coincidan con el filtro</p>
                                <p class="mt-1 text-xs text-slate-500">Prueba con otro tipo de evento, rango de fechas o búsqueda.</p>
                                <button type="button" wire:click="clearFilters" class="mt-4 rounded-lg bg-[#4A0C12]/5 px-4 py-2 text-xs font-medium text-[#4A0C12] transition hover:bg-[#4A0C12]/10">
                                    Ver movimientos del período predeterminado
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('livewire.partials.inventory-pagination', [
            'items' => $rows,
            'paginationId' => 'inventory-movements',
            'paginationLabel' => 'Paginación de movimientos',
        ])
    @endunless
</section>
