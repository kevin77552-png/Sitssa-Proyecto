<div class="w-full space-y-6">
    <section class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm">
        <header class="flex flex-col gap-4 border-b border-slate-100 px-6 py-6 sm:flex-row sm:items-center sm:justify-between sm:px-8">
            <div class="flex items-start gap-3.5">
                <div class="mt-0.5 shrink-0 rounded-lg border border-[#4A0C12]/10 bg-[#4A0C12]/5 p-2.5 text-[#4A0C12]">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-slate-900">Mis solicitudes</h1>
                    <p class="mt-0.5 text-sm text-slate-500">Consulta el avance de aprobación, órdenes de compra y confirma la recepción en almacén.</p>
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="flex items-center gap-2.5 self-start rounded-lg border border-slate-200/80 bg-slate-50 px-3.5 py-2 text-xs sm:self-auto">
                    <div class="rounded-md border border-slate-200 bg-white p-1.5 text-slate-500">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <div>
                        <span class="block text-[11px] font-medium text-slate-400">Solicitante</span>
                        <span class="font-semibold text-slate-800">{{ $almacenistaName ?: '—' }}</span>
                    </div>
                </div>

                <button
                    type="button"
                    wire:click="newRequest"
                    class="inline-flex items-center justify-center gap-2 self-start rounded-lg bg-[#4A0C12] px-4 py-2.5 text-xs font-medium text-white shadow-sm shadow-[#4A0C12]/20 transition hover:bg-[#3A090E] sm:self-auto"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Nueva solicitud
                </button>
            </div>
        </header>

        <div class="flex flex-wrap items-center justify-between gap-3 bg-slate-50/80 px-6 py-3.5 sm:px-8">
            <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Filtrar solicitudes por estado">
                @foreach ([
                    ['all', 'Todas', $statusCounts['all'], 'bg-slate-800'],
                    ['approved', 'Aprobadas / por recibir', $statusCounts['approved'], 'bg-sky-500'],
                    ['pending', 'En revisión', $statusCounts['pending'], 'bg-amber-500'],
                    ['received', 'Recibidas', $statusCounts['received'], 'bg-[#4A0C12]'],
                    ['rejected', 'Rechazadas', $statusCounts['rejected'], 'bg-rose-500'],
                ] as [$filter, $label, $count, $dotClass])
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
                            <span class="h-2 w-2 rounded-full {{ $dotClass }}"></span>
                        @endif
                        {{ $label }} ({{ $count }})
                    </button>
                @endforeach
            </div>
            <span class="hidden text-[11px] text-slate-400 md:inline">
                {{ number_format($statusCounts['all']) }} {{ $statusCounts['all'] === 1 ? 'solicitud' : 'solicitudes' }}
            </span>
        </div>
    </section>

    <div class="space-y-4">
        @forelse($requests as $req)
            @php
                $statusMeta = [
                    'pending' => ['Pendiente de aprobación', 'bg-amber-50 text-amber-700 border-amber-200/80'],
                    'approved' => ['Aprobada', 'bg-[#4A0C12]/5 text-[#4A0C12] border-[#4A0C12]/20'],
                    'rejected' => ['Rechazada', 'bg-rose-50 text-rose-700 border-rose-200/80'],
                    'received' => ['Recibida en almacén', 'bg-[#4A0C12]/5 text-[#4A0C12] border-[#4A0C12]/20'],
                ];
                [$statusLabel, $statusClass] = $statusMeta[$req->status]
                    ?? ['Estado: '.str_replace('_', ' ', $req->status), 'bg-slate-100 text-slate-700 border-slate-200'];
                $managementComplete = in_array($req->status, ['approved', 'received'], true);
                $isRejected = $req->status === 'rejected';
                $isReceived = $req->status === 'received';
            @endphp

            <article wire:key="spare-request-{{ $req->id }}" class="rounded-xl border border-slate-200/90 bg-white p-5 shadow-sm transition hover:border-slate-300 sm:p-6">
                <div class="flex flex-col justify-between gap-4 md:flex-row md:items-start">
                    <div class="min-w-0 flex-1 space-y-2">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <span class="inline-flex items-center rounded-md border border-slate-200 bg-slate-100 px-2.5 py-0.5 font-mono text-xs font-semibold text-slate-700">
                                {{ $req->cantidad }} unidades en total
                            </span>
                            @if($req->prioridad === 'URGENTE')
                                <span class="rounded-md border border-rose-200 bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-700">Urgente</span>
                            @endif
                            <span class="flex items-center gap-1 text-xs text-slate-400" title="Fecha exacta: {{ $req->created_at?->format('d/m/Y H:i') }}">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Creada {{ $req->created_at?->diffForHumans() ?? '—' }}
                            </span>
                        </div>

                        <ul class="max-w-2xl space-y-2 rounded-lg border border-slate-200/60 bg-slate-50/80 px-3 py-2.5">
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
                            <p class="text-xs text-slate-500">
                                <span class="font-semibold text-slate-600">Solicitado por:</span> {{ $req->solicitado_por }}
                            </p>
                        @endif

                        @if($req->comentario)
                            <div class="max-w-2xl rounded-lg border border-indigo-100 bg-indigo-50/60 px-3 py-2 text-xs text-indigo-900">
                                <span class="font-semibold">Tu comentario:</span> {{ $req->comentario }}
                            </div>
                        @endif

                        @if($req->status === 'rejected' && $req->reject_reason)
                            <div class="max-w-2xl rounded-lg border border-rose-100 bg-rose-50 px-3 py-2 text-xs text-rose-800">
                                <span class="font-semibold">Motivo del rechazo:</span> {{ $req->reject_reason }}
                            </div>
                        @endif
                    </div>

                    <div class="flex shrink-0 flex-col gap-3 md:items-end">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium {{ $statusClass }}">
                                <span class="mr-1.5 h-1.5 w-1.5 rounded-full {{ $req->status === 'pending' ? 'bg-amber-500' : ($req->status === 'rejected' ? 'bg-rose-500' : 'bg-[#4A0C12]') }}"></span>
                                {{ $statusLabel }}
                            </span>
                            @if($req->purchaseOrder)
                                <span class="rounded-md border border-indigo-200/80 bg-indigo-50 px-2.5 py-1 font-mono text-xs font-bold text-indigo-700">
                                    OC #{{ $req->purchaseOrder->id }}
                                </span>
                            @endif
                        </div>

                        @if($req->status === 'approved' && ! $req->purchaseOrder)
                            <span class="text-xs text-slate-500">Aprobada; orden de compra pendiente</span>
                        @elseif($req->status === 'approved')
                            <span class="text-xs text-sky-700">Pedido generado</span>
                        @endif

                        <div class="flex flex-wrap gap-2 md:justify-end">
                            <button
                                type="button"
                                x-on:click.prevent="Swal.fire({
                                    title: 'Agregar comentario',
                                    input: 'textarea',
                                    inputValue: @js($req->comentario ?? ''),
                                    inputPlaceholder: 'Escribe una nota o consulta para gerencia...',
                                    showCancelButton: true,
                                    confirmButtonText: 'Guardar',
                                    cancelButtonText: 'Cancelar',
                                    preConfirm: (value) => {
                                        if (!value || !value.trim()) {
                                            Swal.showValidationMessage('El comentario no puede estar vacío');
                                            return false;
                                        }
                                        return value.trim();
                                    }
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        $wire.addComment({{ $req->id }}, result.value);
                                    }
                                })"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                            >
                                <svg class="h-3.5 w-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                                </svg>
                                Comentar
                            </button>

                            @if($req->status === 'approved')
                                <button
                                    type="button"
                                    x-on:click.prevent="Swal.fire({
                                        title: 'Confirmar recepción física',
                                        text: @js('¿Confirmas que recibiste todos los repuestos de la solicitud REQ-'.str_pad((string) $req->id, 3, '0', STR_PAD_LEFT).'? Total de unidades: '.$req->cantidad.'.'),
                                        icon: 'question',
                                        showCancelButton: true,
                                        confirmButtonColor: '#4A0C12',
                                        cancelButtonColor: '#64748b',
                                        confirmButtonText: 'Sí, confirmar recibido',
                                        cancelButtonText: 'Cancelar'
                                    }).then((result) => {
                                        if (result.isConfirmed) {
                                            $wire.confirmReceived({{ $req->id }});
                                        }
                                    })"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-[#4A0C12] px-4 py-2 text-xs font-medium text-white shadow-sm transition hover:bg-[#3A090E]"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Confirmar recibido
                                </button>
                            @elseif($req->status === 'received')
                                <span class="inline-flex items-center gap-1.5 self-center text-xs font-medium text-[#4A0C12]">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Recepción confirmada
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-2 border-t border-slate-100 pt-3 text-[11px]">
                    <span class="inline-flex items-center gap-1 font-medium text-[#4A0C12]">
                        <span class="h-2 w-2 rounded-full bg-[#4A0C12]"></span>
                        Solicitada
                    </span>
                    <div @class([
                        'h-px w-6',
                        'bg-[#4A0C12]/40' => $managementComplete,
                        'bg-rose-300' => $isRejected,
                        'bg-slate-200' => ! $managementComplete && ! $isRejected,
                    ])></div>
                    <span @class([
                        'inline-flex items-center gap-1 font-medium',
                        'text-[#4A0C12]' => $managementComplete,
                        'text-rose-700' => $isRejected,
                        'text-slate-400' => ! $managementComplete && ! $isRejected,
                    ])>
                        <span @class([
                            'h-2 w-2 rounded-full',
                            'bg-[#4A0C12]' => $managementComplete,
                            'bg-rose-500' => $isRejected,
                            'bg-slate-300' => ! $managementComplete && ! $isRejected,
                        ])></span>
                        {{ $isRejected ? 'Rechazada' : ($managementComplete ? 'Aprobada' : 'En revisión') }}
                    </span>
                    <div @class(['h-px w-6', 'bg-[#4A0C12]/40' => $isReceived, 'bg-slate-200' => ! $isReceived])></div>
                    <span @class([
                        'inline-flex items-center gap-1 font-medium',
                        'text-[#4A0C12]' => $isReceived,
                        'text-slate-400' => ! $isReceived,
                    ])>
                        <span @class(['h-2 w-2 rounded-full', 'bg-[#4A0C12]' => $isReceived, 'bg-slate-300' => ! $isReceived])></span>
                        Recibida en almacén
                    </span>
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-slate-200 bg-white px-6 py-10 text-center shadow-sm">
                <p class="text-sm font-semibold text-slate-800">
                    {{ $statusFilter === 'all' ? 'Todavía no tienes solicitudes' : 'No hay solicitudes en esta categoría' }}
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    {{ $statusFilter === 'all' ? 'Crea una solicitud para consultar aquí su avance.' : 'Selecciona otra pestaña para consultar tus solicitudes.' }}
                </p>
                @if($statusFilter === 'all')
                    <button type="button" wire:click="newRequest" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-[#4A0C12] px-4 py-2.5 text-xs font-medium text-white transition hover:bg-[#3A090E]">
                        Nueva solicitud
                    </button>
                @endif
            </div>
        @endforelse
    </div>
</div>
