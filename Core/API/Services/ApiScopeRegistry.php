<?php

namespace Core\API\Services;

use Core\API\Support\ApiScope;
use InvalidArgumentException;

/**
 * Catalogue of known API scopes (config + defaults).
 */
class ApiScopeRegistry
{
    /**
     * @return list<string>
     */
    public function all(): array
    {
        $configured = config('corepanel.api.scopes', []);

        if (! is_array($configured) || $configured === []) {
            return ApiScope::values();
        }

        $scopes = array_values(array_unique(array_filter(array_map(
            static fn (mixed $scope): string => trim((string) $scope),
            $configured,
        ))));

        return $scopes === [] ? ApiScope::values() : $scopes;
    }

    public function contains(string $scope): bool
    {
        return in_array($scope, $this->all(), true);
    }

    /**
     * @param  list<string>  $scopes
     * @return list<string>
     */
    public function assertKnown(array $scopes): array
    {
        $normalized = array_values(array_unique(array_filter(array_map(
            static fn (mixed $scope): string => trim((string) $scope),
            $scopes,
        ))));

        foreach ($normalized as $scope) {
            if (! $this->contains($scope)) {
                throw new InvalidArgumentException("Unknown API scope [{$scope}].");
            }
        }

        if (in_array(ApiScope::ALL, $normalized, true) && count($normalized) > 1) {
            throw new InvalidArgumentException('The wildcard scope [*] cannot be combined with other scopes.');
        }

        return $normalized;
    }
}
