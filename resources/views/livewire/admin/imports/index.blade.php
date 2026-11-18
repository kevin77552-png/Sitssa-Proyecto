<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="page-title">Importaciones de inventario</h1>
            <p class="text-sm text-gray-600">Sube y analiza un Excel. El inventario no cambia hasta confirmar «Aplicar».</p>
        </div>
        <a href="{{ route('dashboard', ['module' => 'admin.migration']) }}" class="btn-secondary">Volver a exportación</a>
    </div>

    @if(!$schemaReady)
        <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950" role="alert">
            Las migraciones del módulo aún no están instaladas en esta base. Confirma la conexión, crea un respaldo y luego ejecuta <code>php artisan migrate</code>.
        </div>
    @else
        <section class="card">
            <h2 class="mb-1 font-semibold">Nueva importación</h2>
            <p class="mb-4 text-sm text-gray-600">Formatos XLSX o CSV, hasta {{ (int) config('inventory_import.max_file_kilobytes') / 1024 }} MB.</p>
            <form method="POST" action="{{ route('imports.store') }}" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-2">
                @csrf
                <div class="md:col-span-2">
                    <label for="import-file" class="mb-1 block text-sm font-medium">Archivo</label>
                    <input id="import-file" name="file" type="file" accept=".xlsx,.csv" required class="field w-full">
                    @error('file')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="cutoff-date" class="mb-1 block text-sm font-medium">Fecha de corte</label>
                    <input id="cutoff-date" name="cutoff_date" type="date" value="{{ old('cutoff_date', '2025-06-30') }}" required class="field w-full">
                    @error('cutoff_date')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="sheet-name" class="mb-1 block text-sm font-medium">Hoja (opcional)</label>
                    <input id="sheet-name" name="sheet_name" type="text" value="{{ old('sheet_name') }}" placeholder="JUNIO 2025 (2)" class="field w-full">
                    @error('sheet_name')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="btn-primary">Subir y analizar</button>
                </div>
            </form>
        </section>

        <section class="card" wire:poll.5s>
            <h2 class="mb-3 font-semibold">Historial de cargas</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead><tr class="text-left"><th class="px-3 py-2">Fecha</th><th class="px-3 py-2">Archivo</th><th class="px-3 py-2">Usuario</th><th class="px-3 py-2">Estado</th><th class="px-3 py-2">Nuevos</th><th class="px-3 py-2">Omitidos</th><th class="px-3 py-2"></th></tr></thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse($batches as $batch)
                            <tr>
                                <td class="px-3 py-2">{{ $batch->created_at->format('d/m/Y H:i') }}</td>
                                <td class="px-3 py-2">{{ $batch->file_name }}</td>
                                <td class="px-3 py-2">{{ $batch->user?->name }}</td>
                                <td class="px-3 py-2">{{ str_replace('_', ' ', ucfirst($batch->status)) }}</td>
                                <td class="px-3 py-2">{{ $batch->new_rows }}</td>
                                <td class="px-3 py-2">{{ $batch->existing_rows + $batch->duplicate_rows + $batch->invalid_rows }}</td>
                                <td class="px-3 py-2"><a class="text-blue-700 underline" href="{{ route('imports.show', $batch) }}">Ver lote</a></td>
                            </tr>
                            @if(in_array($batch->status, ['analyzing', 'applying'], true) && $batch->updated_at?->lt(now()->subMinutes(2)))
                                <tr>
                                    <td colspan="7" class="px-3 pb-3 text-amber-800">El proceso está tardando más de lo esperado. Contacta al administrador para verificar su estado.</td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="7" class="px-3 py-6 text-center text-gray-500">Aún no hay importaciones.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
