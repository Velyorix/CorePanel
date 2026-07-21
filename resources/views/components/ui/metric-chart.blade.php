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

            foreach ($plotValues as $index => $value) {
                if ($value === null) {
                    continue;
                }

                $x = $count === 1 ? 50 : ($index / max($count - 1, 1)) * 100;
                $y = 100 - (($value - $min) / $range) * 100;
                $points[] = round($x, 2).','.round($y, 2);
            }

            $polyline = implode(' ', $points);
            $firstLabel = $series->labels[0] ?? '';
            $lastLabel = $series->labels[array_key_last($series->labels)] ?? '';
        @endphp

        <div
            class="rounded-md border border-border bg-muted/20 p-3"
            role="img"
            aria-label="{{ __('Chart for :label', ['label' => $series->label]) }}"
        >
            <svg
                viewBox="0 0 100 100"
                preserveAspectRatio="none"
                class="h-40 w-full text-primary"
                style="height: {{ (int) $height }}px"
            >
                <polyline
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.75"
                    vector-effect="non-scaling-stroke"
                    points="{{ $polyline }}"
                />
            </svg>
            <div class="mt-2 flex justify-between text-small text-muted-foreground">
                <span>{{ $firstLabel }}</span>
                <span>{{ $lastLabel }}</span>
            </div>
        </div>
    @endif
</div>
