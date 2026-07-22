<?php

namespace Core\Modules\DataTransferObjects;

use Core\Modules\Exceptions\InvalidModuleManifestException;

/**
 * Declarative hook, filter, and event subscriptions from module.json.
 */
final readonly class ModuleHookManifest
{
    /**
     * @param  list<string>  $events
     * @param  list<string>  $hooks
     * @param  list<string>  $filters
     */
    public function __construct(
        public array $events = [],
        public array $hooks = [],
        public array $filters = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->events === [] && $this->hooks === [] && $this->filters === [];
    }

    /**
     * @return array{events: list<string>, hooks: list<string>, filters: list<string>}
     */
    public function toArray(): array
    {
        return [
            'events' => $this->events,
            'hooks' => $this->hooks,
            'filters' => $this->filters,
        ];
    }

    /**
     * @throws InvalidModuleManifestException
     */
    public static function fromArray(mixed $data): self
    {
        if ($data === null) {
            return new self;
        }

        if (! is_array($data)) {
            throw InvalidModuleManifestException::invalidField('hooks', 'must be an object.');
        }

        return new self(
            events: self::normalizeNames($data['events'] ?? [], 'hooks.events'),
            hooks: self::normalizeNames($data['hooks'] ?? [], 'hooks.hooks'),
            filters: self::normalizeNames($data['filters'] ?? [], 'hooks.filters'),
        );
    }

    /**
     * @return list<string>
     *
     * @throws InvalidModuleManifestException
     */
    private static function normalizeNames(mixed $names, string $field): array
    {
        if ($names === null) {
            return [];
        }

        if (! is_array($names)) {
            throw InvalidModuleManifestException::invalidField($field, 'must be a list of hook or event names.');
        }

        $normalized = [];

        foreach ($names as $name) {
            if (! is_string($name) || trim($name) === '') {
                throw InvalidModuleManifestException::invalidField($field, 'entries must be non-empty strings.');
            }

            $name = trim($name);

            if (! preg_match('/^[a-z][a-z0-9_.-]*$/', $name)) {
                throw InvalidModuleManifestException::invalidField(
                    $field,
                    "entry [{$name}] is invalid. Use lowercase dotted names (e.g. invoice.paid).",
                );
            }

            $normalized[] = $name;
        }

        return array_values(array_unique($normalized));
    }
}
