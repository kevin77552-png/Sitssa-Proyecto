<section>
    <div class="mb-6">
        <h1 class="page-title mb-1">Buen día, {{ $firstName }}</h1>
        <p class="page-sub">Resumen operativo de hoy.</p>
    </div>

    @if($canViewOperations)
        <div class="mb-4 grid grid-cols-1 gap-4 md:grid-cols-3">
            <article class="card">
                <p class="text-sm text-gray-500">Solicitudes pendientes</p>
                <p class="mt-1 text-4xl font-bold text-gray-900">{{ $pendingRequests }}</p>
                <p class="mt-1 text-sm text-gray-500">Sin aprobar</p>
            </article>
            <article class="card">
                <p class="text-sm text-gray-500">Órdenes sin factura</p>
                <p class="mt-1 text-4xl font-bold text-gray-900">{{ $ordersWithoutInvoice }}</p>
                <p class="mt-1 text-sm text-gray-500">Pendientes de adjunto</p>
            </article>
            <article class="card">
                <p class="text-sm text-gray-500">Facturas por revisar</p>
                <p class="mt-1 text-4xl font-bold text-gray-900">{{ $invoicesForReview }}</p>
                <p class="mt-1 text-sm text-gray-500">Requieren revisión</p>
            </article>
        </div>

        <div class="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
            <article class="card flex items-center gap-3">
                <span class="material-icons rounded-full bg-emerald-100 p-2 text-emerald-800">check</span>
                <div>
                    <h2 class="font-semibold text-gray-900">Rechazos recientes</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ $recentRejections ? $recentRejections . ' en los últimos 30 días.' : 'Sin rechazos en los últimos 30 días.' }}
                    </p>
                </div>
            </article>
            <article class="card flex items-center gap-3">
                <span class="material-icons rounded-full bg-emerald-100 p-2 text-emerald-800">check</span>
                <div>
                    <h2 class="font-semibold text-gray-900">Stock crítico</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ $hasStockThresholds ? 'Consulta el reporte para revisar los niveles de stock.' : 'Aún no hay datos de stock configurados.' }}
                    </p>
                </div>
            </article>
        </div>
    @endif

    @if ($movements)
        @php($weekly = $movements['weekly'])
        @php($summary = $movements['summary'])

        @if (! $canViewOperations)
            <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                @include('livewire.partials.stat-tile', [
                    'label' => 'Salidas hoy',
                    'value' => number_format($summary['salidas_hoy']),
                    'hint' => $summary['salidas_hoy'] > 0 ? $summary['repuestos_hoy'].' repuestos distintos' : 'Sin movimientos hoy',
                ])
                @include('livewire.partials.stat-tile', [
                    'label' => 'Salidas esta semana',
                    'value' => number_format($summary['salidas_semana']),
                    'hint' => $summary['salidas_hint'],
                ])
                @include('livewire.partials.stat-tile', [
                    'label' => 'Entradas esta semana',
                    'value' => number_format($summary['entradas_semana']),
                    'hint' => $summary['entradas_hint'],
                ])
            </div>

            <div class="mb-4">
                @include('livewire.partials.bars-chart', [
                    'title' => 'Entradas y salidas por semana',
                    'subtitle' => 'Unidades por semana (empieza el lunes)',
                    'labels' => $weekly['labels'],
                    'series' => $weekly['series'],
                    'showValues' => true,
                    'emptyMessage' => 'Aún no hay entradas ni salidas. Aparecerán con el primer egreso que registres.',
                ])
            </div>

            <div class="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                @include('livewire.partials.recent-exits', ['rows' => $movements['recentExits']])
                @include('livewire.partials.hbar-list', [
                    'title' => 'Más usados este mes',
                    'subtitle' => 'Unidades que salieron en los últimos 30 días',
                    'rows' => $movements['topItems'],
                    'color' => \App\Support\DashboardMovements::COLOR_OUT,
                    'emptyMessage' => 'Aún no hay salidas este mes.',
                ])
            </div>
        @else
            <div class="mb-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                @include('livewire.partials.bars-chart', [
                    'title' => 'Entradas y salidas por semana',
                    'subtitle' => 'Unidades por semana',
                    'labels' => $weekly['labels'],
                    'series' => $weekly['series'],
                    'showValues' => false,
                    'emptyMessage' => 'Aún no hay entradas ni salidas. Aparecerán con el primer egreso que registres.',
                ])
                @include('livewire.partials.bars-chart', [
                    'title' => 'Solicitudes y órdenes por mes',
                    'subtitle' => 'Cantidad creada cada mes',
                    'labels' => $movements['monthlyOrders']['labels'],
                    'series' => $movements['monthlyOrders']['series'],
                    'showValues' => true,
                    'emptyMessage' => 'Aún no hay solicitudes ni órdenes en estos meses.',
                ])
            </div>

            <div class="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                @include('livewire.partials.hbar-list', [
                    'title' => 'Consumo por vehículo',
                    'subtitle' => 'Salidas de los últimos 30 días',
                    'rows' => $movements['topVehicles'],
                    'color' => \App\Support\DashboardMovements::COLOR_OUT,
                    'emptyMessage' => 'Aún no hay salidas asignadas a vehículos.',
                ])
                @include('livewire.partials.hbar-list', [
                    'title' => 'Repuestos más usados',
                    'subtitle' => 'Últimos 30 días',
                    'rows' => $movements['topItems'],
                    'color' => \App\Support\DashboardMovements::COLOR_REQUESTS,
                    'emptyMessage' => 'Aún no hay salidas este mes.',
                ])
            </div>
        @endif
    @endif

    <article class="card">
        <h2 class="mb-3 font-semibold text-gray-900">Accesos rápidos</h2>
        <div class="flex flex-wrap gap-2">
            @foreach($quickActions as $index => $action)
                <button wire:click="goTo('{{ $action['module'] }}')"
                    class="{{ $index === 0 ? 'btn-primary' : 'btn-ghost' }}">
                    {{ $action['label'] }}
                </button>
            @endforeach
        </div>
    </article>
</section>
