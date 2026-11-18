@php
    $rows = collect($rows);
    $maxRow = max(1, (int) $rows->max('value'));
    $unit = $unit ?? 'u.';
    $emptyMessage = $emptyMessage ?? 'Aún no hay datos para mostrar.';
@endphp
<div class="rounded-xl border border-gray-200 bg-white p-4">
    <h2 class="text-[15px] font-semibold text-gray-900">{{ $title }}</h2>
    @if (! empty($subtitle))
        <p class="mb-3 text-xs text-gray-500">{{ $subtitle }}</p>
    @endif

    @forelse ($rows as $row)
        <div class="mb-3 last:mb-0">
            <div class="flex items-baseline justify-between gap-2 text-sm">
                <span class="truncate text-gray-900" title="{{ $row['label'] }}">
                    {{ $row['label'] }}
                    @if (! empty($row['sublabel']))
                        <span class="text-xs text-gray-500">· {{ $row['sublabel'] }}</span>
                    @endif
                </span>
                <span class="shrink-0 font-medium text-gray-900">{{ number_format($row['value']) }} {{ $unit }}</span>
            </div>
            <div class="mt-1 h-2 rounded bg-gray-100">
                <div class="h-2 rounded" style="width: {{ round($row['value'] / $maxRow * 100) }}%; background-color: {{ $color }}"></div>
            </div>
        </div>
    @empty
        <p class="py-3 text-sm text-gray-500">{{ $emptyMessage }}</p>
    @endforelse
</div>
