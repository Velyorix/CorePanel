@props([
    'series',
    'height' => 160,
])

@php
    /** @var \Core\Nodes\DataTransferObjects\NodeMonitoringChartSeries $series */
    $values = array_values(array_map(
        static fn (mixed $value): ?float => $value === null ? null : (float) $value,
        $series->values,
    ));
    $numericValues = array_values(array_filter(
        $values,
        static fn (?float $value): bool => $value !== null,
    ));
    $paddingY = 8;
    $plotHeight = 100 - ($paddingY * 2);
@endphp

<div {{ $attributes->class('space-y-3') }}>
    <div class="flex items-baseline justify-between gap-3">
        <p class="text-body-sm font-medium text-foreground">{{ $series->label }}</p>
        @if ($numericValues !== [])
            @php
                $latest = end($numericValues);
            @endphp
            <p class="text-small text-muted-foreground">
                {{ __('Latest') }}:
                <span class="font-mono text-foreground">{{ number_format($latest, $series->unit === '%' ? 1 : 0) }}</span>
                @if ($series->unit !== '')
                    {{ $series->unit }}
                @endif
            </p>
        @endif
    </div>

    @if ($numericValues === [])
        <div class="flex h-40 items-center justify-center rounded-md border border-dashed border-border bg-muted/30 px-4">
            <p class="text-small text-muted-foreground">{{ __('No metric data for this period.') }}</p>
        </div>
    @else
        @php
            $min = min($numericValues);
            $max = max($numericValues);
            $range = max($max - $min, 0.0001);
            $plotValues = $values === [] ? $numericValues : $values;
            $count = count($plotValues);
            $points = [];
            $labeledIndexes = array_keys(array_filter(
                $plotValues,
                static fn (?float $value): bool => $value !== null,
            ));
            $firstIndex = $labeledIndexes[0] ?? 0;
            $lastIndex = $labeledIndexes[array_key_last($labeledIndexes)] ?? $firstIndex;

            foreach ($plotValues as $index => $value) {
                if ($value === null) {
                    continue;
                }

                $x = $count === 1
                    ? 50
                    : ($index / max($count - 1, 1)) * 100;
                $y = $paddingY + ($plotHeight - ((($value - $min) / $range) * $plotHeight));
                $points[] = [
                    'x' => round($x, 2),
                    'y' => round($y, 2),
                ];
            }

            if (count($points) === 1) {
                $y = $points[0]['y'];
                $polyline = '0,'.$y.' 100,'.$y;
            } else {
                $polyline = collect($points)
                    ->map(static fn (array $point): string => $point['x'].','.$point['y'])
                    ->implode(' ');
            }

            $firstLabel = $series->labels[$firstIndex] ?? '';
            $lastLabel = $series->labels[$lastIndex] ?? $firstLabel;
        @endphp

        <div
            class="rounded-md border border-border bg-muted/20 p-3"
            role="img"
            aria-label="{{ __('Chart for :label', ['label' => $series->label]) }}"
        >
            <svg
                viewBox="0 0 100 100"
                preserveAspectRatio="none"
                class="h-40 w-full"
                style="height: {{ (int) $height }}px; color: var(--cp-accent);"
                aria-hidden="true"
            >
                <polyline
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    vector-effect="non-scaling-stroke"
                    points="{{ $polyline }}"
                />

                @foreach ($points as $point)
                    <circle
                        cx="{{ $point['x'] }}"
                        cy="{{ $point['y'] }}"
                        r="1.75"
                        fill="currentColor"
                    />
                @endforeach
            </svg>
            <div class="mt-2 flex justify-between text-small text-muted-foreground">
                <span>{{ $firstLabel }}</span>
                <span>{{ $lastLabel }}</span>
            </div>
        </div>
    @endif
</div>
