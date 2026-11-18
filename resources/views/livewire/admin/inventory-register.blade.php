<section class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm">
    <header class="flex flex-col gap-4 border-b border-slate-100 px-6 py-6 sm:px-8 md:flex-row md:items-center md:justify-between">
        <div class="flex items-start gap-3.5">
            <div class="mt-0.5 shrink-0 rounded-lg border border-rose-100 bg-rose-50 p-2.5 text-brand-700">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
            </div>
            <div>
                <h2 class="text-xl font-bold tracking-tight text-slate-900">Ingreso de repuestos</h2>
                <p class="mt-0.5 text-sm text-slate-500">Complete los datos técnicos y la ubicación física. Cada registro inicia con una unidad.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-4 self-start rounded-lg border border-slate-200/80 bg-slate-50 px-4 py-2.5 text-xs md:self-auto">
            <div class="flex items-center gap-2">
                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <div>
                    <span class="block text-[11px] font-medium text-slate-400">Fecha de registro</span>
                    <span class="font-mono font-semibold text-slate-700">{{ $fecha ? date('d/m/Y', strtotime($fecha)) : '—' }}</span>
                </div>
            </div>
            <div class="hidden h-7 w-px bg-slate-200 sm:block"></div>
            <div class="flex items-center gap-2">
                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                <div>
                    <span class="block text-[11px] font-medium text-slate-400">Realizado por</span>
                    <span class="font-semibold text-slate-700">{{ $realizado_por ?: '—' }}</span>
                </div>
            </div>
        </div>
    </header>

    <div class="p-6 sm:p-8">
        <div x-data="{ show: @entangle('showSaved'), message: @entangle('savedMessage') }" x-effect="if(show){ setTimeout(()=> show = false, 4000) }" class="mb-2">
            <div x-show="show" x-transition class="mb-6 rounded-lg border border-rose-200 bg-rose-50 p-3 text-center font-semibold text-rose-800" role="status" x-text="message"></div>
        </div>

        <form x-data @submit.prevent="
            if (window.Swal && typeof Swal.fire === 'function') {
                Swal.fire({
                    title: 'Confirmar registro',
                    text: '¿Confirmas el registro del repuesto?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sí',
                    cancelButtonText: 'No'
                }).then((result) => { if (result.isConfirmed) { $wire.save() } });
            } else {
                if (confirm('¿Confirmas el registro del repuesto?')) { $wire.save() }
            }
        " class="space-y-8">
            <section>
                <div class="mb-4 flex items-center gap-2">
                    <span class="h-4 w-1.5 rounded-full bg-brand-600"></span>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Datos del artículo</h3>
                </div>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-12">
                    <div class="md:col-span-4">
                        <label for="sigicov" class="mb-1.5 block text-sm font-medium text-slate-700">Sistema SIGICOV <span class="text-brand-600">*</span></label>
                        <input id="sigicov" type="text" wire:model="sigicov" placeholder="Solo caracteres numéricos" oninput="this.value = this.value.toUpperCase()"
                            class="w-full rounded-lg border border-slate-300 bg-slate-50/50 px-3.5 py-2.5 font-mono text-sm text-slate-800 placeholder:text-slate-400 transition focus:border-brand-600 focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-600/10">
                        @error('sigicov') <span class="mt-1.5 block text-xs text-red-600">{{ $message }}</span> @enderror
                        <p class="mt-1.5 flex items-center gap-1 text-[11px] text-slate-400">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Solo caracteres numéricos
                        </p>
                        <p class="mt-1 text-[11px] text-slate-500">Al registrarlo, el SIGICOV estará disponible como Code 128 en Consulta y Salida.</p>
                    </div>
                    <div class="md:col-span-8">
                        <label for="descripcion" class="mb-1.5 block text-sm font-medium text-slate-700">Descripción de repuesto o accesorio <span class="text-brand-600">*</span></label>
                        <input id="descripcion" type="text" wire:model="descripcion" placeholder="Conj. de turbocompresor" oninput="this.value = this.value.toUpperCase()"
                            class="w-full rounded-lg border border-slate-300 bg-slate-50/50 px-3.5 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 transition focus:border-brand-600 focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-600/10">
                        @error('descripcion') <span class="mt-1.5 block text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div class="md:col-span-12">
                        <label for="inventory-type" class="mb-1.5 block text-sm font-medium text-slate-700">Tipo de inventario</label>
                        <select id="inventory-type" wire:model="inventory_type_id" class="w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-800 focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-600/10">
                            <option value="">Sin tipo asignado</option>
                            @foreach($inventoryTypes as $inventoryType)
                                <option value="{{ $inventoryType->id }}">{{ $inventoryType->name }}</option>
                            @endforeach
                        </select>
                        @error('inventory_type_id') <span class="mt-1.5 block text-xs text-red-600">{{ $message }}</span> @enderror
                        <div class="mt-2">
                            <button
                                type="button"
                                wire:click="suggestType"
                                class="inline-flex items-center rounded-md border border-brand-600/30 bg-brand-50 px-3 py-1.5 text-xs font-semibold text-brand-800 transition hover:border-brand-600 hover:bg-brand-100 focus:outline-none focus:ring-2 focus:ring-brand-600/30"
                            >
                                Sugerir tipo
                            </button>
                        </div>
                        @if($showTypeSuggestion && $suggestedInventoryType)
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <span class="text-xs text-slate-600">Sugerencia detectada:</span>
                                <button
                                    type="button"
                                    wire:click="$set('inventory_type_id', {{ $suggestedInventoryType->id }})"
                                    aria-label="Usar sugerencia de tipo: {{ $suggestedInventoryType->name }}"
                                    class="inline-flex items-center rounded-md border border-brand-600/30 bg-brand-50 px-3 py-1.5 text-xs font-semibold text-brand-800 transition hover:border-brand-600 hover:bg-brand-100 focus:outline-none focus:ring-2 focus:ring-brand-600/30"
                                >
                                    Usar sugerencia: {{ $suggestedInventoryType->name }}
                                </button>
                            </div>
                        @elseif($showTypeSuggestion)
                            <p class="mt-2 text-xs text-slate-600">No se encontró una sugerencia única para esta descripción.</p>
                        @endif
                    </div>
                    <div class="md:col-span-12">
                        <label for="resp-accesorios" class="mb-1.5 block text-sm font-medium text-slate-700">Resp. accesorios (código)</label>
                        <input id="resp-accesorios" type="text" wire:model="resp_accesorios" placeholder="1118-00967" oninput="this.value = this.value.toUpperCase()"
                            class="w-full rounded-lg border border-slate-300 bg-slate-50/50 px-3.5 py-2.5 font-mono text-sm text-slate-800 placeholder:text-slate-400 transition focus:border-brand-600 focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-600/10">
                        @error('resp_accesorios') <span class="mt-1.5 block text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>
                </div>
            </section>

            <hr class="border-slate-100">

            <section>
                <div class="mb-4 flex items-center gap-2">
                    <span class="h-4 w-1.5 rounded-full bg-brand-600"></span>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Ubicación en almacén</h3>
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-5">
                    <div>
                        <label for="pasillo" class="mb-1.5 block text-sm font-medium text-slate-700">Pasillo</label>
                        <input id="pasillo" type="text" wire:model="pasillo" maxlength="6" placeholder="P-P" required oninput="this.value = this.value.toUpperCase()"
                            class="w-full rounded-lg border border-slate-300 bg-slate-50/50 px-3 py-2.5 text-center font-mono text-sm uppercase text-slate-800 placeholder:text-slate-400 transition focus:border-brand-600 focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-600/10">
                        @error('pasillo') <span class="mt-1.5 block text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="estante" class="mb-1.5 block text-sm font-medium text-slate-700">Estante</label>
                        <input id="estante" type="text" wire:model="estante" maxlength="6" placeholder="2" required oninput="this.value = this.value.toUpperCase()"
                            class="w-full rounded-lg border border-slate-300 bg-slate-50/50 px-3 py-2.5 text-center font-mono text-sm text-slate-800 placeholder:text-slate-400 transition focus:border-brand-600 focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-600/10">
                        @error('estante') <span class="mt-1.5 block text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="peldano" class="mb-1.5 block text-sm font-medium text-slate-700">Peldaño</label>
                        <input id="peldano" type="text" wire:model="peldaño" maxlength="6" placeholder="C" required oninput="this.value = this.value.toUpperCase()"
                            class="w-full rounded-lg border border-slate-300 bg-slate-50/50 px-3 py-2.5 text-center font-mono text-sm uppercase text-slate-800 placeholder:text-slate-400 transition focus:border-brand-600 focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-600/10">
                        @error('peldaño') <span class="mt-1.5 block text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="fecha" class="mb-1.5 block text-sm font-medium text-slate-700">Fecha</label>
                        <input id="fecha" type="date" wire:model="fecha" readonly
                            class="w-full rounded-lg border border-slate-200 bg-slate-100 px-3 py-2.5 text-sm text-slate-500">
                    </div>
                    <div>
                        <label for="realizado-por" class="mb-1.5 block text-sm font-medium text-slate-700">Realizado por</label>
                        <input id="realizado-por" type="text" wire:model="realizado_por" readonly
                            class="w-full rounded-lg border border-slate-200 bg-slate-100 px-3 py-2.5 text-sm text-slate-500">
                    </div>
                </div>
            </section>

            <div class="flex flex-col-reverse items-center justify-end gap-3 border-t border-slate-100 pt-6 sm:flex-row">
                <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg bg-brand-900 px-6 py-2.5 text-sm font-semibold text-white shadow-sm shadow-brand-900/20 transition hover:bg-brand-700 focus:outline-none focus:ring-4 focus:ring-brand-900/20 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto">
                    <svg wire:loading wire:target="save" class="-ml-1 mr-1 h-4 w-4 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="save">Registrar repuesto</span>
                    <span wire:loading wire:target="save">Registrando...</span>
                </button>
            </div>
        </form>
    </div>
