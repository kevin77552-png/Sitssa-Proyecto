<div class="mx-auto w-full space-y-6">
    <header class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white px-6 py-6 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:px-8">
        <div class="flex items-start gap-3.5">
            <div class="mt-0.5 shrink-0 rounded-lg border border-[#4A0C12]/10 bg-[#4A0C12]/5 p-2.5 text-[#4A0C12]">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
            </div>
            <div>
                <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Órdenes de compra</h1>
                <p class="mt-1 text-sm text-slate-500">Consulta las órdenes asignadas a tu cuenta y envía sus facturas.</p>
            </div>
        </div>
        <span class="inline-flex items-center gap-2 self-start rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2 text-xs font-medium text-slate-600 sm:self-auto">
            <span class="h-2 w-2 rounded-full bg-[#4A0C12]"></span>
            Portal de proveedor
        </span>
    </header>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            {{ session('error') }}
        </div>
    @endif

    <section aria-label="Resumen de órdenes" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total de órdenes</p>
                <p class="mt-1 text-3xl font-bold text-slate-900">{{ $statusCounts['all'] }}</p>
                <p class="mt-1 text-xs text-slate-500">Asignadas a tu cuenta</p>
            </div>
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[#4A0C12]/5 text-[#4A0C12]">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
        </div>
        <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Pendientes</p>
                <p class="mt-1 text-3xl font-bold text-amber-600">{{ $statusCounts['pendiente'] }}</p>
                <p class="mt-1 text-xs text-slate-500">Requieren revisión</p>
            </div>
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
        <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Facturas enviadas</p>
                <p class="mt-1 text-3xl font-bold text-emerald-600">{{ $statusCounts['factura_subida'] }}</p>
                <p class="mt-1 text-xs text-slate-500">Pendientes de revisión</p>
            </div>
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
    </section>

    <section aria-label="Órdenes asignadas" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-5 sm:px-6">
            <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
                    <h2 class="text-lg font-bold text-slate-900">Órdenes recibidas</h2>
                    <div class="inline-flex max-w-full gap-1 overflow-x-auto rounded-lg bg-slate-100 p-1" role="group" aria-label="Filtrar órdenes por estado">
                        @foreach([
                            ['all', 'Todas', $statusCounts['all']],
                            ['pendiente', 'Pendientes', $statusCounts['pendiente']],
                            ['en_proceso', 'En proceso', $statusCounts['en_proceso']],
                            ['factura_subida', 'Facturas enviadas', $statusCounts['factura_subida']],
                            ['rechazado', 'Rechazadas', $statusCounts['rechazado']],
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
                </div>

                <label class="relative w-full lg:w-72">
                    <span class="sr-only">Buscar órdenes</span>
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Buscar orden, repuesto o detalle..."
                        class="w-full rounded-lg border border-slate-300 bg-slate-50 py-2 pl-9 pr-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-[#4A0C12] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#4A0C12]/10"
                    >
                </label>
            </div>
        </div>

        <div class="space-y-4 bg-slate-50/60 p-4 sm:p-6">
            @forelse($orders as $order)
                @php
                    $statusLabels = [
                        'pendiente' => 'Pendiente',
                        'en_proceso' => 'En proceso',
                        'aprobado' => 'Aprobada',
                        'factura_subida' => 'Factura enviada · pendiente de revisión',
                        'rechazado' => 'Rechazada',
                    ];
                    $statusStyles = [
                        'pendiente' => 'border-amber-200 bg-amber-50 text-amber-700',
                        'en_proceso' => 'border-slate-200 bg-slate-100 text-slate-700',
                        'aprobado' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                        'factura_subida' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                        'rechazado' => 'border-red-200 bg-red-50 text-red-700',
                    ];
                @endphp
                <article
                    wire:key="provider-order-{{ $order->id }}"
                    @class([
                        'rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-shadow hover:shadow-md sm:p-5',
                        'border-l-4 border-l-amber-400' => $order->status === 'pendiente',
                    ])
                >
                    <div class="grid grid-cols-1 items-center gap-4 lg:grid-cols-12 lg:gap-6">
                        <div class="flex items-start gap-3.5 lg:col-span-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#4A0C12]/5 text-xs font-bold text-[#4A0C12]">
                                OC
                            </div>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-md bg-slate-900 px-2 py-0.5 text-xs font-bold tracking-wide text-white">#{{ $order->id }}</span>
                                    <h3 class="truncate text-sm font-bold text-slate-900">Orden de compra</h3>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">Emitida por: {{ $order->pedido_por ?: '—' }}</p>
                                @if($order->solicitado_por)
                                    <p class="text-xs text-slate-500">Solicitada por: {{ $order->solicitado_por }}</p>
                                @endif
                                <p class="mt-1 text-xs text-slate-400">Recibida {{ $order->created_at->locale('es')->diffForHumans() }}</p>
                            </div>
                        </div>

                        <div class="flex min-w-0 items-center justify-between gap-3 rounded-lg border border-slate-100 bg-slate-50/80 p-3 lg:col-span-5">
                            <div class="min-w-0">
                                @foreach($order->displayItems() as $orderItem)
                                    <p class="text-sm font-bold text-slate-800">
                                        {{ $orderItem->descripcion }}
                                        <span class="font-mono text-xs font-medium text-slate-500">× {{ $orderItem->cantidad }}</span>
                                    </p>
                                    @if($orderItem->detalles)
                                        <p class="text-xs text-slate-500">{{ $orderItem->detalles }}</p>
                                    @endif
                                @endforeach
                                @if($order->detalles)
                                    <p class="mt-1 line-clamp-2 text-xs text-slate-500">{{ $order->detalles }}</p>
                                @endif
                            </div>
                            <span class="shrink-0 rounded-md border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700">
                                Unidades: {{ $order->cantidad }}
                            </span>
                        </div>

                        <div class="flex flex-col gap-3 border-t border-slate-100 pt-3 sm:flex-row sm:items-center sm:justify-between lg:col-span-4 lg:flex-col lg:items-end lg:border-0 lg:pt-0 xl:flex-row xl:items-center">
                            <span @class([
                                'inline-flex items-center gap-1.5 self-start rounded-full border px-3 py-1 text-xs font-semibold sm:self-auto',
                                $statusStyles[$order->status] ?? 'border-slate-200 bg-slate-100 text-slate-700',
                            ])>
                                <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                                {{ $statusLabels[$order->status] ?? ucfirst(str_replace('_', ' ', $order->status)) }}
                            </span>

                            <div class="flex flex-wrap items-center gap-2">
                                @if($order->status === 'pendiente')
                                    <button
                                        type="button"
                                        wire:click="markAsSeen({{ $order->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="markAsSeen({{ $order->id }})"
                                        class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-[#4A0C12]/30 hover:bg-[#4A0C12]/5 hover:text-[#4A0C12]"
                                    >
                                        Marcar como vista
                                    </button>
                                @endif

                                @if(in_array($order->status, ['pendiente', 'en_proceso', 'rechazado'], true) && (! $order->attachment || $order->status === 'rechazado'))
                                    <button
                                        type="button"
                                        wire:click="openUploadModal({{ $order->id }})"
                                        class="inline-flex items-center justify-center rounded-lg bg-[#4A0C12] px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-[#64151d] focus:outline-none focus:ring-2 focus:ring-[#4A0C12]/30 focus:ring-offset-2"
                                    >
                                        {{ $order->attachment ? 'Reemplazar factura' : 'Subir factura' }}
                                    </button>
                                @endif

                                @if($order->attachment)
                                    <a
                                        href="{{ route('purchase-orders.invoice', $order) }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100"
                                    >
                                        Ver factura
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if($order->status === 'rechazado' && $order->reject_reason)
                        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3.5 py-2.5 text-sm text-red-800">
                            <span class="font-semibold">Motivo del rechazo:</span> {{ $order->reject_reason }}
                        </div>
                    @endif
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-400">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3 class="mt-3 text-sm font-semibold text-slate-900">
                        {{ $statusCounts['all'] === 0 ? 'Todavía no tienes órdenes asignadas' : 'No se encontraron órdenes' }}
                    </h3>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ $statusCounts['all'] === 0 ? 'Las nuevas órdenes asignadas aparecerán aquí.' : 'Prueba con otro filtro o término de búsqueda.' }}
                    </p>
                    @if($statusFilter !== 'all' || trim($search) !== '')
                        <button type="button" wire:click="clearFilters" class="mt-4 rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                            Limpiar filtros
                        </button>
                    @endif
                </div>
            @endforelse
        </div>
    </section>

    @if($uploadingOrderId && $modalOrder)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="invoice-modal-title">
            <div class="w-full max-w-lg overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
                <header class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                            </svg>
                        </div>
                        <div>
                            <h2 id="invoice-modal-title" class="text-base font-bold text-slate-900">{{ $modalOrder->attachment ? 'Reemplazar factura' : 'Subir factura' }}</h2>
                            <p class="text-xs text-slate-500">Orden #{{ $modalOrder->id }}</p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeUploadModal" aria-label="Cerrar" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </header>

                <div class="space-y-4 p-6">
                    <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3.5">
                        <div class="min-w-0">
                            <span class="block text-xs text-slate-400">Repuesto</span>
                            <span class="block truncate text-sm font-semibold text-slate-800">{{ $modalOrder->descripcion }}</span>
                        </div>
                        <span class="shrink-0 rounded-md border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700">Cant. {{ $modalOrder->cantidad }}</span>
                    </div>

                    @if($modalOrder->attachment)
                        <a href="{{ route('purchase-orders.invoice', $modalOrder) }}" target="_blank" rel="noopener noreferrer" class="inline-flex text-sm font-medium text-[#4A0C12] underline underline-offset-2 hover:text-[#64151d]">
                            Ver factura actual
                        </a>
                    @endif

                    <label for="provider-invoice-{{ $uploadingOrderId }}" class="block cursor-pointer rounded-xl border-2 border-dashed border-slate-300 bg-slate-50/60 px-5 py-8 text-center transition hover:border-[#4A0C12]/50 hover:bg-[#4A0C12]/5">
                        <svg class="mx-auto h-8 w-8 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <span class="mt-2 block text-sm font-semibold text-slate-700">Selecciona la factura para esta orden</span>
                        <span class="mt-1 block text-xs text-slate-500">PDF, JPG o PNG · Máximo 3 MB</span>
                        <input id="provider-invoice-{{ $uploadingOrderId }}" type="file" wire:model="attachments.{{ $uploadingOrderId }}" accept=".pdf,.jpg,.jpeg,.png" class="sr-only">
                    </label>

                    @if(isset($attachments[$uploadingOrderId]))
                        <p class="truncate text-xs text-slate-600">{{ $attachments[$uploadingOrderId]->getClientOriginalName() }}</p>
                    @endif
                    <x-input-error :messages="$errors->get('attachments.' . $uploadingOrderId)" />
                    <div wire:loading wire:target="attachments.{{ $uploadingOrderId }}" class="text-xs text-slate-500">Cargando archivo...</div>
                </div>

                <footer class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/70 px-6 py-4">
                    <button type="button" wire:click="closeUploadModal" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100">
                        Cancelar
                    </button>
                    @if($modalOrder->attachment)
                        <button
                            type="button"
                            @click.prevent="Swal.fire({
                                title: 'Confirmar reemplazo',
                                text: 'Se reemplazará la factura actual. ¿Deseas continuar?',
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonText: 'Sí, reemplazar',
                                cancelButtonText: 'Cancelar'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    $wire.uploadInvoice({{ $uploadingOrderId }});
                                }
                            })"
                            class="rounded-lg bg-[#4A0C12] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#64151d]"
                        >
                            Reemplazar factura
                        </button>
                    @else
                        <button type="button" wire:click="uploadInvoice({{ $uploadingOrderId }})" class="rounded-lg bg-[#4A0C12] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#64151d]">
                            Enviar factura
                        </button>
                    @endif
                </footer>
            </div>
        </div>
    @endif
</div>
