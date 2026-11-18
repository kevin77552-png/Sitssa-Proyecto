<div x-data="{ showInvoice: false, invoiceUrl: '', invoiceExt: '', invoiceOrderId: null, invoiceStatus: '' }" class="space-y-6">
    <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Órdenes de compra</h1>
            <p class="mt-1 text-sm text-slate-500">Revisa facturas, estados y proveedores de cada orden.</p>
        </div>
        <button
            type="button"
            wire:click="exportCsv"
            wire:loading.attr="disabled"
            wire:target="exportCsv"
            class="inline-flex items-center gap-2 self-start rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 disabled:cursor-wait disabled:opacity-60 sm:self-auto"
        >
            <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 5.707V19a2 2 0 01-2 2z"/>
            </svg>
            <span wire:loading.remove wire:target="exportCsv">Exportar reporte</span>
            <span wire:loading wire:target="exportCsv">Preparando...</span>
        </button>
    </header>

    @if(session('message'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
            {{ session('message') }}
        </div>
    @endif

    <section aria-label="Indicadores clave" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total de órdenes</p>
                <p class="mt-1 text-3xl font-bold text-slate-900">{{ $totalOrders }}</p>
                <p class="mt-1 text-xs text-slate-500">Registradas en el sistema</p>
            </div>
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
        </div>

        <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Sin factura</p>
                <p class="mt-1 text-3xl font-bold text-amber-600">{{ $ordersWithoutInvoice }}</p>
                <p class="mt-1 text-xs text-slate-500">Pendientes por adjuntar</p>
            </div>
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-amber-100 bg-amber-50 text-amber-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
        </div>

        <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Facturadas</p>
                <p class="mt-1 text-3xl font-bold text-emerald-600">{{ $invoicedOrders }}</p>
                <p class="mt-1 text-xs text-slate-500">Comprobante verificado</p>
            </div>
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-emerald-100 bg-emerald-50 text-emerald-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>

        <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Sin proveedor</p>
                <p class="mt-1 text-3xl font-bold text-[#4A0C12]">{{ $ordersWithoutProvider }}</p>
                <p class="mt-1 text-xs text-slate-500">Requieren asignación</p>
            </div>
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-[#4A0C12]/10 bg-[#4A0C12]/5 text-[#4A0C12]">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
        </div>
    </section>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col justify-between gap-4 border-b border-slate-200 px-4 py-4 sm:px-5 lg:flex-row lg:items-center">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="inline-flex max-w-full gap-1 overflow-x-auto rounded-lg bg-slate-100 p-1" role="group" aria-label="Filtrar órdenes">
                    @foreach([
                        ['all', 'Todas', $totalOrders],
                        ['sin_factura', 'Sin factura', $ordersWithoutInvoice],
                        ['por_revisar', 'Por revisar', $pendingReviewOrders],
                        ['facturada', 'Facturadas', $invoicedOrders],
                        ['sin_proveedor', 'Sin proveedor', $ordersWithoutProvider],
                    ] as [$filter, $label, $count])
                        <button
                            type="button"
                            wire:click="$set('statusFilter', '{{ $filter }}')"
                            aria-pressed="{{ $statusFilter === $filter ? 'true' : 'false' }}"
                            @class([
                                'shrink-0 rounded-md px-3 py-1.5 text-xs transition',
                                'bg-[#4A0C12] font-semibold text-white shadow-sm' => $statusFilter === $filter,
                                'font-medium text-slate-600 hover:bg-white hover:text-slate-900' => $statusFilter !== $filter,
                            ])
                        >
                            {{ $label }} <span class="{{ $statusFilter === $filter ? 'text-white/70' : 'text-slate-400' }}">{{ $count }}</span>
                        </button>
                    @endforeach
                </div>

                <label class="flex items-center gap-2 text-xs text-slate-500">
                    <span class="whitespace-nowrap">Por página</span>
                    <select wire:model.live="perPage" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-700 focus:border-[#4A0C12] focus:outline-none focus:ring-2 focus:ring-[#4A0C12]/10">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </label>
            </div>

            <label class="relative w-full lg:w-80">
                <span class="sr-only">Buscar órdenes de compra</span>
                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Buscar N.º, descripción, solicitante o proveedor..."
                    class="w-full rounded-lg border border-slate-300 bg-slate-50 py-2 pl-10 pr-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-[#4A0C12] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#4A0C12]/10"
                >
            </label>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1050px] border-collapse text-left">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                        <th scope="col" class="w-24 py-3.5 pl-6 pr-3">N.º</th>
                        <th scope="col" class="w-44 px-4 py-3.5">Emitida por</th>
                        <th scope="col" class="min-w-[240px] px-4 py-3.5">Descripción</th>
                        <th scope="col" class="w-24 px-4 py-3.5 text-center">Unidades</th>
                        <th scope="col" class="w-36 px-4 py-3.5">Estado</th>
                        <th scope="col" class="min-w-[230px] px-4 py-3.5">Proveedor</th>
                        <th scope="col" class="w-40 py-3.5 pl-4 pr-6 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @foreach($orders as $order)
                        @php
                            if (! $order->attachment) {
                                [$statusLabel, $statusStyle] = ['Sin factura', 'border-amber-200/80 bg-amber-50 text-amber-700'];
                            } else {
                                [$statusLabel, $statusStyle] = match ($order->status) {
                                    'aprobado' => ['Facturada', 'border-emerald-200/80 bg-emerald-50 text-emerald-700'],
                                    'factura_subida', 'por_revisar' => ['Por revisar', 'border-amber-200/80 bg-amber-50 text-amber-700'],
                                    'rechazado' => ['Rechazada', 'border-rose-200/80 bg-rose-50 text-rose-700'],
                                    default => ['Factura recibida', 'border-slate-200 bg-slate-100 text-slate-700'],
                                };
                            }
                        @endphp
                        <tr wire:key="manager-purchase-order-{{ $order->id }}" class="transition-colors hover:bg-slate-50/90">
                            <td class="whitespace-nowrap py-4 pl-6 pr-3">
                                <span class="rounded-md border border-[#4A0C12]/10 bg-[#4A0C12]/5 px-2.5 py-1 font-mono text-xs font-bold text-[#4A0C12]">#{{ $order->id }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-xs font-medium text-slate-700">{{ $order->pedido_por ?: '—' }}</td>
                            <td class="px-4 py-4">
                                @foreach($order->displayItems() as $orderItem)
                                    <div class="font-semibold leading-snug text-slate-900">
                                        {{ $orderItem->descripcion }}
                                        <span class="font-mono text-xs font-normal text-slate-500">× {{ $orderItem->cantidad }}</span>
                                    </div>
                                    @if($orderItem->detalles)
                                        <div class="text-xs text-slate-500">{{ $orderItem->detalles }}</div>
                                    @endif
                                @endforeach
                                @if($order->solicitado_por)
                                    <div class="mt-1 text-xs text-slate-500">Solicitada por: {{ $order->solicitado_por }}</div>
                                @endif
                                @if($order->detalles)
                                    <div class="mt-1 text-xs text-slate-500">{{ $order->detalles }}</div>
                                @endif
                                @if($order->reject_reason)
                                    <div class="mt-1 text-xs text-rose-700">Motivo: {{ $order->reject_reason }}</div>
                                @endif
                                <div class="mt-1 text-[11px] text-slate-400">{{ $order->created_at->locale('es')->diffForHumans() }}</div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-center">
                                <span class="inline-flex min-w-8 justify-center rounded-md bg-slate-100 px-2 py-1 font-mono text-xs font-bold text-slate-700">{{ $order->cantidad }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4">
                                <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusStyle }}">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $statusLabel }}
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                @if($order->provider)
                                    <span class="font-medium text-slate-700">{{ $order->provider->name }}</span>
                                @else
                                    <button
                                        type="button"
                                        wire:click="openAssignProvider({{ $order->id }})"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-amber-200/80 bg-amber-50 px-2.5 py-1.5 text-xs font-semibold text-amber-800 transition hover:border-[#4A0C12]/30 hover:bg-[#4A0C12]/5 hover:text-[#4A0C12]"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                        </svg>
                                        Sin asignar
                                    </button>
                                @endif
                            </td>
                            <td class="whitespace-nowrap py-4 pl-4 pr-6 text-right">
                                @if($order->attachment)
                                    <button
                                        type="button"
                                        @click="invoiceUrl='{{ route('purchase-orders.invoice', $order) }}'; invoiceExt='{{ strtolower(pathinfo($order->attachment, PATHINFO_EXTENSION)) }}'; invoiceOrderId={{ $order->id }}; invoiceStatus='{{ $order->status }}'; showInvoice=true; @if($order->status === 'factura_subida') $wire.markAsPorRevisar({{ $order->id }}); @endif"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-[#4A0C12]/25 hover:bg-[#4A0C12]/5 hover:text-[#4A0C12]"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        Ver factura
                                    </button>
                                @else
                                    <span class="text-xs text-slate-400">Sin acciones</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($orders->isEmpty())
            <div class="px-6 py-12 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-400">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <h2 class="mt-3 text-sm font-semibold text-slate-900">No hay órdenes que coincidan</h2>
                <p class="mt-1 text-xs text-slate-500">Cambia la búsqueda o restablece los filtros activos.</p>
                @if($search !== '' || $statusFilter !== 'all')
                    <button type="button" wire:click="clearFilters" class="mt-4 rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                        Restablecer filtros
                    </button>
                @endif
            </div>
        @endif

        <footer class="flex flex-col items-center justify-between gap-2 border-t border-slate-100 bg-slate-50/60 px-6 py-3.5 text-xs text-slate-500 sm:flex-row">
            <span>Mostrando {{ $orders->count() }} de {{ $orders->total() }} órdenes</span>
            <span class="text-slate-400">Las órdenes sin factura o proveedor se pueden filtrar arriba.</span>
        </footer>
        @if($orders->hasPages())
            <div class="border-t border-slate-100 px-6 py-3">{{ $orders->links() }}</div>
        @endif
    </section>

    @if($assigningOrderId && $assigningOrder)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="assign-provider-title">
            <div class="w-full max-w-md overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
                <header class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <h2 id="assign-provider-title" class="text-base font-bold text-slate-900">Asignar proveedor</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Orden #{{ $assigningOrder->id }} · {{ $assigningOrder->descripcion }}</p>
                    </div>
                    <button type="button" wire:click="$set('assigningOrderId', null)" aria-label="Cerrar" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </header>
                <div class="space-y-4 p-6">
                    <label for="provider-select-{{ $assigningOrderId }}" class="block text-sm font-medium text-slate-700">Proveedor autorizado</label>
                    <select id="provider-select-{{ $assigningOrderId }}" wire:model="providerIds.{{ $assigningOrderId }}" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 focus:border-[#4A0C12] focus:outline-none focus:ring-2 focus:ring-[#4A0C12]/10">
                        <option value="">Selecciona un proveedor</option>
                        @foreach($providers as $provider)
                            <option value="{{ $provider->id }}">{{ $provider->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('providerIds.' . $assigningOrderId)" />
                    @if($providers->isEmpty())
                        <p class="text-sm text-amber-700">No hay proveedores activos disponibles.</p>
                    @endif
                </div>
                <footer class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/70 px-6 py-4">
                    <button type="button" wire:click="$set('assigningOrderId', null)" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100">
                        Cancelar
                    </button>
                    <button type="button" wire:click="assignProvider({{ $assigningOrderId }})" @disabled($providers->isEmpty()) class="rounded-lg bg-[#4A0C12] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#3A090E] disabled:cursor-not-allowed disabled:opacity-50">
                        Asignar proveedor
                    </button>
                </footer>
            </div>
        </div>
    @endif

    <div x-show="showInvoice" x-cloak
        x-init="Livewire.on('orderApproved', id => { if (invoiceOrderId == id) showInvoice = false }); Livewire.on('orderRejected', id => { if (invoiceOrderId == id) showInvoice = false })"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm"
        role="dialog" aria-modal="true" aria-label="Factura de orden de compra">
        <div class="max-h-[90vh] w-full max-w-4xl overflow-auto rounded-xl border border-slate-200 bg-white p-4 shadow-2xl sm:p-6">
            <div class="mb-4 flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h2 class="font-bold text-slate-900">Factura de orden de compra</h2>
                    <p class="mt-0.5 text-xs text-slate-500" x-text="invoiceOrderId ? `Orden #${invoiceOrderId}` : ''"></p>
                </div>
                <div class="flex gap-2">
                    <a x-bind:href="invoiceUrl + '?download=1'" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Descargar</a>
                    <button type="button" @click="showInvoice=false" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Cerrar</button>
                </div>
            </div>
            <template x-if="invoiceExt === 'pdf'">
                <iframe :src="invoiceUrl" title="Factura PDF" class="h-[60vh] w-full rounded-lg border border-slate-200"></iframe>
            </template>
            <template x-if="invoiceExt !== 'pdf'">
                <img :src="invoiceUrl" alt="Factura" class="mx-auto max-h-[60vh] w-auto">
            </template>
            <div class="mt-4 flex flex-wrap justify-end gap-2">
                <button
                    type="button"
                    x-show="invoiceOrderId && ['factura_subida', 'por_revisar'].includes(invoiceStatus)"
                    @click="const orderId = invoiceOrderId; showInvoice=false; invoiceOrderId=null; $wire.approve(orderId)"
                    class="rounded-lg bg-[#4A0C12] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#3A090E]"
                >
                    Aprobar orden
                </button>
                <button
                    type="button"
                    x-show="invoiceOrderId && ['factura_subida', 'por_revisar'].includes(invoiceStatus)"
                    @click="Swal.fire({
                        title: 'Rechazar orden',
                        text: 'Indica el motivo del rechazo:',
                        input: 'textarea',
                        inputPlaceholder: 'Escribe el motivo aquí...',
                        showCancelButton: true,
                        confirmButtonText: 'Confirmar rechazo',
                        cancelButtonText: 'Cancelar',
                        preConfirm: value => {
                            if (!value || value.trim().length < 5) {
                                Swal.showValidationMessage('El motivo debe tener al menos 5 caracteres');
                            }
                            return value;
                        }
                    }).then(result => {
                        if (result.isConfirmed) {
                            const orderId = invoiceOrderId;
                            showInvoice=false;
                            invoiceOrderId=null;
                            $wire.reject(orderId, result.value);
                        }
                    })"
                    class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-100"
                >
                    Rechazar orden
                </button>
            </div>
        </div>
    </div>
</div>
