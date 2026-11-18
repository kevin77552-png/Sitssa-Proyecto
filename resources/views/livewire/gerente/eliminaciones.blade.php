<div class="w-full space-y-6">
    @php
        $actionRequest = $requests->firstWhere('id', $actionId);
        $modalIsApproval = $actionType === 'aprobar';
    @endphp

    <section class="w-full overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm">
        <header class="flex flex-col gap-4 border-b border-slate-100 bg-white px-6 py-6 sm:flex-row sm:items-center sm:justify-between sm:px-8">
            <div class="flex items-start gap-3.5">
                <div class="mt-0.5 shrink-0 rounded-lg border border-rose-100 bg-rose-50 p-2.5 text-rose-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h1 class="text-xl font-bold tracking-tight text-slate-900">Eliminaciones</h1>
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200/80 bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                            Auditoría de bajas
                        </span>
                    </div>
                    <p class="mt-0.5 text-sm text-slate-500">Gestiona las solicitudes de desincorporación de repuestos enviadas por el almacén.</p>
                </div>
            </div>
        </header>

        <div class="flex flex-col justify-between gap-3 border-b border-slate-200/80 bg-slate-50/80 px-6 py-3.5 md:flex-row md:items-center sm:px-8">
            <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Filtrar solicitudes de eliminación por estado">
                @foreach ([
                    ['todos', 'Todos', $statusCounts['todos']],
                    ['por_aprobar', 'Por aprobar', $statusCounts['por_aprobar']],
                    ['aprobada', 'Aprobadas', $statusCounts['aprobada']],
                    ['rechazada', 'Rechazadas', $statusCounts['rechazada']],
                ] as [$filter, $label, $count])
                    <button
                        type="button"
                        wire:click="setStatusFilter('{{ $filter }}')"
                        aria-pressed="{{ $statusFilter === $filter ? 'true' : 'false' }}"
                        @class([
                            'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition',
                            'bg-slate-800 text-white shadow-sm' => $statusFilter === $filter,
                            'border border-slate-200 bg-white text-slate-600 hover:bg-slate-100' => $statusFilter !== $filter,
                        ])
                    >
                        @if($filter !== 'todos')
                            <span @class([
                                'h-2 w-2 rounded-full',
                                'bg-amber-500' => $filter === 'por_aprobar',
                                'bg-emerald-500' => $filter === 'aprobada',
                                'bg-rose-500' => $filter === 'rechazada',
                            ])></span>
                        @endif
                        {{ $label }} ({{ $count }})
                    </button>
                @endforeach
            </div>

            <label class="relative w-full md:w-80">
                <span class="sr-only">Buscar por SIGICOV, repuesto, motivo o solicitante</span>
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </span>
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Buscar SIGICOV, repuesto o motivo..."
                    class="w-full rounded-lg border border-slate-300 bg-white py-1.5 pl-8 pr-3 text-xs text-slate-800 transition placeholder:text-slate-400 focus:border-[#4A0C12] focus:outline-none focus:ring-2 focus:ring-[#4A0C12]/15"
                >
            </label>
        </div>

        @if(session()->has('success'))
            <div role="status" class="border-b border-emerald-200 bg-emerald-50 px-6 py-3 text-sm text-emerald-800 sm:px-8">{{ session('success') }}</div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/50 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                        <th scope="col" class="py-3.5 pl-6 pr-3">Repuesto (SIGICOV)</th>
                        <th scope="col" class="px-3 py-3.5">Ubicación</th>
                        <th scope="col" class="px-3 py-3.5">Motivo de baja</th>
                        <th scope="col" class="px-3 py-3.5">Solicitante</th>
                        <th scope="col" class="px-3 py-3.5">Estado</th>
                        <th scope="col" class="py-3.5 pl-3 pr-6 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200/70 bg-white text-sm">
                    @forelse($requests as $request)
                        @php
                            $item = $request->inventoryItem;
                            $isPending = !in_array($request->status, ['aprobada', 'rechazada'], true);
                            $statusLabel = $isPending
                                ? 'Por aprobar'
                                : ($request->status === 'aprobada' ? 'Aprobada' : 'Rechazada');
                            $statusClass = $isPending
                                ? 'border-amber-200/80 bg-amber-50 text-amber-700'
                                : ($request->status === 'aprobada'
                                    ? 'border-emerald-200/80 bg-emerald-50 text-emerald-700'
                                    : 'border-rose-200/80 bg-rose-50 text-rose-700');
                        @endphp
                        <tr wire:key="elimination-request-{{ $request->id }}" class="transition-colors hover:bg-slate-50/90">
                            <td class="py-3.5 pl-6 pr-3">
                                @if($item)
                                    <div class="flex min-w-[220px] items-center gap-2.5">
                                        <span class="shrink-0 rounded border border-slate-200/80 bg-slate-100 px-2 py-0.5 font-mono text-xs font-semibold text-slate-700">
                                            #{{ $item->sigicov ?: '—' }}
                                        </span>
                                        <span class="text-xs font-semibold text-slate-900 sm:text-sm">{{ $item->descripcion }}</span>
                                    </div>
                                    <div class="mt-1 pl-1 text-[11px] text-slate-400">Cantidad solicitada: {{ $request->cantidad }}</div>
                                @else
                                    <span class="text-xs text-slate-500">Repuesto ya no disponible</span>
                                @endif
                            </td>
                            <td class="px-3 py-3.5">
                                @if($item)
                                    <span class="inline-block whitespace-nowrap rounded border border-slate-200/70 bg-slate-50 px-2 py-0.5 font-mono text-xs text-slate-600">
                                        {{ collect([$item->pasillo, $item->estante, $item->peldaño])->filter(fn ($value) => filled($value))->join(' / ') ?: 'Sin ubicación' }}
                                    </span>
                                    <div class="mt-1 text-[11px] text-slate-400">Inventario: {{ $item->inventario }}</div>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="max-w-sm px-3 py-3.5">
                                <p class="min-w-[180px] text-xs leading-relaxed text-slate-600">{{ $request->detalles ?: 'Sin motivo especificado' }}</p>
                                @if($request->status === 'rechazada' && $request->reject_reason)
                                    <p class="mt-1 text-[11px] italic text-rose-600">Rechazo: {{ $request->reject_reason }}</p>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-3.5">
                                <div class="text-xs font-medium text-slate-800">{{ $request->almacenista?->name ?? '—' }}</div>
                                <div class="font-mono text-[11px] text-slate-400">{{ $request->created_at?->format('d/m/Y H:i') ?? '—' }}</div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3.5">
                                <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $statusClass }}">
                                    <span @class([
                                        'h-1.5 w-1.5 rounded-full',
                                        'animate-pulse bg-amber-500' => $isPending,
                                        'bg-emerald-500' => $request->status === 'aprobada',
                                        'bg-rose-500' => $request->status === 'rechazada',
                                    ])></span>
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap py-3.5 pl-3 pr-6 text-right">
                                @if($isPending)
                                    <div class="inline-flex items-center gap-2">
                                        <button
                                            type="button"
                                            wire:click="openRejectModal({{ $request->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="openRejectModal({{ $request->id }})"
                                            class="cursor-pointer rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-50 disabled:opacity-50"
                                        >
                                            Rechazar
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="openApproveModal({{ $request->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="openApproveModal({{ $request->id }})"
                                            @disabled(!$item)
                                            class="inline-flex cursor-pointer items-center gap-1 rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            Aprobar baja
                                        </button>
                                    </div>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-md border border-slate-200/70 bg-slate-100 px-2.5 py-1 text-[11px] font-medium text-slate-400">
                                        Procesada
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-14 text-center">
                                <div class="mx-auto mb-3 inline-flex h-12 w-12 items-center justify-center rounded-full border border-slate-200/70 bg-slate-100 text-slate-400">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <h3 class="text-sm font-semibold text-slate-900">
                                    {{ $statusCounts['todos'] === 0 ? 'No hay solicitudes de eliminación' : 'No hay solicitudes que coincidan' }}
                                </h3>
                                <p class="mx-auto mt-1 max-w-sm text-xs text-slate-500">
                                    {{ $statusCounts['todos'] === 0
                                        ? 'Actualmente no existen requerimientos pendientes ni históricos para dar de baja artículos en el inventario.'
                                        : 'Prueba con otro estado o cambia el texto de búsqueda.' }}
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <footer class="flex items-center justify-between border-t border-slate-200/80 bg-slate-50/70 px-6 py-3.5 text-xs text-slate-500 sm:px-8">
            <span>Mostrando <strong class="font-mono text-slate-800">{{ $requests->count() }}</strong> {{ $requests->count() === 1 ? 'solicitud' : 'solicitudes' }} de baja</span>
            <span class="hidden text-slate-400 sm:inline">Al aprobar, la cantidad se resta del inventario y queda registrada en auditoría.</span>
        </footer>
    </section>

    @if($showRejectModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" wire:keydown.escape.window="cancelReject">
            <div class="w-full max-w-md overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl" role="dialog" aria-modal="true" aria-labelledby="elimination-modal-title">
                <div class="p-6">
                    <div class="flex items-start gap-4">
                        <div @class([
                            'shrink-0 rounded-full border p-2.5',
                            'border-rose-100 bg-rose-50 text-rose-600' => $modalIsApproval,
                            'border-slate-200 bg-slate-100 text-slate-600' => !$modalIsApproval,
                        ])>
                            @if($modalIsApproval)
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            @else
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 id="elimination-modal-title" class="text-base font-bold text-slate-900">
                                {{ $modalIsApproval ? 'Aprobar baja definitiva' : 'Rechazar solicitud de baja' }}
                            </h3>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ $modalIsApproval
                                    ? 'Se restará la cantidad del inventario activo y la operación quedará registrada en auditoría.'
                                    : 'El repuesto permanecerá activo. Indica el motivo para que el almacén pueda revisarlo.' }}
                            </p>

                            @if($actionRequest)
                                <div class="mt-3 rounded-lg border border-slate-200/80 bg-slate-50 p-3 text-xs text-slate-800">
                                    <span class="font-mono font-bold">SIGICOV #{{ $actionRequest->inventoryItem?->sigicov ?? '—' }}</span>
                                    — <strong>{{ $actionRequest->inventoryItem?->descripcion ?? 'Repuesto no disponible' }}</strong>
                                    <span class="mt-1 block text-slate-500">
                                        Cantidad solicitada: {{ $actionRequest->cantidad }}
                                        @if($actionRequest->inventoryItem)
                                            · Inventario actual: {{ $actionRequest->inventoryItem->inventario }}
                                        @endif
                                    </span>
                                    <span class="mt-1 block text-slate-500">Motivo reportado: {{ $actionRequest->detalles ?: 'Sin motivo especificado' }}</span>
                                </div>
                            @endif

                            <label for="audit-note" class="mb-1 mt-3 block text-xs font-medium text-slate-700">
                                {{ $modalIsApproval ? 'Nota de auditoría (opcional)' : 'Motivo del rechazo' }}
                                @unless($modalIsApproval)<span class="text-rose-600">*</span>@endunless
                            </label>
                            <textarea
                                id="audit-note"
                                wire:model.defer="auditNote"
                                rows="3"
                                maxlength="1000"
                                placeholder="{{ $modalIsApproval ? 'Ej. Verificado físicamente en almacén...' : 'Indica por qué se rechaza la solicitud...' }}"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs text-slate-800 focus:border-[#4A0C12] focus:outline-none focus:ring-2 focus:ring-[#4A0C12]/15"
                            ></textarea>
                            @error('rejectReason')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                            @error('auditNote')
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
                        wire:click="confirmAction"
                        wire:loading.attr="disabled"
                        wire:target="confirmAction"
                        class="cursor-pointer rounded-lg px-4 py-2 text-xs font-medium text-white shadow-sm transition disabled:cursor-wait disabled:opacity-60 {{ $modalIsApproval ? 'bg-rose-600 hover:bg-rose-700' : 'bg-slate-800 hover:bg-slate-900' }}"
                    >
                        {{ $modalIsApproval ? 'Confirmar eliminación' : 'Confirmar rechazo' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
