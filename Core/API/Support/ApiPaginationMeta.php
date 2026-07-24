<?php

namespace Core\API\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ApiPaginationMeta
{
    /**
     * @return array{current_page: int, per_page: int, total: int, last_page: int}
     */
    public static function fromPaginator(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
