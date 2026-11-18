<section class="w-full space-y-6">
    <header class="flex flex-col gap-4 border-b border-slate-200/80 pb-6 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <div class="mb-1 flex flex-wrap items-center gap-2 text-xs font-mono text-slate-400">
                <span>PORTAL DE PROVEEDORES</span>
                <span aria-hidden="true">/</span>
                <span class="text-slate-600">SITSSA</span>
            </div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900 sm:text-[28px]">Buen día, {{ $firstName }}</h1>
            <p class="mt-0.5 text-sm text-slate-500">Resumen de tus órdenes asignadas.</p>
        </div>

        <button
            type="button"
            wire:click="goTo('provider.purchase_orders')"
            class="inline-flex items-center justify-center gap-2 self-start rounded-xl bg-brand-900 px-4 py-2.5 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-900/30 focus:ring-offset-2 lg:self-auto"
        >
            <span>Ver mis órdenes</span>
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/>
            </svg>
        </button>
    </header>

    <section aria-label="Indicadores de órdenes" class="grid grid-cols-1 divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200/80 bg-white md:grid-cols-3 md:divide-x md:divide-y-0">
        <article class="flex items-start justify-between gap-4 p-5 transition-colors hover:bg-slate-50/50 sm:px-6">
            <div>
                <p class="text-xs font-medium text-slate-500">Órdenes pendientes</p>
                <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ $statusCounts['pending'] }}</p>
                <p class="mt-1.5 text-xs text-slate-500">Requieren confirmación de recepción</p>
            </div>
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-amber-200/70 bg-amber-50 text-amber-600">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"/>
                </svg>
            </span>
        </article>
        <article class="flex items-start justify-between gap-4 p-5 transition-colors hover:bg-slate-50/50 sm:px-6">
            <div>
                <p class="text-xs font-medium text-slate-500">En proceso</p>
                <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ $statusCounts['inProgress'] }}</p>
                <p class="mt-1.5 text-xs text-slate-500">Marcadas como vistas</p>
            </div>
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-indigo-200/70 bg-indigo-50 text-indigo-600">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12h13m-5-5 5 5-5 5m5-5h5"/>
                </svg>
            </span>
        </article>
        <article class="flex items-start justify-between gap-4 p-5 transition-colors hover:bg-slate-50/50 sm:px-6">
            <div>
                <p class="text-xs font-medium text-slate-500">Facturas por revisar</p>
                <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ $statusCounts['invoicesForReview'] }}</p>
                <p class="mt-1.5 text-xs text-slate-500">Enviadas a SITSSA</p>
            </div>
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-emerald-200/70 bg-emerald-50 text-emerald-600">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 5h8l4 4v10H4V5h4zm0 0v5h8V5m-8 9h8m-8 3h5"/>
                </svg>
            </span>
        </article>
    </section>

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
        <section aria-label="Órdenes asignadas recientes" class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white lg:col-span-8">
            <header class="flex flex-col gap-3 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Órdenes asignadas recientes</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Tus últimas órdenes de compra asignadas.</p>
                </div>
                <span class="inline-flex items-center gap-1.5 self-start rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-[11px] font-mono text-slate-500 sm:self-auto">
                    {{ $statusCounts['all'] }} {{ $statusCounts['all'] === 1 ? 'orden en total' : 'órdenes en total' }}
                </span>
            </header>

            @if($recentOrders->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/70 text-[11px] uppercase tracking-wider text-slate-400">
                                <th scope="col" class="whitespace-nowrap py-3 pl-5 pr-3 font-medium sm:pl-6">Orden / Repuesto</th>
                                <th scope="col" class="whitespace-nowrap px-3 py-3 font-medium">Unidades</th>
                                <th scope="col" class="whitespace-nowrap px-3 py-3 font-medium">Estado</th>
                                <th scope="col" class="whitespace-nowrap py-3 pl-3 pr-5 font-medium sm:pr-6">Actualizada</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @foreach($recentOrders as $order)
                                @php
                                    [$statusLabel, $statusClass] = match ($order->status) {
                                        'pendiente' => ['Pendiente', 'border-amber-200 bg-amber-50 text-amber-700'],
                                        'en_proceso' => ['En proceso', 'border-indigo-200 bg-indigo-50 text-indigo-700'],
                                        'factura_subida', 'por_revisar' => ['Factura por revisar', 'border-blue-200 bg-blue-50 text-blue-700'],
                                        'aprobado' => ['Facturada', 'border-emerald-200 bg-emerald-50 text-emerald-700'],
                                        'rechazado' => ['Rechazada', 'border-rose-200 bg-rose-50 text-rose-700'],
                                        default => [ucfirst(str_replace('_', ' ', $order->status)), 'border-slate-200 bg-slate-100 text-slate-700'],
                                    };
                                @endphp
                                <tr wire:key="provider-dashboard-order-{{ $order->id }}" class="transition-colors hover:bg-slate-50/70">
                                    <td class="py-3.5 pl-5 pr-3 sm:pl-6">
                                        <span class="block font-mono text-[11px] font-semibold text-brand-900">OC-{{ $order->id }}</span>
                                        @foreach($order->displayItems() as $orderItem)
                                            <span class="mt-0.5 block max-w-xs truncate font-medium text-slate-800" title="{{ $orderItem->descripcion }}">
                                                {{ $orderItem->descripcion }} × {{ $orderItem->cantidad }}
                                            </span>
                                            @if($orderItem->detalles)
                                                <span class="block max-w-xs truncate text-[11px] text-slate-500">{{ $orderItem->detalles }}</span>
                                            @endif
                                        @endforeach
                                        @if($order->solicitado_por)
                                            <span class="mt-1 block max-w-xs truncate text-[11px] text-slate-500">
                                                Solicitada por: {{ $order->solicitado_por }}
                                            </span>
                                        @endif
                                        <span class="block max-w-xs truncate text-[11px] text-slate-500">
                                            Emitida por: {{ $order->pedido_por ?: '—' }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3.5">
                                        <span class="rounded-md border border-slate-200 bg-white px-2 py-1 font-mono text-xs font-semibold text-slate-700">{{ $order->cantidad }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3.5">
                                        <span @class(['inline-flex rounded-full border px-2.5 py-1 text-[11px] font-semibold', $statusClass])>{{ $statusLabel }}</span>
                                    </td>
                                    <td class="whitespace-nowrap py-3.5 pl-3 pr-5 text-xs text-slate-500 sm:pr-6" title="{{ $order->updated_at?->format('d/m/Y H:i') }}">
                                        {{ $order->updated_at?->locale('es')->diffForHumans() ?? 'Sin fecha' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <footer class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 bg-slate-50/70 px-5 py-3.5 text-[11px] text-slate-500 sm:px-6">
                    <span>Mostrando {{ $recentOrders->count() }} de {{ $statusCounts['all'] }} órdenes asignadas</span>
                    <button type="button" wire:click="goTo('provider.purchase_orders')" class="font-semibold text-brand-900 underline-offset-2 hover:underline">
                        Ir a mis órdenes
                    </button>
                </footer>
            @else
                <div class="px-6 py-12 text-center">
                    <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100 text-slate-400">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 5h8l4 4v10H4V5h4zm0 0v5h8V5m-8 9h8"/>
                        </svg>
                    </span>
                    <h3 class="mt-3 text-sm font-semibold text-slate-800">Todavía no tienes órdenes asignadas</h3>
                    <p class="mx-auto mt-1 max-w-sm text-xs leading-relaxed text-slate-500">Cuando SITSSA asigne una orden a tu cuenta, aparecerá aquí para que puedas revisarla.</p>
                </div>
            @endif
        </section>

        <aside class="space-y-6 lg:col-span-4">
            <section aria-label="Resumen de órdenes" class="rounded-2xl border border-slate-200/80 bg-white p-5 sm:p-6">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">Resumen de estados</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Estado actual de tus órdenes.</p>
                    </div>
                    <span class="rounded-md bg-slate-100 px-2 py-1 text-[10px] font-mono font-semibold tracking-wide text-slate-500">SITSSA</span>
                </div>
                <dl class="mt-3 divide-y divide-slate-100">
                    <div class="flex items-center justify-between gap-3 py-2.5 text-xs">
                        <dt class="text-slate-600">Total asignadas</dt>
                        <dd class="font-mono font-semibold text-slate-900">{{ $statusCounts['all'] }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3 py-2.5 text-xs">
                        <dt class="text-slate-600">Facturas aprobadas</dt>
                        <dd class="font-mono font-semibold text-emerald-700">{{ $statusCounts['approved'] }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3 py-2.5 text-xs">
                        <dt class="text-slate-600">Rechazadas</dt>
                        <dd class="font-mono font-semibold text-rose-700">{{ $statusCounts['rejected'] }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-2xl border border-slate-200/80 bg-white p-5 sm:p-6">
                <h2 class="text-sm font-semibold text-slate-900">Acciones para tus órdenes</h2>
                <p class="mt-0.5 text-xs text-slate-500">Revisa los pedidos y actualiza la documentación pendiente.</p>
                <ul class="mt-4 space-y-3 text-xs leading-relaxed text-slate-600">
                    <li class="flex gap-2.5">
                        <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500"></span>
                        Marca como vista una orden nueva para confirmar que la recibiste.
                    </li>
                    <li class="flex gap-2.5">
                        <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-700"></span>
                        Sube la factura correspondiente a la orden cuando esté disponible.
                    </li>
                </ul>
                <button
                    type="button"
                    wire:click="goTo('provider.purchase_orders')"
                    class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-brand-900/15 bg-brand-900/5 px-3.5 py-2.5 text-xs font-semibold text-brand-900 transition hover:bg-brand-900/10 focus:outline-none focus:ring-2 focus:ring-brand-900/20"
                >
                    Gestionar mis órdenes
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/>
                    </svg>
                </button>
            </section>
        </aside>
    </div>
</section>
