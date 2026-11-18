<div class="w-full space-y-6">
    <header>
        <h1 class="page-title">Catálogo de tipos de inventario</h1>
        <p class="mt-1 text-sm text-gray-500">Las palabras clave solo generan sugerencias; cada asignación se confirma manualmente.</p>
    </header>

    @if(session('inventory-type-message'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800" role="status">{{ session('inventory-type-message') }}</div>
    @endif

    <section class="card">
        <h2 class="mb-4 text-base font-semibold">{{ $editingId ? 'Editar tipo' : 'Agregar tipo' }}</h2>
        <form wire:submit="save" class="space-y-4">
            <div>
                <label for="inventory-type-name" class="mb-1 block text-sm font-medium">Nombre del tipo</label>
                <input id="inventory-type-name" type="text" wire:model="name" maxlength="120" class="field w-full">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="inventory-type-keywords" class="mb-1 block text-sm font-medium">Palabras clave para sugerencias</label>
                <textarea id="inventory-type-keywords" wire:model="keywordsText" rows="3" maxlength="2000" class="field w-full" placeholder="filtro, aceite, lubricante"></textarea>
                <p class="mt-1 text-xs text-gray-500">Separadas por coma, punto y coma o salto de línea. Si una descripción coincide con varios tipos, no se sugiere ninguno.</p>
                @error('keywordsText') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn-primary">Guardar tipo</button>
                @if($editingId)
                    <button type="button" wire:click="cancelEdit" class="btn-secondary">Cancelar</button>
                @endif
            </div>
        </form>
    </section>

    <section class="card overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-stone-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-3 py-3">Tipo</th>
                    <th class="px-3 py-3">Palabras clave</th>
                    <th class="px-3 py-3">Artículos asignados</th>
                    <th class="px-3 py-3">Estado</th>
                    <th class="px-3 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($types as $type)
                    <tr wire:key="inventory-type-{{ $type->id }}">
                        <td class="px-3 py-3 font-medium">{{ $type->name }}</td>
                        <td class="px-3 py-3 text-gray-600">{{ implode(', ', $type->keywords ?? []) ?: '—' }}</td>
                        <td class="px-3 py-3 tabular-nums">{{ $type->inventory_items_count }}</td>
                        <td class="px-3 py-3">{{ $type->is_active ? 'Activo' : 'Inactivo' }}</td>
                        <td class="space-x-2 px-3 py-3 text-right">
                            <button type="button" wire:click="edit({{ $type->id }})" class="text-brand-700 hover:underline">Editar</button>
                            <button type="button" wire:click="toggleActive({{ $type->id }})" class="text-brand-700 hover:underline">{{ $type->is_active ? 'Desactivar' : 'Activar' }}</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-8 text-center text-gray-500">Aún no hay tipos. Agrega los tipos que utiliza el almacén.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</div>
