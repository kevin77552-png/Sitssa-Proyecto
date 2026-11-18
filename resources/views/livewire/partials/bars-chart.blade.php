@php
    $showValues = $showValues ?? true;
    $subtitle = $subtitle ?? null;
    $emptyMessage = $emptyMessage ?? 'Aún no hay datos para mostrar.';
    $allValues = array_merge(...array_map(fn ($seriesItem) => array_map('intval', $seriesItem['values']), $series));
    $maxValue = $allValues === [] ? 0 : max($allValues);
    $isEmpty = $maxValue === 0;

    if ($maxValue <= 4) {
        $top = 4;
    } else {
        $power = 10 ** (int) floor(log10($maxValue));
        $fraction = $maxValue / $power;
        $top = ($fraction <= 1 ? 1 : ($fraction <= 2 ? 2 : ($fraction <= 5 ? 5 : 10))) * $power;
    }

    $ticks = [0, (int) round($top / 2), $top];
    $barWidth = count($series) > 1 ? 'w-3.5' : 'w-5';
@endphp
<div class="rounded-xl border border-gray-200 bg-white p-4">
    <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
        <div>
            <h2 class="text-[15px] font-semibold text-gray-900">{{ $title }}</h2>
            @if ($subtitle)
                <p class="text-xs text-gray-500">{{ $subtitle }}</p>
            @endif
        </div>
        <div class="flex items-center gap-3 text-xs text-gray-600">
            @foreach ($series as $seriesItem)
                <span class="flex items-center gap-1">
                    <span class="inline-block h-2.5 w-2.5 rounded-sm" style="background-color: {{ $seriesItem['color'] }}"></span>
                    {{ $seriesItem['name'] }}
                </span>
            @endforeach
        </div>
    </div>

    <div class="flex gap-2">
        <div class="relative h-40 w-7 shrink-0">
            @foreach ($ticks as $tick)
                <span class="absolute right-0 text-[11px] text-gray-400" style="bottom: calc({{ round($tick / $top * 100) }}% - 7px)">{{ $tick }}</span>
            @endforeach
        </div>

        <div class="min-w-0 flex-1">
            <div class="relative h-40" role="img" aria-label="{{ $title }}">
                @foreach ([0, 50, 100] as $line)
                    <div class="absolute inset-x-0 border-t border-gray-100" style="bottom: {{ $line }}%"></div>
                @endforeach

                <div class="absolute inset-0 flex">
                    @foreach ($labels as $index => $label)
                        <div class="flex h-full flex-1 items-end justify-center gap-[3px]">
                            @foreach ($series as $seriesItem)
                                @php
                                    $value = (int) ($seriesItem['values'][$index] ?? 0);
                                    $percent = $value > 0 ? max(3, (int) round($value / $top * 100)) : 0;
                                @endphp
                                <div class="relative h-full {{ $barWidth }}" title="{{ $seriesItem['name'] }}: {{ $value }}">
                                    <div class="absolute inset-x-0 bottom-0 rounded-t" style="height: {{ $percent }}%; background-color: {{ $seriesItem['color'] }}"></div>
                                    @if ($showValues && $value > 0)
                                        <span class="absolute left-1/2 -translate-x-1/2 text-[11px] text-gray-600" style="bottom: calc({{ $percent }}% + 2px)">{{ $value }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>

                @if ($isEmpty)
                    <div class="absolute inset-0 flex items-center justify-center px-4 text-center text-sm text-gray-500">{{ $emptyMessage }}</div>
                @endif
            </div>

            <div class="mt-1.5 flex">
                @foreach ($labels as $label)
                    <div class="flex-1 truncate text-center text-[11px] text-gray-400">{{ $label }}</div>
                @endforeach
            </div>
        </div>
    </div>
</div>
