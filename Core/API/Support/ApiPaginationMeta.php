<?php

namespace Core\API\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ApiPaginationMeta
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function fromPaginator(LengthAwarePaginator $paginator, array $filters = []): array
    {
        $meta = [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];

        $applied = self::normalizeFilters($filters);

        if ($applied !== []) {
            $meta['filters'] = $applied;
        }

        return $meta;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private static function normalizeFilters(array $filters): array
    {
        $normalized = [];

        foreach ($filters as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if ($value instanceof \BackedEnum) {
                $normalized[$key] = $value->value;

                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
