<div
    class="max-w-6xl space-y-5"
    @if(in_array($batch->status, ['analyzing', 'applying', 'reverting'], true)) wire:poll.5s @endif
>
    @php
        $batchStatus = [
            'ready' => ['Listo para aplicar', 'bg-emerald-100 text-emerald-800'],
            'analyzing' => ['Analizando', 'bg-amber-100 text-amber-900'],
            'applying' => ['Aplicando', 'bg-blue-100 text-blue-800'],
            'reverting' => ['Revirtiendo', 'bg-amber-100 text-amber-900'],
            'completed' => ['Aplicado', 'bg-blue-100 text-blue-800'],
            'reverted' => ['Revertido', 'bg-stone-200 text-stone-800'],
            'failed' => ['Con errores', 'bg-red-100 text-red-800'],
        ][strtolower($batch->status)] ?? [str_replace('_', ' ', ucfirst($batch->status)), 'bg-stone-200 text-stone-800'];
    @endphp

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('imports.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-brand-600">
                <span class="material-icons text-[16px]" aria-hidden="true">arrow_back</span>
                Volver al historial
            </a>
            <h1 class="page-title mt-1">Lote de importación</h1>
            <p class="text-sm text-gray-500">
                {{ $batch->file_name }} · Hoja: {{ $batch->sheet_name ?: 'En detección' }} ·
                Corte: {{ $batch->cutoff_date?->format('d/m/Y') ?: 'Sin especificar' }}
                <span class="ml-2 text-xs text-gray-400" title="{{ $batch->id }}">#{{ \Illuminate\Support\Str::limit($batch->id, 8, '') }}</span>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <span class="badge {{ $batchStatus[1] }}">{{ $batchStatus[0] }}</span>
            @if(in_array($batch->status, ['ready', 'completed', 'reverted'], true))
                <a href="{{ route('imports.omitted', $batch) }}" class="btn-ghost inline-flex h-10 items-center gap-1">
                    <span class="material-icons text-[18px]" aria-hidden="true">download</span>
                    Omitidos (CSV)
                </a>
            @endif
            @if($batch->status === 'ready')
                <button
                    type="button"
                    class="btn-primary"
                    @disabled((int) $batch->new_rows < 1)
                    x-on:click.prevent="window.Swal.fire({
                        icon: 'question',
                        title: '¿Aplicar importación?',
                        text: 'Se insertarán {{ number_format((int) $batch->new_rows) }} registros nuevos. {{ number_format((int) $untypedNewRows) }} quedarán sin tipo confirmado. Los {{ number_format((int) ($batch->rows_read - $batch->new_rows)) }} restantes no se modificarán.',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, aplicar',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#B3122B'
                    }).then(result => { if (result.isConfirmed) { $wire.apply() } })"
                >
                    Aplicar importación
                </button>
            @endif
            @if($batch->status === 'failed' && $batch->approved_at !== null)
                <button type="button" wire:click="retry" wire:confirm="El lote falló durante la aplicación. Se conservarán las filas analizadas y podrás confirmar Aplicar de nuevo." class="btn-secondary">Reintentar</button>
            @endif
            @if($batch->status === 'completed')
                <button
                    type="button"
                    wire:click="revert"
                    wire:loading.attr="disabled"
                    wire:target="revert"
                    wire:confirm="Se verificará un respaldo antes de revertir. Si no puede validarse, el inventario no se modificará. ¿Continuar? No se puede revertir si existen solicitudes relacionadas."
                    class="btn-danger"
                >
                    <span wire:loading.remove wire:target="revert">Respaldar y revertir lote</span>
                    <span wire:loading wire:target="revert">Procesando reversión…</span>
                </button>
            @endif
        </div>
    </div>

    @if(session('message'))
        <div class="rounded-lg bg-emerald-100 p-3 text-emerald-900">{{ session('message') }}</div>
    @endif
    @if($actionError || $batch->error)
        <div class="rounded-lg bg-red-100 p-3 text-red-900">{{ $actionError ?: $batch->error }}</div>
    @endif
    @if($batch->status === 'reverting')
        <div class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-blue-900" role="status">
            La reversión está en proceso. Esta página se actualizará automáticamente al finalizar.
        </div>
    @endif
    @if(in_array($batch->status, ['analyzing', 'applying', 'reverting'], true) && $batch->updated_at?->lt(now()->subMinutes(2)))
        <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-amber-950" role="alert">El proceso está tardando más de lo esperado. Contacta al administrador para verificar su estado.</div>
    @endif

    <section class="card">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ([
                ['Filas leídas', $batch->rows_read, 'text-gray-900'],
                ['Nuevas', $batch->new_rows, 'text-emerald-700'],
                ['Ya existentes', $batch->existing_rows, 'text-amber-700'],
                ['Repetidas', $batch->duplicate_rows, 'text-amber-700'],
                ['Sin SIGICOV / inválidas', $batch->invalid_rows, 'text-red-700'],
                ['Nuevas sin tipo confirmado', $untypedNewRows, 'text-amber-700'],
                ['Insertadas', $batch->imported_rows, 'text-emerald-700'],
                ['Registros antes', $batch->records_before, 'text-gray-500'],
                ['Registros después', $batch->records_after, 'text-gray-500'],
            ] as [$label, $value, $tone])
                <div class="rounded-lg border border-stone-200 bg-stone-50 px-4 py-3">
                    <div class="text-xs text-gray-500">{{ $label }}</div>
                    <div class="text-2xl font-bold tabular-nums {{ $tone }}">{{ is_null($value) ? '—' : number_format((int) $value) }}</div>
                </div>
            @endforeach
        </div>

        @if($batch->status === 'ready')
            <div class="mt-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-900">
                <span class="material-icons mt-0.5 text-[18px]" aria-hidden="true">info</span>
                <p><strong>Vista previa.</strong> El inventario todavía no se ha modificado. Cada SIGICOV válido se importará como una unidad; las columnas «Inventario» y «Total» del archivo se ignoran. Las sugerencias de tipo no se aplican automáticamente: confirma cada tipo en su selector. Las filas sin tipo confirmado se importan sin clasificar. Los SIGICOV existentes, incluidos los eliminados, no se sobrescriben.</p>
            </div>
        @endif
    </section>

    @if(in_array($batch->status, ['ready', 'completed', 'reverted'], true))
        <section class="card">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap gap-2">
                    @foreach ([
                        ['all', 'Todos', $rowStatusCounts['all']],
                        ['new', 'Nuevas', $rowStatusCounts['new']],
                        ['existente', 'Existentes', $rowStatusCounts['existente']],
                        ['repetido', 'Repetidas', $rowStatusCounts['repetido']],
                        ['invalid', 'Inválidas', $rowStatusCounts['invalid']],
                        ['importado', 'Importadas', $rowStatusCounts['importado']],
                        ['incomplete_location', 'Ubicación incompleta', $rowStatusCounts['incomplete_location']],
                    ] as [$key, $label, $count])
                        <button
                            type="button"
                            wire:click="$set('rowStatus', '{{ $key }}')"
                            @class([
                                'h-8 rounded-full border px-3 text-sm font-medium',
                                'border-brand-900 bg-brand-900 text-white' => $rowStatus === $key,
                                'border-stone-300 bg-white text-gray-700 hover:bg-stone-50' => $rowStatus !== $key,
                            ])
                        >
                            {{ $label }} <span class="opacity-70">({{ number_format($count) }})</span>
                        </button>
                    @endforeach
                </div>
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Buscar SIGICOV o descripción…"
                    aria-label="Buscar SIGICOV, descripción o pasillo"
                    class="field w-full sm:w-64"
                >
            </div>

            @php
                $rowStatusLabels = [
                    'new' => ['Nueva', 'bg-emerald-100 text-emerald-800'],
                    'existente' => ['Existente', 'bg-amber-100 text-amber-900'],
                    'repetido' => ['Repetida', 'bg-amber-100 text-amber-900'],
                    'sin_sigicov' => ['Sin SIGICOV', 'bg-red-100 text-red-800'],
                    'invalida' => ['Inválida', 'bg-red-100 text-red-800'],
                    'importado' => ['Importada', 'bg-blue-100 text-blue-800'],
                ];
            @endphp
            <div class="max-h-[640px] overflow-auto rounded-lg border border-stone-200">
                <table class="w-full min-w-[900px] text-sm">
                    <thead class="sticky top-0 z-10 bg-stone-50">
                        <tr>
                            <th class="th">Fila</th>
                            <th class="th">SIGICOV</th>
                            <th class="th">Descripción</th>
                            <th class="th">Tipo / sugerencia</th>
                            <th class="th">Pasillo</th>
                            <th class="th text-right">Unidades</th>
                            <th class="th">Estado</th>
                            <th class="th">Motivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            @php
                                [$label, $statusClass] = $rowStatusLabels[$row->status]
                                    ?? [str_replace('_', ' ', ucfirst($row->status)), 'bg-stone-200 text-stone-800'];
                            @endphp
                            <tr @class([
                                'bg-amber-50/50' => in_array($row->status, ['existente', 'repetido'], true),
                                'bg-red-50/50' => in_array($row->status, ['sin_sigicov', 'invalida'], true),
                                'bg-blue-50/50' => $row->status === 'importado',
                            ])>
                                <td class="td tabular-nums text-gray-500">{{ $row->excel_row }}</td>
                                <td class="td font-medium tabular-nums">{{ $row->sigicov ?: '—' }}</td>
                                <td class="td">{{ $row->payload['descripcion'] ?? '—' }}</td>
                                <td class="td">
                                    @if($row->status === 'new' && $batch->status === 'ready')
                                        <select
                                            aria-label="Tipo para la fila {{ $row->excel_row }}"
                                            wire:change="setRowType({{ $row->id }}, $event.target.value)"
                                            class="field min-w-48"
                                        >
                                            <option value="" @selected($row->inventory_type_id === null)>Sin tipo asignado</option>
                                            @if($row->inventoryType && ! $row->inventoryType->is_active)
                                                <option value="{{ $row->inventory_type_id }}" selected disabled>{{ $row->inventoryType->name }} (inactivo)</option>
                                            @endif
                                            @foreach($inventoryTypes as $inventoryType)
                                                <option value="{{ $inventoryType->id }}" @selected((int) $row->inventory_type_id === $inventoryType->id)>
                                                    {{ $inventoryType->name }}{{ (int) $row->suggested_inventory_type_id === $inventoryType->id ? ' (sugerido)' : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @if($row->suggestedInventoryType)
                                            <p class="mt-1 text-xs text-gray-500">Sugerencia: {{ $row->suggestedInventoryType->name }}; confirma en el selector.</p>
                                        @endif
                                        @error("rowTypes.{$row->id}") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    @else
                                        {{ $row->inventoryType?->name ?: 'Sin tipo' }}
                                        @if($row->suggestedInventoryType && ! $row->inventoryType)
                                            <span class="block text-xs text-gray-500">Sugerencia: {{ $row->suggestedInventoryType->name }} (no asignada)</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="td text-gray-600">{{ $row->payload['pasillo'] ?? '—' }}</td>
                                <td class="td text-right tabular-nums">1</td>
                                <td class="td"><span class="badge {{ $statusClass }}">{{ $label }}</span></td>
                                <td class="td text-xs text-amber-800">{{ $row->reason }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-3 py-6 text-center text-gray-500">No hay filas para este filtro.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $rows->onEachSide(1)->links() }}</div>
        </section>
    @endif
</div>
