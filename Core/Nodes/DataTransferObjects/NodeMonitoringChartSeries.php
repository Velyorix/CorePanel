<?php

namespace Core\Nodes\DataTransferObjects;

final readonly class NodeMonitoringChartSeries
{
    /**
     * @param  list<float|null>  $values
     * @param  list<string>  $labels
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $unit,
        public array $values,
        public array $labels,
    ) {
    }

    public function hasData(): bool
    {
        foreach ($this->values as $value) {
            if ($value !== null) {
                return true;
            }
        }

        return false;
    }
}
