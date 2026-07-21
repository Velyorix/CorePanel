<?php

namespace Core\Nodes\DataTransferObjects;

use Core\Nodes\Enums\NodeStatus;
use Core\Nodes\Enums\NodeType;
use InvalidArgumentException;

readonly class NodeData
{
    /**
     * @param  array<string, mixed>|null  $credentials
     * @param  array<string, mixed>|null  $config
     * @param  list<int>|null  $groupIds
     */
    public function __construct(
        public string $name,
        public string $hostname,
        public NodeType $type = NodeType::General,
        public ?string $module = null,
        public ?string $ipAddress = null,
        public ?string $apiUrl = null,
        public NodeStatus $status = NodeStatus::Active,
        public ?int $maxServices = null,
        public int $sortOrder = 0,
        public ?int $nodeGroupId = null,
        public ?array $credentials = null,
        public ?array $config = null,
        public ?array $groupIds = null,
    ) {
    }

    /**
     * @param  array{
     *     name?: string|null,
     *     hostname?: string|null,
     *     type?: string|null,
     *     module?: string|null,
     *     ip_address?: string|null,
     *     api_url?: string|null,
     *     status?: string|null,
     *     max_services?: int|null,
     *     sort_order?: int|null,
     *     node_group_id?: int|null,
     *     credentials?: array<string, mixed>|null,
     *     config?: array<string, mixed>|null,
     *     group_ids?: list<int>|null
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        $name = self::requiredString($data['name'] ?? null, 'name');
        $hostname = self::requiredString($data['hostname'] ?? null, 'hostname');

        $typeValue = self::nullableString($data['type'] ?? null) ?? NodeType::General->value;
        $type = NodeType::tryFrom($typeValue);

        if ($type === null) {
            throw new InvalidArgumentException("Invalid node type [{$typeValue}].");
        }

        $statusValue = self::nullableString($data['status'] ?? null) ?? NodeStatus::Active->value;
        $status = NodeStatus::tryFrom($statusValue);

        if ($status === null) {
            throw new InvalidArgumentException("Invalid node status [{$statusValue}].");
        }

        $maxServices = $data['max_services'] ?? null;

        if ($maxServices !== null) {
            $maxServices = max(0, (int) $maxServices);
        }

        $nodeGroupId = $data['node_group_id'] ?? null;

        if ($nodeGroupId !== null) {
            $nodeGroupId = (int) $nodeGroupId;
        }

        $groupIds = null;

        if (array_key_exists('group_ids', $data)) {
            $groupIds = self::normalizeGroupIds($data['group_ids']);
        }

        return new self(
            name: $name,
            hostname: $hostname,
            type: $type,
            module: self::nullableString($data['module'] ?? null),
            ipAddress: self::nullableString($data['ip_address'] ?? null),
            apiUrl: self::nullableString($data['api_url'] ?? null),
            status: $status,
            maxServices: $maxServices,
            sortOrder: max(0, (int) ($data['sort_order'] ?? 0)),
            nodeGroupId: $nodeGroupId,
            credentials: self::nullableArray($data['credentials'] ?? null),
            config: self::nullableArray($data['config'] ?? null),
            groupIds: $groupIds,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type->value,
            'module' => $this->module,
            'hostname' => $this->hostname,
            'ip_address' => $this->ipAddress,
            'api_url' => $this->apiUrl,
            'status' => $this->status->value,
            'max_services' => $this->maxServices,
            'sort_order' => $this->sortOrder,
            'node_group_id' => $this->nodeGroupId,
            'credentials' => $this->credentials,
            'config' => $this->config,
        ];
    }

    /**
     * @return list<int>
     */
    public function resolvedGroupIds(): array
    {
        if ($this->groupIds !== null) {
            return $this->groupIds;
        }

        if ($this->nodeGroupId !== null) {
            return [$this->nodeGroupId];
        }

        return [];
    }

    public function shouldSyncGroupRelations(): bool
    {
        return $this->groupIds !== null || $this->nodeGroupId !== null;
    }

    /**
     * @param  mixed  $value
     * @return list<int>
     */
    private static function normalizeGroupIds(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException('Group ids must be an array.');
        }

        $ids = [];

        foreach ($value as $groupId) {
            if (! is_int($groupId) && ! ctype_digit((string) $groupId)) {
                throw new InvalidArgumentException('Each group id must be an integer.');
            }

            $ids[] = (int) $groupId;
        }

        return array_values(array_unique($ids));
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
     * @return array<string, mixed>|null
     */
    private static function nullableArray(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException('Expected an array value.');
        }

        return $value;
    }
}
