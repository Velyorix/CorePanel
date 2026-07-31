<?php

namespace Core\API\Support;

use Illuminate\Http\Request;

/**
 * Resolve list pagination size from API query params.
 */
final class ApiPagination
{
    public static function defaultPerPage(): int
    {
        return max(1, (int) config('corepanel.api.pagination.default_per_page', 20));
    }

    public static function maxPerPage(): int
    {
        return max(1, (int) config('corepanel.api.pagination.max_per_page', 100));
    }

    public static function perPage(Request $request): int
    {
        $default = self::defaultPerPage();
        $max = self::maxPerPage();
        $raw = $request->query('per_page');

        if ($raw === null || $raw === '') {
            return min($max, $default);
        }

        $requested = (int) $raw;

        if ($requested < 1) {
            return min($max, $default);
        }

        return min($max, $requested);
    }
}
