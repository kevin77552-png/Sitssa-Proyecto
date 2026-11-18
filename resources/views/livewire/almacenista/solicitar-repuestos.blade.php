<div class="mx-auto w-full space-y-6">
    <section class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm">
        <header class="flex flex-col gap-4 border-b border-slate-100 bg-white px-6 py-6 sm:flex-row sm:items-center sm:justify-between sm:px-8">
            <div class="flex items-start gap-3.5">
                <div class="mt-0.5 shrink-0 rounded-lg border border-[#4A0C12]/10 bg-[#4A0C12]/5 p-2.5 text-[#4A0C12]">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-slate-900">Solicitar repuestos</h1>
                    <p class="mt-0.5 text-sm text-slate-500">Envía una solicitud de compra o reposición directamente al gerente.</p>
                </div>
            </div>

            <div class="flex items-center gap-2.5 self-start rounded-lg border border-slate-200/80 bg-slate-50 px-3.5 py-2 text-xs sm:self-auto">
                <div class="rounded-md border border-slate-200 bg-white p-1.5 text-slate-500">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
                <div>
                    <span class="block text-[11px] font-medium text-slate-400">Solicitud realizada por</span>
                    <span class="font-semibold text-slate-800">{{ $almacenistaName ?? '—' }}</span>
                </div>
            </div>
        </header>

        @if($successMessage)
            <div class="mx-6 mt-5 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800 sm:mx-8" role="status">
                {{ $successMessage }}
            </div>
        @endif

        <form wire:submit="submit" class="space-y-6 p-6 sm:p-8">
            <div class="max-w-xl">
                <label for="request-requested-by" class="mb-1.5 block text-sm font-medium text-slate-700">
                    Solicitado por <span class="text-[#4A0C12]">*</span>
                </label>
                <input id="request-requested-by" type="text" required maxlength="255" wire:model.defer="solicitadoPor"
                       placeholder="Nombre de la persona que necesita los repuestos"
                       class="w-full rounded-lg border border-slate-300 bg-slate-50/50 px-3.5 py-2.5 text-sm text-slate-800 transition placeholder:text-slate-400 focus:border-[#4A0C12] focus:bg-white focus:outline-none focus:ring-4 focus:ring-[#4A0C12]/10">
                @error('solicitadoPor') <p class="mt-1.5 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>

            <section class="space-y-4" aria-labelledby="request-items-title">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 id="request-items-title" class="text-sm font-semibold text-slate-800">Repuestos solicitados</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Agrega hasta 20 repuestos; cada uno puede tener su propia cantidad y especificación.</p>
                    </div>
                    <button type="button" wire:click="addItem" @disabled(count($items) >= 20)
                            class="inline-flex items-center gap-1.5 rounded-lg border border-[#4A0C12]/20 bg-white px-3 py-2 text-xs font-semibold text-[#4A0C12] transition hover:bg-[#4A0C12]/5 disabled:cursor-not-allowed disabled:opacity-50">
                        <span aria-hidden="true">+</span>
                        Agregar repuesto
                    </button>
                </div>
                @error('items') <p class="text-xs text-red-600" role="alert">{{ $message }}</p> @enderror

                @foreach($items as $index => $item)
                    <article wire:key="request-item-{{ $item['_key'] }}" class="rounded-lg border border-slate-200 bg-slate-50/60 p-4">
                        <div class="mb-3 flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Repuesto {{ $index + 1 }}</span>
                            @if(count($items) > 1)
                                <button type="button" wire:click="removeItem({{ $index }})"
                                        class="rounded px-2 py-1 text-xs font-medium text-rose-600 transition hover:bg-rose-50">
                                    Quitar
                                </button>
                            @endif
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                            <div class="md:col-span-8">
                                <label for="request-item-description-{{ $index }}" class="mb-1.5 block text-sm font-medium text-slate-700">
                                    Descripción del repuesto <span class="text-[#4A0C12]">*</span>
                                </label>
                                <input id="request-item-description-{{ $index }}" type="text" required maxlength="255"
                                       wire:model.defer="items.{{ $index }}.descripcion"
                                       placeholder="Ej. Juego de empacaduras o destornillador"
                                       class="w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-800 transition placeholder:text-slate-400 focus:border-[#4A0C12] focus:outline-none focus:ring-4 focus:ring-[#4A0C12]/10">
                                @error("items.{$index}.descripcion") <p class="mt-1.5 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
                            </div>

                            <div class="md:col-span-4">
                                <label for="request-item-quantity-{{ $index }}" class="mb-1.5 block text-sm font-medium text-slate-700">
                                    Cantidad <span class="text-[#4A0C12]">*</span>
                                </label>
                                <div class="flex overflow-hidden rounded-lg border border-slate-300 bg-white transition focus-within:border-[#4A0C12] focus-within:ring-4 focus-within:ring-[#4A0C12]/10">
                                    <button type="button" wire:click="adjustItemQuantity({{ $index }}, -1)" aria-label="Disminuir cantidad del repuesto {{ $index + 1 }}"
                                            class="px-3 py-2.5 font-semibold text-slate-500 transition hover:bg-slate-100 hover:text-slate-800">−</button>
                                    <input id="request-item-quantity-{{ $index }}" type="number" min="1" max="999" required
                                           wire:model.defer="items.{{ $index }}.cantidad"
                                           class="w-full appearance-none bg-transparent py-2.5 text-center font-mono text-sm font-semibold text-slate-800 focus:outline-none">
                                    <button type="button" wire:click="adjustItemQuantity({{ $index }}, 1)" aria-label="Aumentar cantidad del repuesto {{ $index + 1 }}"
                                            class="px-3 py-2.5 font-semibold text-slate-500 transition hover:bg-slate-100 hover:text-slate-800">+</button>
                                </div>
                                @error("items.{$index}.cantidad") <p class="mt-1.5 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
                            </div>

                            <div class="md:col-span-12">
                                <label for="request-item-details-{{ $index }}" class="mb-1.5 block text-sm font-medium text-slate-700">
                                    Detalles o especificaciones <span class="font-normal text-slate-400">(opcional)</span>
                                </label>
                                <textarea id="request-item-details-{{ $index }}" rows="2" maxlength="1000"
                                          wire:model.defer="items.{{ $index }}.detalles"
                                          placeholder="Número de parte, medida o uso requerido..."
                                          class="w-full resize-y rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-800 transition placeholder:text-slate-400 focus:border-[#4A0C12] focus:outline-none focus:ring-4 focus:ring-[#4A0C12]/10"></textarea>
                                @error("items.{$index}.detalles") <p class="mt-1.5 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </article>
                @endforeach
            </section>

            <div class="max-w-sm">
                <label for="request-priority" class="mb-1.5 block text-sm font-medium text-slate-700">Prioridad de la solicitud</label>
                <select id="request-priority" wire:model.defer="prioridad"
                        class="w-full cursor-pointer rounded-lg border border-slate-300 bg-slate-50/50 px-3 py-2.5 text-sm text-slate-800 transition focus:border-[#4A0C12] focus:bg-white focus:outline-none focus:ring-4 focus:ring-[#4A0C12]/10">
                    <option value="NORMAL">Normal</option>
                    <option value="URGENTE">Urgente (unidad parada)</option>
                </select>
                @error('prioridad') <p class="mt-1.5 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col-reverse items-center justify-between gap-3 border-t border-slate-100 pt-4 sm:flex-row">
                <span class="flex items-center gap-1.5 text-xs text-slate-400">
                    <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    El gerente recibirá la solicitud para su revisión.
                </span>
                <div class="flex w-full items-center justify-end gap-2.5 sm:w-auto">
                    <button type="button" wire:click="clearForm"
                            class="cursor-pointer rounded-lg px-4 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                        Limpiar
                    </button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="submit"
                            class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg bg-[#4A0C12] px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-[#3A090E] disabled:cursor-wait disabled:opacity-60">
                        <svg wire:loading.remove wire:target="submit" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                        <span wire:loading.remove wire:target="submit">Crear solicitud</span>
                        <span wire:loading wire:target="submit">Enviando...</span>
                    </button>
                </div>
            </div>
        </form>
    </section>

    <section class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm">
        <header class="flex items-center justify-between gap-3 border-b border-slate-100 px-6 py-4 sm:px-8">
            <div class="flex items-center gap-2.5">
                <h2 class="text-base font-bold text-slate-900">Solicitudes recientes</h2>
                <span class="rounded-full border border-slate-200/80 bg-slate-100 px-2 py-0.5 font-mono text-xs font-semibold text-slate-600">
                    {{ $requests->count() }}
                </span>
            </div>
            <span class="hidden text-xs text-slate-400 sm:inline">Historial personal de requisiciones</span>
        </header>

        @if($requests->isEmpty())
            <div class="px-6 py-12 text-center">
                <p class="text-sm font-semibold text-slate-900">Aún no has realizado solicitudes</p>
                <p class="mt-1 text-xs text-slate-500">Cuando envíes una, aparecerá aquí su estado y fecha.</p>
            </div>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach($requests as $req)
                    @php
                        [$statusLabel, $statusClass, $statusDot] = match($req->status) {
                            'approved' => ['Aprobada por gerencia', 'border-emerald-200/80 bg-emerald-50 text-emerald-700', 'bg-emerald-500'],
                            'rejected' => ['Rechazada', 'border-rose-200/80 bg-rose-50 text-rose-700', 'bg-rose-500'],
                            'received' => ['Recibida', 'border-sky-200/80 bg-sky-50 text-sky-700', 'bg-sky-500'],
                            'pending' => ['Pendiente de revisión', 'border-amber-200/80 bg-amber-50 text-amber-700', 'bg-amber-500'],
                            default => [ucfirst($req->status ?? 'Pendiente'), 'border-slate-200 bg-slate-100 text-slate-700', 'bg-slate-400'],
                        };
                    @endphp
                    <li class="flex flex-col justify-between gap-4 px-6 py-4 transition-colors hover:bg-slate-50/80 sm:flex-row sm:items-center sm:px-8">
                        <div class="min-w-0 space-y-1.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded border border-slate-200/70 bg-slate-100 px-2 py-0.5 font-mono text-xs font-semibold text-slate-500">
                                    REQ-{{ str_pad((string) $req->id, 3, '0', STR_PAD_LEFT) }}
                                </span>
                                <span class="inline-flex items-center rounded border border-[#4A0C12]/10 bg-[#4A0C12]/5 px-2 py-0.5 font-mono text-xs font-semibold text-[#4A0C12]">
                                    {{ $req->cantidad }} unidades en total
                                </span>
                                @if($req->prioridad === 'URGENTE')
                                    <span class="inline-flex items-center rounded border border-rose-200/70 bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-700">
                                        Urgente
                                    </span>
                                @endif
                            </div>
                            <ul class="space-y-1.5 pl-1">
                                @foreach($req->displayItems() as $requestItem)
                                    <li class="text-sm text-slate-800">
                                        <span class="font-semibold">{{ $requestItem->descripcion }}</span>
                                        <span class="ml-1 font-mono text-xs text-slate-500">× {{ $requestItem->cantidad }}</span>
                                        @if($requestItem->detalles)
                                            <p class="mt-0.5 text-xs leading-relaxed text-slate-500">{{ $requestItem->detalles }}</p>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                            @if($req->solicitado_por)
                                <p class="text-xs text-slate-500">
                                    <span class="font-semibold text-slate-600">Solicitado por:</span> {{ $req->solicitado_por }}
                                </p>
                            @endif
                            @if($req->reject_reason)
                                <p class="text-xs text-rose-700"><span class="font-semibold">Motivo del rechazo:</span> {{ $req->reject_reason }}</p>
                            @endif
                            @if($req->comentario)
                                <p class="text-xs text-slate-600"><span class="font-semibold">Comentario:</span> {{ $req->comentario }}</p>
                            @endif
                        </div>

                        <div class="flex shrink-0 items-center justify-between gap-3 sm:flex-col sm:items-end">
                            <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium {{ $statusClass }}" role="status">
                                <span class="h-1.5 w-1.5 rounded-full {{ $statusDot }}"></span>
                                {{ $statusLabel }}
                            </span>
                            <span class="flex items-center gap-1 font-mono text-[11px] text-slate-400">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                {{ $req->created_at->format('d/m/Y H:i') }}
                            </span>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
