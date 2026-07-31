<?php

namespace Core\Nodes\DataTransferObjects;

use Core\Nodes\Enums\NodeGroupStatus;
use Core\Nodes\Enums\NodeGroupType;
use Illuminate\Support\Str;
use InvalidArgumentException;

readonly class NodeGroupData
{
    public function __construct(
        public string $name,
        public string $key,
        public ?string $location = null,
        public NodeGroupType $type = NodeGroupType::General,
        public ?string $description = null,
        public NodeGroupStatus $status = NodeGroupStatus::Active,
        public int $sortOrder = 0,
        /** @var list<int>|null */
        public ?array $nodeIds = null,
        public bool $nodeIdsProvided = false,
    ) {
    }

    /**
     * @param  array{
     *     name?: string|null,
     *     key?: string|null,
     *     location?: string|null,
     *     type?: string|null,
     *     description?: string|null,
     *     status?: string|null,
     *     sort_order?: int|null,
     *     node_ids?: list<int|string>|null
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        $name = self::requiredString($data['name'] ?? null, 'name');
        $keyInput = self::nullableString($data['key'] ?? null);

        if ($keyInput !== null) {
            $key = Str::lower($keyInput);
        } else {
            $key = Str::slug($name, '_');
        }

        if ($key === '') {
            throw new InvalidArgumentException('The node group key is required.');
        }

        if (! preg_match('/^[a-z0-9]+(?:[_-][a-z0-9]+)*$/', $key)) {
            throw new InvalidArgumentException("Invalid node group key [{$key}].");
        }

        $typeValue = self::nullableString($data['type'] ?? null) ?? NodeGroupType::General->value;
        $type = NodeGroupType::tryFrom($typeValue);

        if ($type === null) {
            throw new InvalidArgumentException("Invalid node group type [{$typeValue}].");
        }

        $statusValue = self::nullableString($data['status'] ?? null) ?? NodeGroupStatus::Active->value;
        $status = NodeGroupStatus::tryFrom($statusValue);

        if ($status === null) {
            throw new InvalidArgumentException("Invalid node group status [{$statusValue}].");
        }

        $nodeIdsProvided = array_key_exists('node_ids', $data);
        $nodeIds = null;

        if ($nodeIdsProvided) {
            $nodeIds = self::normalizeNodeIds($data['node_ids']);
        }

        return new self(
            name: $name,
            key: $key,
            location: self::nullableString($data['location'] ?? null),
            type: $type,
            description: self::nullableString($data['description'] ?? null),
            status: $status,
            sortOrder: max(0, (int) ($data['sort_order'] ?? 0)),
            nodeIds: $nodeIds,
            nodeIdsProvided: $nodeIdsProvided,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'key' => $this->key,
            'location' => $this->location,
            'type' => $this->type->value,
            'description' => $this->description,
            'status' => $this->status->value,
            'sort_order' => $this->sortOrder,
        ];
    }

    private static function requiredString(mixed $value, string $field): string
    {
        $string = self::nullableString($value);

        if ($string === null) {
            throw new InvalidArgumentException("The {$field} is required.");
        }

        return $string;
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    /**
     * @return list<int>
     */
    private static function normalizeNodeIds(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException('Server ids must be an array.');
        }

        $ids = [];

        foreach ($value as $nodeId) {
            if (! is_int($nodeId) && ! ctype_digit((string) $nodeId)) {
                throw new InvalidArgumentException('Each server id must be an integer.');
            }

            $ids[] = (int) $nodeId;
        }

        return array_values(array_unique($ids));
    }
}
