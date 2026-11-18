<div class="mx-auto max-w-5xl space-y-6">
    <header class="flex flex-col gap-4 border-b border-zinc-200 pb-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="page-title">{{ $editingId ? 'Editar unidad' : 'Registrar unidad' }}</h1>
            <p class="mt-1 text-sm text-zinc-500">Completa los datos de la unidad o carga varias desde una plantilla.</p>
        </div>
        <button type="button" wire:click="downloadTemplate" class="btn-secondary">Descargar plantilla XLSX</button>
    </header>

    @if(session('units-message'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800" role="status">{{ session('units-message') }}</div>
    @endif

    <section class="card space-y-4">
        <div>
            <h2 class="text-base font-semibold">Importar unidades</h2>
            <p class="mt-1 text-sm text-zinc-500">Analiza el archivo y revisa los cambios antes de aplicarlos. Las unidades omitidas no se modifican ni se eliminan.</p>
        </div>
        <form wire:submit="analyzeImport" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="min-w-0 flex-1">
                <label for="units-file" class="mb-1 block text-sm font-medium">Archivo XLSX o CSV</label>
                <input id="units-file" type="file" wire:model="file" accept=".xlsx,.csv,.txt" class="field w-full">
                @error('file') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                <div wire:loading wire:target="file" class="mt-1 text-xs text-zinc-500">Cargando archivo…</div>
            </div>
            <button type="submit" class="btn-primary">Analizar archivo</button>
        </form>

        @if($previewCounts)
            <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4">
                <h3 class="font-medium">Vista previa</h3>
                <p class="mt-1 text-sm text-zinc-600">
                    {{ $previewCounts['crear'] ?? 0 }} nuevas ·
                    {{ $previewCounts['actualizar'] ?? 0 }} por actualizar ·
                    {{ $previewCounts['sin_cambios'] ?? 0 }} sin cambios ·
                    {{ $previewCounts['error'] ?? 0 }} con errores
                </p>
                <div class="mt-3 max-h-64 overflow-auto rounded-md border border-zinc-200 bg-white">
                    <table class="w-full text-left text-xs">
                        <thead class="sticky top-0 bg-zinc-100 text-zinc-600">
                            <tr><th class="px-3 py-2">Fila</th><th class="px-3 py-2">Placa</th><th class="px-3 py-2">Acción</th><th class="px-3 py-2">Detalle</th></tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @foreach($previewPlan as $row)
                                <tr wire:key="unit-import-row-{{ $loop->index }}">
                                    <td class="px-3 py-2">{{ $row['line'] }}</td>
                                    <td class="px-3 py-2 font-medium">{{ $row['placa'] ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ str_replace('_', ' ', ucfirst($row['action'])) }}</td>
                                    <td class="px-3 py-2 text-zinc-600">{{ $row['reason'] ?? (count($row['changes']) ? implode(', ', array_keys($row['changes'])) : '—') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" wire:click="applyImport" wire:confirm="¿Aplicar los cambios válidos de esta importación?" class="btn-primary">Aplicar cambios válidos</button>
                    <button type="button" wire:click="$set('previewPlan', [])" class="btn-secondary">Cancelar</button>
                </div>
            </div>
        @endif
        @if($importMessage)
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800" role="status">{{ $importMessage }}</div>
        @endif
    </section>

    <section class="card">
        <h2 class="mb-4 text-base font-semibold">Datos de la unidad</h2>
        <form wire:submit="save" class="space-y-4">
            <div>
                <label for="unit-plate" class="mb-1 block text-sm font-medium">Placa *</label>
                <input id="unit-plate" type="text" wire:model="placa" maxlength="20" class="field w-full" autocomplete="off">
                @error('placa') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="unit-external-id" class="mb-1 block text-sm font-medium">Código externo</label>
                <input id="unit-external-id" type="text" wire:model="externalId" maxlength="64" class="field w-full">
                @error('externalId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="unit-type" class="mb-1 block text-sm font-medium">Tipo</label>
                    <input id="unit-type" type="text" wire:model="tipo" maxlength="40" class="field w-full" placeholder="Ej. bus, camioneta">
                    @error('tipo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="unit-model" class="mb-1 block text-sm font-medium">Modelo</label>
                    <input id="unit-model" type="text" wire:model="modelo" maxlength="60" class="field w-full">
                    @error('modelo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label for="unit-description" class="mb-1 block text-sm font-medium">Descripción</label>
                <input id="unit-description" type="text" wire:model="descripcion" maxlength="255" class="field w-full">
                @error('descripcion') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="unit-status" class="mb-1 block text-sm font-medium">Estado</label>
                <select id="unit-status" wire:model="estado" class="field w-full">
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('estado') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn-primary">Guardar unidad</button>
                @if($editingId)
                    <button type="button" wire:click="cancelEdit" class="btn-secondary">Cancelar edición</button>
                @endif
            </div>
        </form>
    </section>
</div>
