<?php

namespace Core\API\Services;

use Core\API\Models\ApiToken;
use Core\API\Support\ApiScope;

/**
 * Resolve whether an API token grants one or more scopes.
 */
class ApiTokenScopeChecker
{
    /**
     * @param  list<string>  $requiredScopes
     */
    public function allows(ApiToken $token, array $requiredScopes): bool
    {
        if ($requiredScopes === []) {
            return true;
        }

        $granted = $token->permissions;

        // null = unrestricted (full access token).
        if ($granted === null) {
            return true;
        }

        if (! is_array($granted)) {
            return false;
        }

        if (in_array(ApiScope::ALL, $granted, true)) {
            return true;
        }

        foreach ($requiredScopes as $required) {
            if (! $this->grants($granted, (string) $required)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $granted
     */
    private function grants(array $granted, string $required): bool
    {
        if (in_array($required, $granted, true)) {
            return true;
        }

        // Support resource wildcards: api.service.* satisfies api.service.read
        $parts = explode('.', $required);

        if (count($parts) >= 2) {
            $wildcard = $parts[0].'.'.$parts[1].'.*';

            if (in_array($wildcard, $granted, true)) {
                return true;
            }
        }

        return false;
    }
}
