<div class="w-full space-y-6">
    <section class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm">
        <header class="flex flex-col gap-4 border-b border-slate-100 bg-white px-6 py-6 sm:flex-row sm:items-center sm:justify-between sm:px-8">
            <div class="flex items-start gap-3.5">
                <div class="mt-0.5 shrink-0 rounded-lg border border-[#4A0C12]/10 bg-[#4A0C12]/5 p-2.5 text-[#4A0C12]">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-slate-900">Órdenes de compra</h1>
                    <p class="mt-0.5 text-sm text-slate-500">Registra pedidos de reposición y asigna un proveedor autorizado.</p>
                </div>
            </div>

            <div class="flex items-center gap-2.5 self-start rounded-lg border border-slate-200/80 bg-slate-50 px-3.5 py-2 text-xs sm:self-auto">
                <span class="rounded-md border border-slate-200 bg-white p-1.5 text-slate-500">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </span>
                <span>
                    <span class="block text-[11px] font-medium text-slate-400">Emitida por</span>
                    <span class="font-semibold text-slate-800">{{ auth()->user()->name }} ({{ ucfirst(auth()->user()->role) }})</span>
                </span>
            </div>
        </header>

        <form wire:submit.prevent="createOrder" class="space-y-6 p-6 sm:p-8">
            @if(session('success'))
                <div role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            <section class="space-y-3" aria-labelledby="order-items-heading">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 id="order-items-heading" class="text-sm font-semibold text-slate-700">Repuestos de la orden</h2>
                        <p class="mt-0.5 text-xs text-slate-400">Agrega cada repuesto y su cantidad (hasta 20). El inventario se actualizará manualmente al recibirlos.</p>
                    </div>
                    <button type="button" wire:click="addItem" @disabled(count($items) >= 20)
                        class="cursor-pointer rounded-lg border border-[#4A0C12]/20 bg-white px-3 py-2 text-xs font-semibold text-[#4A0C12] transition hover:bg-[#4A0C12]/5 disabled:cursor-not-allowed disabled:opacity-50">
                        + Agregar repuesto
                    </button>
                </div>

                @error('items') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                <div class="space-y-3">
                    @foreach($items as $index => $item)
                        <div wire:key="purchase-order-item-{{ $index }}" class="grid grid-cols-1 items-end gap-3 rounded-lg border border-slate-200 bg-slate-50/50 p-3 sm:grid-cols-12">
                            <div class="sm:col-span-9">
                                <label for="order-item-description-{{ $index }}" class="mb-1.5 block text-xs font-medium text-slate-700">
                                    Repuesto {{ $index + 1 }} <span class="text-[#4A0C12]">*</span>
                                </label>
                                <input type="text" wire:model="items.{{ $index }}.descripcion" id="order-item-description-{{ $index }}" maxlength="1000"
                                    placeholder="Ej. Filtro de aceite"
                                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 focus:border-[#4A0C12] focus:outline-none focus:ring-2 focus:ring-[#4A0C12]/10">
                                @error('items.'.$index.'.descripcion') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label for="order-item-quantity-{{ $index }}" class="mb-1.5 block text-xs font-medium text-slate-700">
                                    Cantidad <span class="text-[#4A0C12]">*</span>
                                </label>
                                <input type="number" wire:model="items.{{ $index }}.cantidad" id="order-item-quantity-{{ $index }}" min="1" max="2147483647"
                                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-center font-mono text-sm text-slate-800 focus:border-[#4A0C12] focus:outline-none focus:ring-2 focus:ring-[#4A0C12]/10">
                                @error('items.'.$index.'.cantidad') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-1">
                                @if(count($items) > 1)
                                    <button type="button" wire:click="removeItem({{ $index }})" aria-label="Quitar repuesto {{ $index + 1 }}"
                                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-500 hover:bg-rose-50 hover:text-rose-700">
                                        Quitar
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <div class="grid grid-cols-1 gap-5 md:grid-cols-12">
                <div class="md:col-span-6">
                    <label for="order-provider" class="mb-1.5 block text-sm font-medium text-slate-700">
                        Proveedor asignado <span class="text-[#4A0C12]">*</span>
                    </label>
                    <select wire:model="providerId" id="order-provider" required
                        class="w-full cursor-pointer rounded-lg border border-slate-300 bg-slate-50/50 px-3.5 py-2.5 text-sm text-slate-800 transition focus:border-[#4A0C12] focus:bg-white focus:outline-none focus:ring-4 focus:ring-[#4A0C12]/10">
                        <option value="">Seleccione un proveedor</option>
                        @foreach($providers as $provider)
                            <option value="{{ $provider->id }}">{{ $provider->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('providerId')" class="mt-1.5" />
                    <p class="mt-1.5 text-[11px] text-slate-400">Solo se muestran proveedores activos.</p>
                </div>

                <div class="md:col-span-6">
                    <label for="order-details" class="mb-1.5 block text-sm font-medium text-slate-700">
                        Detalles u observaciones <span class="font-normal text-slate-400">(opcional)</span>
                    </label>
                    <textarea wire:model="detalles" id="order-details" name="detalles" rows="2" maxlength="2000"
                        placeholder="Ej. Condiciones de entrega o referencias adicionales..."
                        class="w-full resize-y rounded-lg border border-slate-300 bg-slate-50/50 px-3.5 py-2.5 text-sm text-slate-800 transition placeholder:text-slate-400 focus:border-[#4A0C12] focus:bg-white focus:outline-none focus:ring-4 focus:ring-[#4A0C12]/10"></textarea>
                    <x-input-error :messages="$errors->get('detalles')" class="mt-1.5" />
                </div>
            </div>

            <div class="flex flex-col-reverse items-center justify-between gap-3 border-t border-slate-100 pt-4 sm:flex-row">
                <span class="text-xs text-slate-400">El proveedor debe estar asignado para crear el pedido.</span>
                <div class="flex w-full items-center justify-end gap-2.5 sm:w-auto">
                    <button type="button" wire:click="resetForm"
                        class="cursor-pointer rounded-lg px-4 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                        Limpiar
                    </button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="createOrder"
                        class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg bg-[#4A0C12] px-5 py-2.5 text-sm font-medium text-white shadow-sm shadow-[#4A0C12]/20 transition hover:bg-[#3A090E] active:bg-[#30070B] disabled:cursor-wait disabled:opacity-70">
                        <svg wire:loading.remove wire:target="createOrder" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        <span wire:loading.remove wire:target="createOrder">Crear pedido</span>
                        <span wire:loading wire:target="createOrder">Creando...</span>
                    </button>
                </div>
            </div>
        </form>
    </section>

    <section class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm">
        <header class="flex flex-col gap-3 border-b border-slate-100 px-6 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-8">
            <div class="flex items-center gap-2.5">
                <h2 class="text-base font-bold text-slate-900">Órdenes recientes</h2>
                <span class="rounded-full border border-slate-200/80 bg-slate-100 px-2 py-0.5 font-mono text-xs font-semibold text-slate-600">
                    {{ $orders->count() }}
                </span>
            </div>
            <span class="text-xs text-slate-500">Últimos pedidos registrados</span>
        </header>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[850px] border-collapse text-left">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/50 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                        <th scope="col" class="py-3.5 pl-6 pr-3">N.º orden</th>
                        <th scope="col" class="px-3 py-3.5">Emitida por</th>
                        <th scope="col" class="px-3 py-3.5">Descripción</th>
                        <th scope="col" class="px-3 py-3.5 text-center">Unidades</th>
                        <th scope="col" class="px-3 py-3.5">Estado</th>
                        <th scope="col" class="py-3.5 pl-3 pr-6">Proveedor</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200/70 bg-white text-sm">
                    @forelse($orders as $order)
                        @php
                            [$statusLabel, $statusStyle] = match ($order->status) {
                                'aprobado' => ['Aprobada', 'border-emerald-200/80 bg-emerald-50 text-emerald-700'],
                                'factura_subida', 'por_revisar' => ['Factura subida', 'border-amber-200/80 bg-amber-50 text-amber-700'],
                                'rechazado' => ['Rechazada', 'border-rose-200/80 bg-rose-50 text-rose-700'],
                                'en_proceso' => ['En proceso', 'border-sky-200/80 bg-sky-50 text-sky-700'],
                                default => ['Pendiente', 'border-slate-200 bg-slate-100 text-slate-700'],
                            };
                        @endphp
                        <tr wire:key="purchase-order-{{ $order->id }}" class="transition-colors hover:bg-slate-50/90">
                            <td class="whitespace-nowrap py-3.5 pl-6 pr-3">
                                <span class="rounded border border-[#4A0C12]/10 bg-[#4A0C12]/5 px-2.5 py-1 font-mono text-xs font-bold text-[#4A0C12]">
                                    #{{ $order->id }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3.5 text-xs font-medium text-slate-700">{{ $order->pedido_por ?: '—' }}</td>
                            <td class="px-3 py-3.5">
                                @foreach($order->displayItems() as $orderItem)
                                    <p class="text-xs text-slate-900 sm:text-sm">
                                        <span class="font-semibold">{{ $orderItem->descripcion }}</span>
                                        <span class="font-mono text-slate-500">× {{ $orderItem->cantidad }}</span>
                                    </p>
                                    @if($orderItem->detalles)
                                        <p class="text-[11px] text-slate-500">{{ $orderItem->detalles }}</p>
                                    @endif
                                @endforeach
                                @if($order->solicitado_por)
                                    <p class="mt-1 text-[11px] text-slate-500">Solicitada por: {{ $order->solicitado_por }}</p>
                                @endif
                                @if($order->detalles)
                                    <p class="mt-0.5 text-[11px] text-slate-400">{{ $order->detalles }}</p>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-3.5 text-center">
                                <span class="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs font-semibold text-slate-700">{{ $order->cantidad }}</span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3.5">
                                <span @class([
                                    'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium',
                                    $statusStyle,
                                ])>
                                    <span @class([
                                        'h-1.5 w-1.5 rounded-full',
                                        'bg-emerald-500' => $order->status === 'aprobado',
                                        'bg-amber-500' => in_array($order->status, ['factura_subida', 'por_revisar'], true),
                                        'bg-rose-500' => $order->status === 'rechazado',
                                        'bg-sky-500' => $order->status === 'en_proceso',
                                        'bg-slate-400' => ! in_array($order->status, ['aprobado', 'factura_subida', 'por_revisar', 'rechazado', 'en_proceso'], true),
                                    ])></span>
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap py-3.5 pl-3 pr-6">
                                @if($order->provider)
                                    <span class="text-xs font-medium text-slate-700">{{ $order->provider->name }}</span>
                                @else
                                    <span class="inline-flex items-center rounded border border-slate-200/70 bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-500">Sin asignar</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <p class="text-sm font-semibold text-slate-900">No hay órdenes registradas</p>
                                <p class="mt-1 text-xs text-slate-500">Al crear un pedido, aparecerá aquí.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
