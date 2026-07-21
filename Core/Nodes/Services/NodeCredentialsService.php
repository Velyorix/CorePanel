<?php

namespace Core\Nodes\Services;

use Core\Nodes\Enums\NodeCredentialField;
use Core\Nodes\Models\Node;

class NodeCredentialsService
{
    /**
     * @param  array<string, mixed>|null  $input
     * @return array<string, string>|null
     */
    public function buildForCreate(?array $input): ?array
    {
        $normalized = $this->normalizeInput($input ?? [], preserveExisting: false);

        return $normalized === [] ? null : $normalized;
    }

    /**
     * @param  array<string, mixed>|null  $patch
     * @return array<string, string>|null
     */
    public function mergeForUpdate(Node $node, ?array $patch): ?array
    {
        $normalized = $this->normalizeInput(
            $patch ?? [],
            preserveExisting: true,
            existing: is_array($node->credentials) ? $node->credentials : [],
        );

        return $normalized === [] ? null : $normalized;
    }

    /**
     * @return list<string>
     */
    public function configuredKeys(Node $node): array
    {
        if (! is_array($node->credentials)) {
            return [];
        }

        return array_values(array_filter(
            NodeCredentialField::values(),
            fn (string $key): bool => filled($node->credentials[$key] ?? null),
        ));
    }

    public function hasConfiguredCredentials(Node $node): bool
    {
        return $this->configuredKeys($node) !== [];
    }

    /**
     * @return array<string, string>
     */
    public function maskedLabels(Node $node): array
    {
        $masked = [];

        foreach ($this->configuredKeys($node) as $key) {
            $field = NodeCredentialField::tryFrom($key);

            if ($field === null) {
                continue;
            }

            $masked[$key] = $field->label();
        }

        return $masked;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $existing
     * @return array<string, string>
     */
    private function normalizeInput(array $input, bool $preserveExisting, array $existing = []): array
    {
        $normalized = [];

        foreach (NodeCredentialField::values() as $key) {
            if (array_key_exists($key, $input)) {
                $value = trim((string) $input[$key]);

                if ($value !== '') {
                    $normalized[$key] = $value;

                    continue;
                }

                if ($preserveExisting && isset($existing[$key]) && filled($existing[$key])) {
                    $normalized[$key] = (string) $existing[$key];
                }

                continue;
            }

            if ($preserveExisting && isset($existing[$key]) && filled($existing[$key])) {
                $normalized[$key] = (string) $existing[$key];
            }
        }

        return $normalized;
    }
}