</section>

<section class="mt-6 overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm">
    <div class="border-b border-slate-100 px-5 py-5 sm:px-6">
        <h3 class="text-base font-semibold text-slate-900">Repuestos registrados recientemente</h3>
        <p class="mt-0.5 text-sm text-slate-500">Consulta los últimos ingresos y filtra por código o ubicación.</p>
    </div>
    <div class="flex flex-col gap-3 p-5 sm:flex-row sm:flex-wrap sm:items-center sm:px-6">
        <input type="text" wire:model="search" aria-label="Buscar por SIGICOV" placeholder="Buscar por SIGICOV"
            class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-600/10 sm:w-auto" />
        <input type="text" wire:model="search_descripcion" aria-label="Buscar por descripción" placeholder="Buscar por descripción"
            class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-600/10 sm:w-auto" />
        <input type="text" wire:model="search_pasillo" aria-label="Buscar por pasillo" placeholder="Buscar por pasillo"
            class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-600/10 sm:w-auto" />
        <input type="text" wire:model="search_estante" aria-label="Buscar por estante" placeholder="Buscar por estante"
            class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-600/10 sm:w-auto" />
        <input type="text" wire:model="search_peldano" aria-label="Buscar por peldaño" placeholder="Buscar por peldaño"
            class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-600/10 sm:w-auto" />
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-xs">
            <thead>
                <tr class="bg-slate-50 text-left font-semibold uppercase tracking-wide text-slate-500">
                    <th class="whitespace-nowrap px-3 py-3">SIGICOV</th>
                    <th class="whitespace-nowrap px-3 py-3">Descripción</th>
                    <th class="whitespace-nowrap px-3 py-3">Tipo</th>
                    <th class="whitespace-nowrap px-3 py-3">Pasillo</th>
                    <th class="whitespace-nowrap px-3 py-3">Estante</th>
                    <th class="whitespace-nowrap px-3 py-3">Peldaño</th>
                    <th class="whitespace-nowrap px-3 py-3">Fecha</th>
                    <th class="whitespace-nowrap px-3 py-3">Realizado por</th>
                    <th class="whitespace-nowrap px-3 py-3">Resp. accesorios</th>
                    <th class="whitespace-nowrap px-3 py-3" title="Fecha y hora en que el artículo se registró en el sistema">Fecha de registro</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($items as $item)
                    <tr class="{{ $loop->odd ? 'bg-white' : 'bg-slate-50/70' }} text-slate-700">
                        <td class="whitespace-nowrap px-3 py-3 font-mono font-medium">{{ $item->sigicov }}</td>
                        <td class="min-w-52 px-3 py-3">{{ $item->descripcion }}</td>
                        <td class="whitespace-nowrap px-3 py-3">{{ $item->inventoryType?->name ?: 'Sin tipo' }}</td>
                        <td class="whitespace-nowrap px-3 py-3">{{ $item->pasillo }}</td>
                        <td class="whitespace-nowrap px-3 py-3">{{ $item->estante }}</td>
                        <td class="whitespace-nowrap px-3 py-3">{{ $item->peldaño }}</td>
                        <td class="whitespace-nowrap px-3 py-3">{{ $item->fecha ?: 'NO DISPONIBLE' }}</td>
                        <td class="whitespace-nowrap px-3 py-3">{{ $item->realizado_por }}</td>
                        <td class="whitespace-nowrap px-3 py-3">{{ $item->resp_accesorios }}</td>
                        <td class="whitespace-nowrap px-3 py-3 font-mono text-[11px]" title="Fecha y hora en que el artículo se registró en el sistema">{{ $item->created_at ? $item->created_at->format('d/m/Y H:i') : '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="px-3 py-8 text-center text-sm text-slate-400">Sin resultados</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
