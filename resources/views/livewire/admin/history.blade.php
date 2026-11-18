<div class="w-full overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm" wire:keydown.escape.window="closeDetails">
    @php
        $today = now()->toDateString();
        $weekStart = now()->subDays(6)->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $allRangeActive = $dateFrom === '' && $dateTo === '';
        $todayRangeActive = $dateFrom === $today && $dateTo === $today;
        $weekRangeActive = $dateFrom === $weekStart && $dateTo === $today;
        $monthRangeActive = $dateFrom === $monthStart && $dateTo === $today;
    @endphp

    <header class="flex flex-col gap-4 border-b border-slate-100 bg-white px-6 py-6 sm:flex-row sm:items-center sm:justify-between sm:px-8">
        <div class="flex items-start gap-3.5">
            <div class="mt-0.5 shrink-0 rounded-lg border border-[#4A0C12]/10 bg-[#4A0C12]/5 p-2.5 text-[#4A0C12]">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-xl font-bold tracking-tight text-slate-900">Histórico general</h1>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200/80 bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                        Bitácora de auditoría
                    </span>
                </div>
                <p class="mt-0.5 text-sm text-slate-500">Registro de las operaciones realizadas en el sistema, quién las ejecutó y cuándo.</p>
            </div>
        </div>

        <div class="flex flex-col gap-2 self-start sm:flex-row sm:self-auto">
            <button type="button" x-on:click="window.openHistoryPrintTab()" wire:click="preparePrint" wire:loading.attr="disabled" wire:target="preparePrint"
                @disabled($errors->has('dateFrom') || $errors->has('dateTo'))
                class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-[#4A0C12] bg-white px-4 py-2.5 text-xs font-medium text-[#4A0C12] transition hover:bg-[#4A0C12]/5 disabled:cursor-wait disabled:opacity-70">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 9V3h12v6M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v7H6z"/>
                </svg>
                <span wire:loading.remove wire:target="preparePrint">Imprimir histórico</span>
                <span wire:loading wire:target="preparePrint">Preparando...</span>
            </button>
            <button type="button" wire:click="export" wire:loading.attr="disabled" wire:target="export"
                class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg bg-[#4A0C12] px-4 py-2.5 text-xs font-medium text-white shadow-sm shadow-[#4A0C12]/20 transition hover:bg-[#3A090E] disabled:cursor-wait disabled:opacity-70">
                <svg wire:loading.remove wire:target="export" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                <span wire:loading.remove wire:target="export">Exportar a Excel</span>
                <span wire:loading wire:target="export">Preparando archivo...</span>
            </button>
        </div>
    </header>

    <section class="space-y-3.5 border-b border-slate-200/80 bg-slate-50/80 px-6 py-4 sm:px-8">
        <div class="grid grid-cols-1 items-end gap-3 sm:grid-cols-2 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <label for="history-search" class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-slate-500">Buscar</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </span>
                    <input id="history-search" type="search" placeholder="Repuesto, lote, orden o detalle..."
                        wire:model.live.debounce.300ms="search"
                        class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-9 pr-3 text-xs text-slate-800 transition placeholder:text-slate-400 focus:border-[#4A0C12] focus:outline-none focus:ring-2 focus:ring-[#4A0C12]/15">
                </div>
            </div>

            <div class="lg:col-span-2">
                <label for="history-entity" class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-slate-500">Entidad</label>
                <select id="history-entity" wire:model.live="entityFilter"
                    class="w-full cursor-pointer rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700 focus:border-[#4A0C12] focus:outline-none focus:ring-2 focus:ring-[#4A0C12]/15">
                    <option value="">Todas</option>
                    @foreach($entities as $entity)
                        <option value="{{ $entity }}">{{ \App\Models\Log::entityLabel($entity) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="lg:col-span-2">
                <label for="history-action" class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-slate-500">Acción</label>
                <select id="history-action" wire:model.live="actionFilter"
                    class="w-full cursor-pointer rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700 focus:border-[#4A0C12] focus:outline-none focus:ring-2 focus:ring-[#4A0C12]/15">
                    <option value="">Todas</option>
                    @foreach($actions as $action => $label)
                        <option value="{{ $action }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="lg:col-span-2">
                <label for="history-user" class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-slate-500">Usuario</label>
                <select id="history-user" wire:model.live="userFilter"
                    class="w-full cursor-pointer rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700 focus:border-[#4A0C12] focus:outline-none focus:ring-2 focus:ring-[#4A0C12]/15">
                    <option value="">Todos</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-2 sm:col-span-2 lg:col-span-2">
                <div>
                    <label for="history-from" class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-slate-500">Desde</label>
                    <input id="history-from" type="date" wire:model.live.debounce.500ms="dateFrom"
                        class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-xs text-slate-700 focus:border-[#4A0C12] focus:outline-none focus:ring-2 focus:ring-[#4A0C12]/15">
                    @error('dateFrom')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="history-to" class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-slate-500">Hasta</label>
                    <input id="history-to" type="date" wire:model.live.debounce.500ms="dateTo"
                        class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-xs text-slate-700 focus:border-[#4A0C12] focus:outline-none focus:ring-2 focus:ring-[#4A0C12]/15">
                    @error('dateTo')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200/70 pt-3">
            <div class="flex flex-wrap items-center gap-1.5">
                <span class="mr-1 text-xs text-slate-500">Rango rápido:</span>
                <button type="button" wire:click="setQuickRange('all')" @class([
                    'cursor-pointer rounded-md px-2.5 py-1 text-xs font-medium transition',
                    'bg-[#4A0C12] text-white' => $allRangeActive,
                    'border border-slate-200 bg-white text-slate-600 hover:bg-slate-100' => ! $allRangeActive,
                ])>Todos</button>
                <button type="button" wire:click="setQuickRange('today')" @class([
                    'cursor-pointer rounded-md px-2.5 py-1 text-xs font-medium transition',
                    'bg-[#4A0C12] text-white' => $todayRangeActive,
                    'border border-slate-200 bg-white text-slate-600 hover:bg-slate-100' => ! $todayRangeActive,
                ])>Hoy</button>
                <button type="button" wire:click="setQuickRange('week')" @class([
                    'cursor-pointer rounded-md px-2.5 py-1 text-xs font-medium transition',
                    'bg-[#4A0C12] text-white' => $weekRangeActive,
                    'border border-slate-200 bg-white text-slate-600 hover:bg-slate-100' => ! $weekRangeActive,
                ])>Últimos 7 días</button>
                <button type="button" wire:click="setQuickRange('month')" @class([
                    'cursor-pointer rounded-md px-2.5 py-1 text-xs font-medium transition',
                    'bg-[#4A0C12] text-white' => $monthRangeActive,
                    'border border-slate-200 bg-white text-slate-600 hover:bg-slate-100' => ! $monthRangeActive,
                ])>Este mes</button>
                <button type="button" wire:click="clearFilters"
                    class="cursor-pointer rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-100">
                    Limpiar filtros
                </button>
            </div>

            <div class="flex items-center gap-3">
                <span wire:loading wire:target="search,entityFilter,actionFilter,userFilter,dateFrom,dateTo,setQuickRange,clearFilters"
                    class="text-xs text-slate-500" role="status">Filtrando...</span>
                <p class="text-xs text-slate-500" aria-live="polite">
                    <strong class="font-mono text-slate-800">{{ $resultCount }} {{ $resultCount === 1 ? 'registro coincidente' : 'registros coincidentes' }}</strong>
                </p>
            </div>
        </div>
    </section>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[1050px] border-collapse text-left">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50/50 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                    <th scope="col" class="py-3.5 pl-6 pr-3">Fecha</th>
                    <th scope="col" class="px-3 py-3.5">Acción</th>
                    <th scope="col" class="px-3 py-3.5">Registro afectado</th>
                    <th scope="col" class="px-3 py-3.5">Detalle de la operación</th>
                    <th scope="col" class="py-3.5 pl-3 pr-6 text-right">Usuario</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200/70 bg-white text-sm" wire:loading.class="opacity-60"
                wire:target="search,entityFilter,actionFilter,userFilter,dateFrom,dateTo,setQuickRange,clearFilters">
                @forelse($logs as $log)
                    @php
                        $actionLabel = $actions[$log->accion] ?? ucfirst(str_replace('_', ' ', $log->accion));
                        $isDelete = str_contains($log->accion, 'elimin');
                        $isReject = str_contains($log->accion, 'rechaz');
                        $isApprove = str_contains($log->accion, 'aprobar');
                        $isCreate = str_contains($log->accion, 'crear') || str_contains($log->accion, 'ingreso') || str_contains($log->accion, 'subir');
                        $actionClass = $isDelete
                            ? 'border-amber-200/80 bg-amber-50 text-amber-800'
                            : ($isReject
                                ? 'border-rose-200/80 bg-rose-50 text-rose-800'
                                : ($isApprove
                                    ? 'border-emerald-200/80 bg-emerald-50 text-emerald-800'
                                    : ($isCreate
                                        ? 'border-sky-200/80 bg-sky-50 text-sky-800'
                                        : 'border-slate-200/80 bg-slate-100 text-slate-700')));
                        $entitySummary = $log->displayEntitySummary();
                        if (!$entitySummary && $log->entidad === 'inventory_item' && $inventoryItems->has($log->entidad_id)) {
                            $item = $inventoryItems->get($log->entidad_id);
                            $entitySummary = 'Repuesto '.$item->sigicov.' - '.$item->descripcion;
                        } elseif (!$entitySummary && $log->entidad === 'purchase_order' && $purchaseOrders->has($log->entidad_id)) {
                            $order = $purchaseOrders->get($log->entidad_id);
                            $entitySummary = 'Orden #'.$order->id.' - '.$order->descripcion;
                        } elseif (!$entitySummary && in_array($log->entidad, ['spare_request', 'solicitud'], true) && $spareRequests->has($log->entidad_id)) {
                            $request = $spareRequests->get($log->entidad_id);
                            $entitySummary = $request->tipo === 'eliminacion' && $request->inventoryItem
                                ? 'Eliminación de repuesto: '.$request->inventoryItem->sigicov.' - '.$request->inventoryItem->descripcion
                                : 'Solicitud #'.$request->id.' - '.$request->descripcion;
                        } elseif (!$entitySummary && in_array($log->entidad, ['user', 'users'], true) && $loggedUsers->has($log->entidad_id)) {
                            $entitySummary = $loggedUsers->get($log->entidad_id)->name;
                        }
                        $actorName = $log->displayActorName();
                        $actorRole = $log->displayActorRole();
                        $initials = collect(preg_split('/\s+/', trim($actorName)))
                            ->filter()
                            ->take(2)
                            ->map(fn ($part) => mb_substr($part, 0, 1))
                            ->implode('');
                        $detailLines = $log->displayDetails();
                        $summary = $log->displaySummary();
                    @endphp
                    <tr wire:key="history-log-{{ $log->id }}" class="transition-colors hover:bg-slate-50/90">
                        <td class="whitespace-nowrap py-4 pl-6 pr-3 align-top">
                            <div class="font-mono text-xs font-medium text-slate-700">{{ $log->created_at->format('d/m/Y') }}</div>
                            <div class="mt-0.5 font-mono text-[11px] text-slate-400">{{ $log->created_at->format('H:i') }}</div>
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 align-top">
                            <span @class([
                                'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium',
                                $actionClass,
                            ])>{{ $actionLabel }}</span>
                        </td>
                        <td class="px-3 py-4 align-top">
                            <span class="text-xs font-semibold text-slate-800">{{ $entitySummary ?? \App\Models\Log::entityLabel($log->entidad) }}</span>
                            @if($log->entidad_id)
                                <span class="mt-1 block font-mono text-[11px] text-slate-400">#{{ $log->entidad_id }}</span>
                            @endif
                        </td>
                        <td class="max-w-xl px-3 py-4 align-top">
                            @if($summary)
                                <p class="text-xs font-medium leading-relaxed text-slate-800">{{ $summary }}</p>
                                <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                    @foreach($detailLines as $label => $value)
                                        <span class="inline-flex items-center gap-1 rounded border border-slate-200/80 bg-slate-100 px-2 py-0.5 text-[11px] text-slate-600">
                                            <span class="text-slate-400">{{ $label }}:</span>
                                            <span @class(['font-mono' => in_array($label, ['Lote', 'Respaldo'], true)])>{{ $value }}</span>
                                        </span>
                                    @endforeach
                                </div>
                            @elseif(!empty($log->cambios))
                                <div class="space-y-1">
                                    @foreach($log->cambios as $change)
                                        <p class="text-xs leading-relaxed text-slate-700">
                                            <span class="font-medium">{{ ucfirst(str_replace('_', ' ', $change['field'] ?? 'Campo')) }}:</span>
                                            <span class="break-words rounded bg-rose-50 px-1 text-rose-700">{{ $change['old'] ?? '' }}</span>
                                            <span aria-label="cambió a">→</span>
                                            <span class="break-words rounded bg-emerald-50 px-1 text-emerald-800">{{ $change['new'] ?? '' }}</span>
                                        </p>
                                    @endforeach
                                </div>
                                <p class="mt-1.5 text-xs leading-relaxed text-slate-600">{{ $log->descripcion }}</p>
                            @else
                                @foreach($detailLines as $label => $value)
                                    <p class="text-xs leading-relaxed text-slate-700">
                                        @if($label !== 'Detalle')<span class="font-medium">{{ $label }}:</span>@endif
                                        {{ $value }}
                                    </p>
                                @endforeach
                            @endif
                            @if($log->accion === 'editar' && in_array($log->entidad, ['user', 'users'], true) && empty($log->cambios))
                                <p class="mt-1 text-[11px] text-amber-700">Este registro no conserva los campos y valores modificados.</p>
                            @endif
                            <button type="button" wire:click="showDetails({{ $log->id }})"
                                class="mt-1.5 cursor-pointer text-[11px] font-medium text-[#4A0C12] underline decoration-[#4A0C12]/30 underline-offset-2 hover:text-[#3A090E]">
                                Ver detalle completo
                            </button>
                        </td>
                        <td class="whitespace-nowrap py-4 pl-3 pr-6 align-top text-right">
                            <div class="inline-flex items-center gap-2.5 text-left">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-[#4A0C12]/10 bg-[#4A0C12]/5 text-xs font-bold text-[#4A0C12]">
                                    {{ $initials }}
                                </span>
                                <span>
                                    <span class="block text-xs font-semibold text-slate-800">{{ $actorName }}</span>
                                    @if($actorRole)
                                        <span class="mt-0.5 inline-block rounded border border-slate-200/70 bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-500">{{ ucfirst($actorRole) }}</span>
                                    @endif
                                </span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="bg-white px-6 py-12 text-center">
                            <p class="text-sm font-semibold text-slate-900">No hay eventos registrados con esos filtros</p>
                            <p class="mt-1 text-xs text-slate-500">Prueba restableciendo el rango de fechas o los filtros de búsqueda.</p>
                            <button type="button" wire:click="clearFilters"
                                class="mt-4 cursor-pointer rounded-lg bg-[#4A0C12]/5 px-4 py-2 text-xs font-medium text-[#4A0C12] transition hover:bg-[#4A0C12]/10">
                                Ver todo el histórico
                            </button>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($logs->hasPages())
        <footer class="border-t border-slate-200/80 bg-slate-50/70 px-6 py-4 sm:px-8">
            {{ $logs->links() }}
        </footer>
    @endif

    @if($selectedLog)
        @php
            $detailActor = $selectedLog->displayActorName();
            $detailAction = $actions[$selectedLog->accion] ?? ucfirst(str_replace('_', ' ', $selectedLog->accion));
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" role="presentation" wire:click.self="closeDetails">
            <section class="max-h-[85vh] w-full max-w-2xl overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-xl"
                role="dialog" aria-modal="true" aria-labelledby="history-detail-title">
                <header class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-4">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 h-2 w-2 shrink-0 rounded-full bg-[#4A0C12]"></span>
                        <div>
                            <h2 id="history-detail-title" class="text-base font-bold text-slate-900">{{ $detailAction }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $selectedLog->created_at->format('d/m/Y H:i:s') }} · {{ $detailActor }}</p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeDetails" aria-label="Cerrar detalle"
                        class="cursor-pointer text-slate-400 transition hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </header>
                <dl class="space-y-4 p-6 text-sm">
                    <div>
                        <dt class="font-semibold text-slate-700">Registro</dt>
                        <dd class="mt-1 break-words text-slate-600">{{ $selectedLog->displayEntitySummary() ?? \App\Models\Log::entityLabel($selectedLog->entidad) }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-700">Detalle</dt>
                        <dd class="mt-1 whitespace-pre-line break-words text-slate-600">{{ $selectedLog->displayDescription() }}</dd>
                    </div>
                    @if(!empty($selectedLog->cambios))
                        <div>
                            <dt class="font-semibold text-slate-700">Cambios</dt>
                            <dd class="mt-1 space-y-1 text-slate-600">
                                @foreach($selectedLog->cambios as $change)
                                    <p>{{ ucfirst(str_replace('_', ' ', $change['field'] ?? 'Campo')) }}: {{ $change['old'] ?? '' }} → {{ $change['new'] ?? '' }}</p>
                                @endforeach
                            </dd>
                        </div>
                    @elseif($selectedLog->accion === 'editar' && in_array($selectedLog->entidad, ['user', 'users'], true))
                        <div role="note" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                            Este registro no conserva los campos y valores anteriores y nuevos; no es posible reconstruir exactamente qué se modificó.
                        </div>
                    @endif
                    <div>
                        <dt class="font-semibold text-slate-700">Usuario</dt>
                        <dd class="mt-1 text-slate-600">{{ $detailActor }}@if($selectedLog->displayActorRole()) · {{ ucfirst($selectedLog->displayActorRole()) }}@endif</dd>
                    </div>
                </dl>
                <footer class="flex justify-end border-t border-slate-100 bg-slate-50 px-6 py-3.5">
                    <button type="button" wire:click="closeDetails"
                        class="cursor-pointer rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-100">
                        Cerrar
                    </button>
                </footer>
            </section>
        </div>
    @endif
</div>
