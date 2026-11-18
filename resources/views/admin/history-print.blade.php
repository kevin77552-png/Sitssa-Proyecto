<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Imprimir histórico general</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 12mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #0f172a;
            font-family: Arial, sans-serif;
            font-size: 10px;
        }

        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.5rem;
            background: #f8fafc;
        }

        .toolbar p {
            margin: 0;
            color: #475569;
            font-size: 0.875rem;
        }

        .toolbar button {
            border: 0;
            border-radius: 0.5rem;
            padding: 0.65rem 1rem;
            background: #4a0c12;
            color: #fff;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 600;
        }

        main {
            padding: 1rem 1.5rem;
        }

        h1 {
            margin: 0 0 0.25rem;
            font-size: 18px;
        }

        .metadata,
        .filters {
            color: #475569;
            font-size: 9px;
        }

        .filters {
            margin-top: 0.5rem;
        }

        table {
            width: 100%;
            margin-top: 1rem;
            border-collapse: collapse;
            table-layout: fixed;
        }

        thead {
            display: table-header-group;
        }

        th,
        td {
            border: 1px solid #cbd5e1;
            padding: 5px 6px;
            text-align: left;
            vertical-align: top;
            overflow-wrap: anywhere;
        }

        th {
            background: #f1f5f9;
            font-size: 8px;
            text-transform: uppercase;
        }

        tr {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .date {
            width: 12%;
            white-space: nowrap;
        }

        .action {
            width: 15%;
        }

        .record {
            width: 22%;
        }

        .detail {
            width: 39%;
        }

        .user {
            width: 12%;
        }

        .change {
            margin: 0 0 3px;
        }

        .empty {
            padding: 2rem;
            text-align: center;
        }

        .pagination {
            margin-top: 1rem;
        }

        @media print {
            .toolbar {
                display: none;
            }

            .pagination {
                display: none;
            }

            main {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <header class="toolbar">
        <p>
            {{ $logs->total() }} {{ $logs->total() === 1 ? 'registro' : 'registros' }} · Histórico general
            @if($logs->lastPage() > 1)
                · Página {{ $logs->currentPage() }} de {{ $logs->lastPage() }}
            @endif
        </p>
        <button type="button" onclick="window.print()">Imprimir / Guardar como PDF</button>
    </header>

    <main>
        <h1>Histórico general</h1>
        <p class="metadata">Generado el {{ $printedAt->format('d/m/Y H:i') }} · {{ $logs->total() }} {{ $logs->total() === 1 ? 'registro' : 'registros' }}</p>
        @if($filterSummary !== [])
            <p class="filters"><strong>Filtros:</strong> {{ implode(' · ', $filterSummary) }}</p>
        @endif

        <table>
            <thead>
                <tr>
                    <th class="date">Fecha</th>
                    <th class="action">Acción</th>
                    <th class="record">Registro afectado</th>
                    <th class="detail">Detalle de la operación</th>
                    <th class="user">Usuario</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    @php
                        $actionLabel = $actions[$log->accion] ?? ucfirst(str_replace('_', ' ', $log->accion));
                        $entitySummary = $log->displayEntitySummary() ?? \App\Models\Log::entityLabel($log->entidad);
                        $summary = $log->displaySummary();
                        $detailLines = $log->displayDetails();
                    @endphp
                    <tr>
                        <td>{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ $actionLabel }}</td>
                        <td>
                            {{ $entitySummary }}
                            @if($log->entidad_id)
                                <br><small>#{{ $log->entidad_id }}</small>
                            @endif
                        </td>
                        <td>
                            @if($summary)
                                <p class="change"><strong>{{ $summary }}</strong></p>
                                @foreach($detailLines as $label => $value)
                                    <p class="change"><strong>{{ $label }}:</strong> {{ $value }}</p>
                                @endforeach
                            @elseif(!empty($log->cambios))
                                @foreach($log->cambios as $change)
                                    <p class="change">
                                        <strong>{{ ucfirst(str_replace('_', ' ', $change['field'] ?? 'Campo')) }}:</strong>
                                        {{ $change['old'] ?? '' }} → {{ $change['new'] ?? '' }}
                                    </p>
                                @endforeach
                                <p>{{ $log->descripcion }}</p>
                            @else
                                @foreach($detailLines as $label => $value)
                                    <p class="change">
                                        @if($label !== 'Detalle')
                                            <strong>{{ $label }}:</strong>
                                        @endif
                                        {{ $value }}
                                    </p>
                                @endforeach
                            @endif
                        </td>
                        <td>{{ $log->displayActorName() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="empty">No hay registros para imprimir con los filtros seleccionados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if($logs->hasPages())
            <nav class="pagination" aria-label="Paginación del histórico">
                {{ $logs->links() }}
            </nav>
        @endif
    </main>
</body>
</html>
