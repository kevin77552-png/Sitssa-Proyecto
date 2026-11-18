<div x-data="{ showInvoice: false, invoiceUrl: '', invoiceExt: '' }" class="w-full overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm">
    <header class="flex flex-col gap-4 border-b border-slate-100 bg-white px-6 py-6 sm:flex-row sm:items-center sm:justify-between sm:px-8">
        <div class="flex items-start gap-3.5">
            <div class="mt-0.5 shrink-0 rounded-lg border border-[#4A0C12]/10 bg-[#4A0C12]/5 p-2.5 text-[#4A0C12]">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-xl font-bold tracking-tight text-slate-900">Órdenes de compra</h1>
                    @if($unassignedOrders > 0)
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-200/80 bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700">
                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-500"></span>
                            {{ $unassignedOrders }} {{ $unassignedOrders === 1 ? 'orden sin proveedor' : 'órdenes sin proveedor' }}
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200/80 bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            Todas tienen proveedor
                        </span>
                    @endif
                </div>
                <p class="mt-0.5 text-sm text-slate-500">Consulta los pedidos y asigna proveedores pendientes desde la tabla.</p>
            </div>
        </div>

        <a href="{{ route('dashboard', ['module' => 'admin.purchase_orders']) }}"
            class="inline-flex cursor-pointer items-center gap-2 self-start rounded-lg bg-[#4A0C12] px-4 py-2.5 text-xs font-medium text-white shadow-sm shadow-[#4A0C12]/20 transition hover:bg-[#3A090E] sm:self-auto">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nuevo pedido
        </a>
    </header>

    <section class="flex flex-col justify-between gap-3 border-b border-slate-200/80 bg-slate-50/80 px-6 py-4 md:flex-row md:items-center sm:px-8">
        <div class="relative w-full flex-1 md:max-w-sm">
            <label for="purchase-order-search" class="sr-only">Buscar órdenes de compra</label>
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <input id="purchase-order-search" type="search" wire:model.live.debounce.300ms="search"
                placeholder="Buscar por N.º, solicitante, artículo o proveedor..."
                class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-9 pr-3.5 text-xs text-slate-800 transition placeholder:text-slate-400 focus:border-[#4A0C12] focus:outline-none focus:ring-2 focus:ring-[#4A0C12]/10">
        </div>

        <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Filtrar por asignación de proveedor">
            <button type="button" wire:click="$set('providerFilter', '')"
                @class([
                    'inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition',
                    'bg-slate-800 text-white shadow-sm' => $providerFilter === '',
                    'border border-slate-200 bg-white text-slate-600 hover:bg-slate-100' => $providerFilter !== '',
                ])>
                Todas ({{ $totalOrders }})
            </button>
            <button type="button" wire:click="$set('providerFilter', 'unassigned')"
                @class([
                    'inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition',
                    'bg-slate-800 text-white shadow-sm' => $providerFilter === 'unassigned',
                    'border border-slate-200 bg-white text-slate-600 hover:bg-slate-100' => $providerFilter !== 'unassigned',
                ])>
                <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                Sin proveedor ({{ $unassignedOrders }})
            </button>
            <button type="button" wire:click="$set('providerFilter', 'assigned')"
                @class([
                    'inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition',
                    'bg-slate-800 text-white shadow-sm' => $providerFilter === 'assigned',
                    'border border-slate-200 bg-white text-slate-600 hover:bg-slate-100' => $providerFilter !== 'assigned',
                ])>
                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                Con proveedor ({{ $assignedOrders }})
            </button>
        </div>
    </section>

    <p class="border-b border-slate-100 bg-white px-6 py-2.5 text-xs text-slate-500 sm:px-8">
        El filtro indica si se asignó un proveedor; el estado de la orden se muestra por separado.
    </p>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[980px] border-collapse text-left">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50/50 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                    <th scope="col" class="py-3.5 pl-6 pr-3">N.º</th>
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
                        <td class="whitespace-nowrap py-4 pl-6 pr-3">
                            <span class="rounded border border-[#4A0C12]/10 bg-[#4A0C12]/5 px-2.5 py-1 font-mono text-xs font-bold text-[#4A0C12]">#{{ $order->id }}</span>
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-xs font-medium text-slate-700">{{ $order->pedido_por ?: '—' }}</td>
                        <td class="px-3 py-4">
                            @foreach($order->displayItems() as $orderItem)
                                <div class="text-xs text-slate-900 sm:text-sm">
                                    <span class="font-semibold">{{ $orderItem->descripcion }}</span>
                                    <span class="font-mono text-slate-500">× {{ $orderItem->cantidad }}</span>
                                </div>
                                @if($orderItem->detalles)
                                    <div class="text-[11px] text-slate-500">{{ $orderItem->detalles }}</div>
                                @endif
                            @endforeach
                            @if($order->solicitado_por)
                                <div class="mt-1 text-[11px] text-slate-500">Solicitada por: {{ $order->solicitado_por }}</div>
                            @endif
                            @if($order->detalles)
                                <div class="mt-0.5 text-[11px] text-slate-500">{{ $order->detalles }}</div>
                            @endif
                            <div class="mt-1 text-[11px] text-slate-400">{{ $order->created_at->locale('es')->diffForHumans() }}</div>
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-center">
                            <span class="rounded bg-slate-100 px-2.5 py-1 font-mono text-xs font-semibold text-slate-700">{{ $order->cantidad }}</span>
                        </td>
                        <td class="whitespace-nowrap px-3 py-4">
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
                        <td class="py-4 pl-3 pr-6">
                            @if($order->provider_id)
                                <div class="flex items-center gap-2">
                                    <span class="h-2 w-2 shrink-0 rounded-full bg-emerald-500"></span>
                                    <span class="text-xs font-medium text-slate-800">{{ $order->provider?->name ?? 'Proveedor inactivo o eliminado' }}</span>
                                </div>
                            @else
                                @if($providers->isNotEmpty())
                                    <div class="flex w-full max-w-xs items-center">
                                        <select wire:model="providerIds.{{ $order->id }}" aria-label="Seleccionar proveedor para la orden {{ $order->id }}"
                                            class="min-w-0 flex-1 rounded-l-lg border border-r-0 border-slate-300 bg-slate-50/70 px-3 py-1.5 text-xs text-slate-700 focus:border-[#4A0C12] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#4A0C12]/10">
                                            <option value="">Seleccionar proveedor...</option>
                                            @foreach($providers as $provider)
                                                <option value="{{ $provider->id }}">{{ $provider->name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="button" wire:click="assignProvider({{ $order->id }})" wire:loading.attr="disabled" wire:target="assignProvider({{ $order->id }})"
                                            class="shrink-0 cursor-pointer rounded-r-lg border border-[#4A0C12] bg-[#4A0C12] px-3.5 py-1.5 text-xs font-medium text-white transition hover:bg-[#3A090E] disabled:cursor-wait disabled:opacity-70">
                                            Asignar
                                        </button>
                                    </div>
                                    <x-input-error :messages="$errors->get('providerIds.' . $order->id)" class="mt-1" />
                                @else
                                    <span class="text-xs text-slate-500">No hay proveedores activos disponibles.</span>
                                @endif
                            @endif
                            @if($order->attachment)
                                <button type="button"
                                    @click="invoiceUrl='{{ route('purchase-orders.invoice', $order) }}'; invoiceExt='{{ strtolower(pathinfo($order->attachment, PATHINFO_EXTENSION)) }}'; showInvoice=true"
                                    class="mt-2 text-xs font-medium text-[#4A0C12] hover:underline">
                                    Ver factura
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center">
                            <p class="text-sm font-semibold text-slate-900">No se encontraron órdenes de compra</p>
                            <p class="mt-1 text-xs text-slate-500">No hay registros que coincidan con la búsqueda o el filtro seleccionado.</p>
                            @if($search !== '' || $providerFilter !== '')
                                <button type="button" wire:click="clearFilters" class="mt-4 cursor-pointer rounded-lg bg-[#4A0C12]/5 px-4 py-2 text-xs font-medium text-[#4A0C12] transition hover:bg-[#4A0C12]/10">
                                    Ver todas las órdenes
                                </button>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <footer class="flex flex-col gap-3 border-t border-slate-200/80 bg-slate-50/70 px-6 py-3.5 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-8">
        <span>
            @if($orders->total() > 0)
                Mostrando <strong class="font-mono text-slate-800">{{ $orders->firstItem() }}–{{ $orders->lastItem() }}</strong>
                de <strong class="font-mono text-slate-800">{{ $orders->total() }}</strong> órdenes
            @else
                Mostrando <strong class="font-mono text-slate-800">0</strong> órdenes
            @endif
        </span>
        <span class="hidden text-slate-400 sm:inline">Selecciona un proveedor y pulsa <strong>Asignar</strong> para completar la asignación.</span>
    </footer>
    @if($orders->hasPages())
        <div class="border-t border-slate-100 px-6 py-3 sm:px-8">{{ $orders->links() }}</div>
    @endif

    <div x-show="showInvoice" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @keydown.escape.window="showInvoice=false">
        <div class="max-h-[90vh] w-full overflow-auto rounded-xl bg-white p-4 shadow-lg md:w-3/4 lg:w-2/3">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-semibold text-slate-900">Factura</h2>
                <div class="flex gap-2">
                    <a x-bind:href="invoiceUrl + '?download=1'" target="_blank" rel="noopener noreferrer" class="rounded-lg px-3 py-2 text-sm font-medium text-[#4A0C12] hover:bg-[#4A0C12]/5">Descargar</a>
                    <button type="button" @click="showInvoice=false" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Cerrar</button>
                </div>
            </div>
            <template x-if="invoiceExt === 'pdf'">
                <iframe :src="invoiceUrl" title="Factura PDF" class="h-[70vh] w-full"></iframe>
            </template>
            <template x-if="invoiceExt !== 'pdf'">
                <img :src="invoiceUrl" alt="Factura" class="mx-auto max-h-[75vh] w-auto">
            </template>
        </div>
    </div>
</div>
