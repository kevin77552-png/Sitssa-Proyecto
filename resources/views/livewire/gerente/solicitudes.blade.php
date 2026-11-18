<div class="w-full space-y-6">
    <section class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm">
        <header class="flex flex-col gap-4 border-b border-slate-100 px-6 py-6 sm:flex-row sm:items-center sm:justify-between sm:px-8">
            <div class="flex items-start gap-3.5">
                <div class="mt-0.5 shrink-0 rounded-lg border border-[#4A0C12]/10 bg-[#4A0C12]/5 p-2.5 text-[#4A0C12]">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h1 class="text-xl font-bold tracking-tight text-slate-900">Solicitudes de repuestos</h1>
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-purple-200/80 bg-purple-50 px-2.5 py-0.5 text-xs font-medium text-purple-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-purple-500"></span>
                            Vista Gerencia
                        </span>
                    </div>
                    <p class="mt-0.5 text-sm text-slate-500">Revisa las requisiciones del almacén, asigna un proveedor autorizado y aprueba para generar la orden de compra.</p>
                </div>
            </div>

            <label class="flex items-center gap-2 self-start text-xs sm:self-auto">
                <span class="font-medium text-slate-500">Por página:</span>
                <select wire:model.live="perPage" class="cursor-pointer rounded-lg border border-slate-300 bg-slate-50 px-2.5 py-1.5 font-mono text-xs font-semibold text-slate-700 focus:border-[#4A0C12] focus:outline-none focus:ring-2 focus:ring-[#4A0C12]/15">
                    <option value="5">5</option>
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </label>
        </header>

        <div class="flex flex-col justify-between gap-3 bg-slate-50/80 px-6 py-3.5 md:flex-row md:items-center sm:px-8">
            <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Filtrar solicitudes por estado">
                @foreach ([
                    ['all', 'Todas', $statusCounts['all']],
                    ['pending', 'Pendientes', $statusCounts['pending']],
                    ['approved', 'Aprobadas', $statusCounts['approved']],
                    ['rejected', 'Rechazadas', $statusCounts['rejected']],
                ] as [$filter, $label, $count])
                    <button
                        type="button"
                        wire:click="setStatusFilter('{{ $filter }}')"
                        aria-pressed="{{ $statusFilter === $filter ? 'true' : 'false' }}"
                        @class([
                            'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition',
                            'bg-[#4A0C12] text-white shadow-sm' => $statusFilter === $filter,
                            'border border-slate-200 bg-white text-slate-600 hover:bg-slate-100' => $statusFilter !== $filter,
                        ])
                    >
                        @if($filter !== 'all')
                            <span @class([
                                'h-2 w-2 rounded-full',
                                'bg-amber-500' => $filter === 'pending',
                                'bg-emerald-500' => $filter === 'approved',
                                'bg-rose-500' => $filter === 'rejected',
                            ])></span>
                        @endif
                        {{ $label }} ({{ $count }})
                    </button>
                @endforeach
            </div>

            <label class="relative w-full md:w-72">
                <span class="sr-only">Buscar solicitud o almacenista</span>
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </span>
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Buscar artículo, código o almacenista..."
                    class="w-full rounded-lg border border-slate-300 bg-white py-1.5 pl-8 pr-3 text-xs text-slate-800 transition placeholder:text-slate-400 focus:border-[#4A0C12] focus:outline-none focus:ring-2 focus:ring-[#4A0C12]/15"
                >
            </label>
        </div>
    </section>

    @if(session()->has('message'))
        <div role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('message') }}</div>
    @endif
    @if(session()->has('error'))
        <div role="alert" class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">{{ session('error') }}</div>
    @endif

    <div class="flex items-center justify-between px-1 text-xs text-slate-500">
        <span>
            Mostrando
            <strong class="font-mono text-slate-800">{{ $requests->firstItem() ?? 0 }} - {{ $requests->lastItem() ?? 0 }}</strong>
            de <strong class="font-mono text-slate-800">{{ $requests->total() }}</strong>
            {{ $requests->total() === 1 ? 'solicitud' : 'solicitudes' }}
        </span>
        <span class="hidden text-slate-400 sm:inline">Al aprobar, se genera automáticamente la orden de compra.</span>
    </div>

    <div class="space-y-4">
        @forelse($requests as $req)
            @php
                $requestCode = 'REQ-'.str_pad((string) $req->id, 3, '0', STR_PAD_LEFT);
                $statusMeta = [
                    'pending' => ['Pendiente de aprobación', 'bg-amber-50 text-amber-700 border-amber-200/80'],
                    'approved' => ['Aprobada', 'bg-emerald-50 text-emerald-700 border-emerald-200/80'],
                    'received' => ['Recibida en almacén', 'bg-[#4A0C12]/5 text-[#4A0C12] border-[#4A0C12]/20'],
                    'rejected' => ['Rechazada', 'bg-rose-50 text-rose-700 border-rose-200/80'],
                ];
                [$statusLabel, $statusClass] = $statusMeta[$req->status]
                    ?? ['Estado: '.str_replace('_', ' ', $req->status), 'bg-slate-100 text-slate-700 border-slate-200'];
            @endphp

            <article wire:key="manager-spare-request-{{ $req->id }}" class="rounded-xl border border-slate-200/90 bg-white p-5 shadow-sm transition hover:border-slate-300 sm:p-6">
                <div class="flex flex-col justify-between gap-5 lg:flex-row lg:items-center">
                    <div class="min-w-0 flex-1 space-y-2.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded border border-slate-200/70 bg-slate-100 px-2 py-0.5 font-mono text-xs font-semibold text-slate-500">{{ $requestCode }}</span>
                            <h2 class="text-base font-bold text-slate-900">Repuestos solicitados</h2>
                            <span class="inline-flex items-center rounded-md border border-slate-200/80 bg-slate-100 px-2.5 py-0.5 font-mono text-xs font-semibold text-slate-700">
                                {{ $req->cantidad }} unidades en total
                            </span>
                            @if($req->prioridad === 'URGENTE')
                                <span class="inline-flex items-center rounded border border-rose-200/70 bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-700">Urgente</span>
                            @endif
                            <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $statusClass }}">
                                <span @class([
                                    'h-1.5 w-1.5 rounded-full',
                                    'bg-amber-500' => $req->status === 'pending',
                                    'bg-emerald-500' => $req->status === 'approved',
                                    'bg-[#4A0C12]' => $req->status === 'received',
                                    'bg-rose-500' => $req->status === 'rejected',
                                    'bg-slate-400' => ! in_array($req->status, ['pending', 'approved', 'received', 'rejected'], true),
                                ])></span>
                                {{ $statusLabel }}
                            </span>
                            @if($req->purchaseOrder)
                                <span class="rounded border border-indigo-200/70 bg-indigo-50 px-2 py-0.5 font-mono text-xs font-bold text-indigo-700">
                                    OC #{{ $req->purchaseOrder->id }}
                                </span>
                            @endif
                        </div>

                        <ul class="max-w-3xl space-y-2 rounded-lg border border-slate-200/60 bg-slate-50/80 px-3 py-2.5">
                            @foreach($req->displayItems() as $requestItem)
                                <li class="text-xs text-slate-700">
                                    <span class="font-semibold">{{ $requestItem->descripcion }}</span>
                                    <span class="ml-1 font-mono text-slate-500">× {{ $requestItem->cantidad }}</span>
                                    @if($requestItem->detalles)
                                        <p class="mt-0.5 text-slate-500">{{ $requestItem->detalles }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>

                        @if($req->solicitado_por)
                            <p class="max-w-3xl text-xs text-slate-500">
                                <span class="font-semibold text-slate-600">Solicitado por:</span> {{ $req->solicitado_por }}
                            </p>
                        @endif

                        @if($req->comentario)
                            <p class="max-w-3xl rounded-lg border border-indigo-100 bg-indigo-50/60 px-3 py-2 text-xs text-indigo-900">
                                <span class="font-semibold">Comentario del almacenista:</span> {{ $req->comentario }}
                            </p>
                        @endif

                        @if($req->status === 'rejected' && $req->reject_reason)
                            <p class="max-w-3xl rounded-lg border border-rose-100 bg-rose-50 px-3 py-2 text-xs text-rose-800">
                                <span class="font-semibold">Motivo del rechazo:</span> {{ $req->reject_reason }}
                            </p>
                        @endif

                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 pt-1 text-xs text-slate-500">
                            <span>
                                <span class="font-semibold text-slate-700">{{ $req->almacenista?->name ?? '—' }}</span>
                                <span class="text-slate-400">(Almacenista)</span>
                            </span>
                            <span class="inline-flex items-center gap-1 font-mono text-[11px] text-slate-400" title="Fecha exacta: {{ $req->created_at?->format('d/m/Y H:i') }}">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                {{ $req->created_at?->format('d/m/Y H:i') ?? '—' }}
                            </span>
                        </div>
                    </div>

                    @if($req->status === 'pending')
                        <div class="space-y-3 rounded-xl border border-slate-200/80 bg-slate-50/90 p-4 lg:w-96 lg:shrink-0">
                            <div>
                                <label for="provider-{{ $req->id }}" class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                                    Asignar proveedor para aprobar <span class="text-[#4A0C12]">*</span>
                                </label>
                                <select
                                    id="provider-{{ $req->id }}"
                                    wire:model="providerIds.{{ $req->id }}"
                                    class="w-full cursor-pointer rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 focus:border-[#4A0C12] focus:outline-none focus:ring-2 focus:ring-[#4A0C12]/15"
                                >
                                    <option value="">Selecciona un proveedor...</option>
                                    @foreach($providers as $provider)
                                        <option value="{{ $provider->id }}">{{ $provider->name }}</option>
                                    @endforeach
                                </select>
                                @error('provider_id')
                                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                @enderror
                                @if($providers->isEmpty())
                                    <p class="mt-1 text-xs text-rose-600">No hay proveedores activos disponibles.</p>
                                @endif
                            </div>

                            <div class="flex items-center justify-end gap-2 pt-1">
                                <button
                                    type="button"
                                    wire:click="openReject({{ $req->id }})"
                                    class="cursor-pointer rounded-lg border border-rose-200 bg-white px-3 py-2 text-xs font-medium text-rose-700 transition hover:bg-rose-50"
                                >
                                    Rechazar
                                </button>
                                <button
                                    type="button"
                                    wire:click="approve({{ $req->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="approve({{ $req->id }})"
                                    @disabled($providers->isEmpty())
                                    class="inline-flex flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-lg bg-[#4A0C12] px-4 py-2 text-xs font-medium text-white shadow-sm transition hover:bg-[#3A090E] disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Aprobar y generar OC
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="flex shrink-0 flex-col gap-3 border-t border-slate-100 pt-4 lg:w-72 lg:items-end lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0">
                            <span class="inline-flex items-center gap-1 rounded-md border border-slate-200/70 bg-slate-100 px-2.5 py-1 text-[11px] font-medium text-slate-500">
                                Acciones cerradas
                            </span>
                            @if($req->purchaseOrder?->provider)
                                <div class="text-left lg:text-right">
                                    <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400">Proveedor asignado</span>
                                    <span class="text-xs font-medium text-slate-700">{{ $req->purchaseOrder->provider->name }}</span>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-slate-200 bg-white px-6 py-10 text-center shadow-sm">
                <p class="text-sm font-semibold text-slate-800">No hay solicitudes que coincidan con el filtro</p>
                <p class="mt-1 text-xs text-slate-500">Prueba con otra categoría o cambia el texto de búsqueda.</p>
            </div>
        @endforelse
    </div>

    @if($requests->hasPages())
        <div class="pt-2">
            {{ $requests->links() }}
        </div>
    @endif

    @if($rejectingId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" wire:keydown.escape.window="cancelReject">
            <div class="w-full max-w-md overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl" role="dialog" aria-modal="true" aria-labelledby="reject-modal-title">
                <div class="p-6">
                    <div class="flex items-start gap-4">
                        <div class="shrink-0 rounded-full border border-rose-100 bg-rose-50 p-2.5 text-rose-600">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 id="reject-modal-title" class="text-base font-bold text-slate-900">Rechazar solicitud</h3>
                            <p class="mt-1 text-sm text-slate-500">Indica el motivo del rechazo para que el almacenista pueda revisarlo.</p>
                            @php($requestBeingRejected = $requests->firstWhere('id', $rejectingId))
                            @if($requestBeingRejected)
                                <div class="mt-3 rounded-lg border border-slate-200/80 bg-slate-50 p-3 text-xs font-medium text-slate-800">
                                    REQ-{{ str_pad((string) $requestBeingRejected->id, 3, '0', STR_PAD_LEFT) }} — {{ $requestBeingRejected->descripcion }}
                                    (Cantidad: {{ $requestBeingRejected->cantidad }})
                                </div>
                            @endif
                            <label for="reject-reason" class="mb-1 mt-3 block text-xs font-medium text-slate-700">
                                Motivo del rechazo <span class="text-rose-600">*</span>
                            </label>
                            <textarea
                                id="reject-reason"
                                wire:model.defer="rejectReason"
                                rows="3"
                                maxlength="1000"
                                placeholder="Escribe el motivo..."
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs text-slate-800 focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500/15"
                            ></textarea>
                            @error('rejectReason')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-slate-100 bg-slate-50 px-6 py-3.5">
                    <button type="button" wire:click="cancelReject" class="cursor-pointer rounded-lg px-4 py-2 text-xs font-medium text-slate-600 transition hover:bg-slate-200/60">
                        Cancelar
                    </button>
                    <button
                        type="button"
                        wire:click="submitReject"
                        wire:loading.attr="disabled"
                        wire:target="submitReject"
                        class="cursor-pointer rounded-lg bg-rose-600 px-4 py-2 text-xs font-medium text-white shadow-sm transition hover:bg-rose-700 disabled:opacity-60"
                    >
                        Confirmar rechazo
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
