<div class="mx-auto max-w-7xl space-y-6">
    <header class="flex flex-col gap-4 border-b border-zinc-200 pb-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="page-title">Consulta de unidades</h1>
            <p class="mt-1 text-sm text-zinc-500">Busca unidades, revisa su historial y administra su estado.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" wire:click="export" class="btn-secondary">Exportar XLSX</button>
            <button type="button" wire:click="exportCsv" class="btn-secondary">Exportar CSV</button>
        </div>
    </header>

    @if(session('units-message'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800" role="status">{{ session('units-message') }}</div>
    @endif

    <section class="card min-w-0 space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row">
            <div class="min-w-0 flex-1">
                <label for="units-search" class="sr-only">Buscar unidad</label>
                <input id="units-search" type="search" wire:model.live.debounce.300ms="search" class="field w-full" placeholder="Buscar por placa, código o descripción">
            </div>
            <div>
                <label for="units-status-filter" class="sr-only">Filtrar por estado</label>
                <select id="units-status-filter" wire:model.live="statusFilter" class="field w-full sm:w-40">
                    <option value="">Todos los estados</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[680px] text-left text-sm">
                <thead class="border-b bg-stone-50 text-xs uppercase text-zinc-500">
                    <tr>
                        <th class="px-3 py-3">Placa</th>
                        <th class="px-3 py-3">Código externo</th>
                        <th class="px-3 py-3">Estado</th>
                        <th class="px-3 py-3">Salidas</th>
                        <th class="px-3 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($units as $unit)
                        <tr wire:key="unit-{{ $unit->id }}">
                            <td class="px-3 py-3">
                                <div class="font-mono font-semibold text-slate-900">Placa: {{ $unit->placa }}</div>
                                <div class="text-xs text-zinc-500">{{ $unit->descripcion ?: trim(($unit->tipo ?? '').' '.($unit->modelo ?? '')) ?: '—' }}</div>
                            </td>
                            <td class="px-3 py-3 text-xs text-zinc-600">
                                <div>{{ $unit->external_id ?: '—' }}</div>
                            </td>
                            <td class="px-3 py-3">
                                <span class="rounded-full px-2 py-1 text-xs {{ $unit->estado === 'activa' ? 'bg-emerald-50 text-emerald-700' : 'bg-zinc-100 text-zinc-600' }}">{{ $statuses[$unit->estado] ?? $unit->estado }}</span>
                            </td>
                            <td class="px-3 py-3 tabular-nums">{{ $unit->salidas_count }}</td>
                            <td class="space-x-2 whitespace-nowrap px-3 py-3 text-right">
                                <button type="button" wire:click="showHistory({{ $unit->id }})" class="text-brand-700 hover:underline">Historial</button>
                                <button type="button" wire:click="edit({{ $unit->id }})" class="text-brand-700 hover:underline">Editar</button>
                                <button type="button" wire:click="toggleActive({{ $unit->id }})" wire:confirm="¿Cambiar el estado de esta unidad?" class="text-brand-700 hover:underline">{{ $unit->estado === 'activa' ? 'Desactivar' : 'Activar' }}</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-8 text-center text-zinc-500">No hay unidades registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $units->links() }}
    </section>

    @if($historyUnit)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="unit-history-title" wire:click.self="closeHistory">
            <section class="max-h-[85vh] w-full max-w-3xl overflow-hidden rounded-xl bg-white shadow-xl">
                <header class="flex items-start justify-between border-b p-5">
                    <div>
                        <h2 id="unit-history-title" class="text-lg font-semibold">Historial de salidas · {{ $historyUnit->placa }}</h2>
                        <p class="mt-1 text-sm text-zinc-500">Movimientos asociados a esta unidad.</p>
                    </div>
                    <button type="button" wire:click="closeHistory" aria-label="Cerrar historial" class="rounded-md px-2 py-1 text-zinc-500 hover:bg-zinc-100">✕</button>
                </header>
                <div class="max-h-[65vh] overflow-auto p-5">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[540px] text-left text-sm">
                            <thead class="border-b bg-stone-50 text-xs uppercase text-zinc-500">
                                <tr><th class="px-3 py-2">Fecha</th><th class="px-3 py-2">Repuesto</th><th class="px-3 py-2">Motivo</th><th class="px-3 py-2">Usuario</th></tr>
                            </thead>
                            <tbody class="divide-y">
                                @forelse($historyUnit->salidas as $movement)
                                    <tr wire:key="unit-history-{{ $movement->id }}">
                                        <td class="px-3 py-2">{{ $movement->occurred_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                        <td class="px-3 py-2">{{ $movement->item?->sigicov ?? '—' }} · {{ $movement->item?->descripcion ?? 'Repuesto eliminado' }}</td>
                                        <td class="px-3 py-2">{{ $movement->motivo ?: '—' }}</td>
                                        <td class="px-3 py-2">{{ $movement->actor_name ?: $movement->user?->name ?: '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-3 py-8 text-center text-zinc-500">Esta unidad aún no tiene salidas.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    @endif
</div>
