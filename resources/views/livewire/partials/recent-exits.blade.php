<div class="rounded-xl border border-gray-200 bg-white p-4">
    <h2 class="text-[15px] font-semibold text-gray-900">Últimos egresos</h2>
    <p class="mb-1 text-xs text-gray-500">Con el vehículo al que se asignó</p>

    @forelse ($rows as $row)
        <div class="flex items-center gap-3 py-2 {{ $loop->first ? '' : 'border-t border-gray-100' }}">
            <div class="w-16 shrink-0 text-xs text-gray-400">{{ $row['when'] }}</div>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm text-gray-900" title="{{ $row['descripcion'] }}">{{ $row['descripcion'] }}</p>
                <p class="text-xs text-gray-500">
                    @if ($row['placa'])
                        {{ $row['placa'] }}@if ($row['vehiculo']) · {{ $row['vehiculo'] }}@endif
                    @else
                        Sin vehículo
                    @endif
                </p>
            </div>
            <div class="shrink-0 text-sm font-medium text-orange-700">-{{ $row['cantidad'] }}</div>
        </div>
    @empty
        <p class="py-3 text-sm text-gray-500">Aún no hay egresos. Aparecerán aquí con el vehículo asignado.</p>
    @endforelse
</div>
