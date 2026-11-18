<div class="w-full space-y-6">
    <header class="flex flex-col gap-4 rounded-xl border border-slate-200/90 bg-white px-6 py-5 shadow-sm sm:px-8 md:flex-row md:items-center md:justify-between">
        <div class="flex items-start gap-3.5">
            <div class="mt-0.5 shrink-0 rounded-lg border border-[#4A0C12]/10 bg-[#4A0C12]/5 p-2.5 text-[#4A0C12]">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-xl font-bold tracking-tight text-slate-900">Reportes operativos</h1>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                        <span class="h-1.5 w-1.5 rounded-full bg-[#4A0C12]"></span>
                        Datos del sistema
                    </span>
                </div>
                <p class="mt-0.5 text-sm text-slate-500">Resumen de solicitudes, órdenes de compra, facturación y alertas de inventario.</p>
            </div>
        </div>

        <button type="button" wire:click="export" wire:loading.attr="disabled" wire:target="export"
            class="inline-flex cursor-pointer items-center justify-center gap-1.5 self-start rounded-lg bg-[#4A0C12] px-3.5 py-2 text-xs font-medium text-white shadow-sm transition hover:bg-[#3A090E] disabled:cursor-wait disabled:opacity-70 md:self-auto">
            <svg wire:loading.remove wire:target="export" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            <span wire:loading.remove wire:target="export">Exportar CSV</span>
            <span wire:loading wire:target="export">Preparando...</span>
        </button>
    </header>

    <section class="grid grid-cols-1 gap-5 md:grid-cols-3" aria-label="Indicadores operativos">
        @php
            $reportKpis = [
                [
                    'title' => 'Solicitudes pendientes',
                    'value' => $pendingRequestCount,
                    'description' => 'Sin aprobar por gerencia',
                    'empty' => 'Al día',
                    'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
                ],
                [
                    'title' => 'Órdenes sin factura',
                    'value' => $openOrderCount,
                    'description' => 'Órdenes activas sin adjunto',
                    'empty' => 'Sin pendientes',
                    'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                ],
                [
                    'title' => 'Facturas por revisar',
                    'value' => $uploadedOrderCount,
                    'description' => 'Órdenes con factura subida',
                    'empty' => 'Sin pendientes',
                    'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
                ],
            ];
        @endphp
        @foreach($reportKpis as $kpi)
            <article class="flex flex-col justify-between rounded-xl border border-slate-200/90 bg-white p-6 shadow-sm transition hover:border-slate-300">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-xs font-semibold text-slate-500">{{ $kpi['title'] }}</h2>
                        <p @class([
                            'mt-2 font-mono text-3xl font-bold',
                            'text-amber-600' => $kpi['value'] > 0,
                            'text-slate-900' => $kpi['value'] === 0,
                        ])>{{ $kpi['value'] }}</p>
                    </div>
                    <span class="rounded-lg border border-[#4A0C12]/10 bg-[#4A0C12]/5 p-2.5 text-[#4A0C12]">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="{{ $kpi['icon'] }}"/>
                        </svg>
                    </span>
                </div>
                <div class="mt-4 flex items-center justify-between gap-2 border-t border-slate-100 pt-4">
                    <span class="text-xs text-slate-400">{{ $kpi['description'] }}</span>
                    <span @class([
                        'inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-medium',
                        'border-amber-200/80 bg-amber-50 text-amber-700' => $kpi['value'] > 0,
                        'border-emerald-200/80 bg-emerald-50 text-emerald-700' => $kpi['value'] === 0,
                    ])>{{ $kpi['value'] > 0 ? 'Requiere atención' : $kpi['empty'] }}</span>
                </div>
            </article>
        @endforeach
    </section>

    <section class="grid grid-cols-1 gap-5 lg:grid-cols-2" aria-label="Análisis y salud operativa">
        <article class="space-y-3 rounded-xl border border-slate-200/90 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-bold text-slate-900">Rechazos frecuentes (solicitudes)</h2>
            @if($rejectionsFromRequests->isEmpty())
                <div class="flex items-center gap-3 py-2 text-sm text-slate-500">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-emerald-200/70 bg-emerald-50 text-emerald-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </span>
                    <span>No se encontraron rechazos recientes.</span>
                </div>
            @else
                <ul class="space-y-2.5">
                    @foreach($rejectionsFromRequests as $rejection)
                        <li class="flex items-center justify-between gap-3 rounded-lg border border-slate-200/70 bg-slate-50 px-3 py-2 text-xs">
                            <span class="font-medium text-slate-700">{{ $rejection->reject_reason }}</span>
                            <span class="shrink-0 rounded bg-rose-50 px-2 py-0.5 font-mono font-bold text-rose-600">{{ $rejection->total }}x</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </article>

        <article class="space-y-3 rounded-xl border border-slate-200/90 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-bold text-slate-900">Rechazos frecuentes (órdenes)</h2>
            @if($rejectionsFromOrders->isEmpty())
                <div class="flex items-center gap-3 py-2 text-sm text-slate-500">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-emerald-200/70 bg-emerald-50 text-emerald-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </span>
                    <span>No se encontraron rechazos en órdenes.</span>
                </div>
            @else
                <ul class="space-y-2.5">
                    @foreach($rejectionsFromOrders as $rejection)
                        <li class="flex items-center justify-between gap-3 rounded-lg border border-slate-200/70 bg-slate-50 px-3 py-2 text-xs">
                            <span class="font-medium text-slate-700">{{ $rejection->reject_reason }}</span>
                            <span class="shrink-0 rounded bg-rose-50 px-2 py-0.5 font-mono font-bold text-rose-600">{{ $rejection->total }}x</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </article>

        <article class="space-y-3 rounded-xl border border-slate-200/90 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-bold text-slate-900">Tiempo promedio hasta factura</h2>
            @if($avgApprovalHours !== null)
                <div class="flex items-center gap-3 py-2">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-slate-500">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </span>
                    <div>
                        <p class="font-mono text-lg font-bold text-slate-900">{{ $avgApprovalHours }} <span class="text-sm font-medium">horas</span></p>
                        <p class="text-xs text-slate-500">Promedio entre el registro de la orden y la factura subida.</p>
                    </div>
                </div>
            @else
                <div class="flex items-center gap-3 py-2 text-sm text-slate-500">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-slate-500">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </span>
                    <span>Aún no hay datos de aprobación suficientes para calcular promedios.</span>
                </div>
            @endif
        </article>

        <article class="space-y-3 rounded-xl border border-slate-200/90 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-bold text-slate-900">Disponibilidad crítica / baja</h2>
            @if($criticalStockCount === 0 && $lowStockCount === 0)
                <div class="flex items-center gap-3 py-2 text-sm text-slate-500">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-emerald-200/70 bg-emerald-50 text-emerald-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </span>
                    <span>Aún no hay alertas de disponibilidad crítica configuradas.</span>
                </div>
            @else
                @if($criticalStockCount > 0)
                    <div>
                        <h3 class="mb-2 text-xs font-semibold text-rose-700">Repuestos en nivel crítico ({{ $criticalStockCount }})</h3>
                        <ul class="space-y-2">
                            @foreach($criticalStock as $item)
                                <li class="flex items-center justify-between gap-3 rounded-lg border border-rose-200/70 bg-rose-50/60 px-3 py-2 text-xs">
                                    <span class="font-semibold text-slate-800">{{ $item->descripcion }}</span>
                                    <span class="shrink-0 font-mono font-semibold text-rose-700">{{ $item->inventario }} disp. <span class="font-sans font-normal text-rose-600">(mín: {{ $item->stock_minimo }})</span></span>
                                </li>
                            @endforeach
                        </ul>
                        {{ $criticalStock->links() }}
                    </div>
                @endif
                @if($lowStockCount > 0)
                    <div>
                        <h3 class="mb-2 text-xs font-semibold text-amber-700">Bajo stock ({{ $lowStockCount }})</h3>
                        <ul class="space-y-2">
                            @foreach($lowStock as $item)
                                <li class="flex items-center justify-between gap-3 rounded-lg border border-amber-200/70 bg-amber-50/60 px-3 py-2 text-xs">
                                    <span class="font-semibold text-slate-800">{{ $item->descripcion }}</span>
                                    <span class="shrink-0 font-mono font-semibold text-amber-800">{{ $item->inventario }} disp. <span class="font-sans font-normal">(rep.: {{ $item->reorder_point }})</span></span>
                                </li>
                            @endforeach
                        </ul>
                        {{ $lowStock->links() }}
                    </div>
                @endif
            @endif
        </article>
    </section>

    <section class="space-y-3 pt-2">
        <div class="flex flex-wrap items-center justify-between gap-2 px-1">
            <div class="flex items-center gap-2">
                <span class="h-4 w-1.5 rounded-full bg-[#4A0C12]"></span>
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-600">Detalles recientes</h2>
            </div>
            <span class="text-xs text-slate-400">Mostrando hasta las últimas 10 operaciones por sección</span>
        </div>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
            <article class="space-y-3 rounded-xl border border-slate-200/90 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-bold text-slate-900">Solicitudes pendientes (últimas 10)</h3>
                @if($pendingRequests->isEmpty())
                    <div class="flex items-center gap-3 py-2 text-sm text-slate-500">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-emerald-200/70 bg-emerald-50 text-emerald-700">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                            </svg>
                        </span>
                        <span>Sin solicitudes pendientes.</span>
                    </div>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach($pendingRequests->take(10) as $request)
                            <li class="flex items-center justify-between gap-3 py-2.5 text-xs">
                                <div class="min-w-0">
                                    <div class="truncate">
                                        <span class="font-mono font-semibold text-slate-600">#{{ $request->id }}</span>
                                        <span class="ml-1.5 font-semibold text-slate-800">
                                            {{ $request->displayItems()->map(fn ($item) => $item->descripcion.' × '.$item->cantidad)->implode(', ') }}
                                        </span>
                                    </div>
                                    <span class="mt-0.5 block text-slate-400">
                                        Solicitado por: {{ $request->solicitado_por ?: 'No especificado' }}
                                        · Registrada por: {{ $request->almacenista?->name ?? 'No disponible' }}
                                        · {{ $request->cantidad }} unidades en total
                                    </span>
                                </div>
                                <span class="shrink-0 text-[11px] text-slate-400">{{ $request->created_at->locale('es')->diffForHumans() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </article>

            <article class="space-y-3 rounded-xl border border-slate-200/90 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-bold text-slate-900">Órdenes sin factura (últimas 10)</h3>
                @if($openOrdersMissingInvoice->isEmpty())
                    <div class="flex items-center gap-3 py-2 text-sm text-slate-500">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-emerald-200/70 bg-emerald-50 text-emerald-700">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                            </svg>
                        </span>
                        <span>Sin órdenes pendientes de factura.</span>
                    </div>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach($openOrdersMissingInvoice->take(10) as $order)
                            <li class="flex items-center justify-between gap-3 py-2.5 text-xs">
                                <div class="min-w-0">
                                    <div class="truncate">
                                        <span class="font-mono font-bold text-[#4A0C12]">OC #{{ $order->id }}</span>
                                        <span class="ml-1.5 font-semibold text-slate-800">
                                            @foreach($order->displayItems() as $orderItem)
                                                {{ $orderItem->descripcion }} × {{ $orderItem->cantidad }}@unless($loop->last); @endunless
                                            @endforeach
                                        </span>
                                    </div>
                                    <span class="mt-0.5 block text-slate-400">{{ $order->provider?->name ?? 'Sin proveedor asignado' }}</span>
                                    <span class="block text-slate-400">
                                        Solicitada por: {{ $order->solicitado_por ?: 'No especificado' }}
                                        · Emitida por: {{ $order->pedido_por ?: 'No especificado' }}
                                    </span>
                                </div>
                                <span class="shrink-0 text-[11px] font-medium text-amber-600">{{ $order->created_at->locale('es')->diffForHumans() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </article>
        </div>
    </section>
</div>
