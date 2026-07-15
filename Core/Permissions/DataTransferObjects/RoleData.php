<?php

namespace Core\Permissions\DataTransferObjects;

readonly class RoleData
{
    /**
     * @param  list<int>  $permissionIds
     * @param  list<int>  $userIds
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public ?int $parentId,
        public array $permissionIds,
        public array $userIds,
    ) {
    }

    /**
     * @param  array{
     *     name: string,
     *     description?: string|null,
     *     parent_id?: int|null,
     *     permission_ids?: list<int>|null,
     *     user_ids?: list<int>|null
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            description: $data['description'] ?? null,
            parentId: isset($data['parent_id']) ? (int) $data['parent_id'] : null,
            permissionIds: array_map('intval', $data['permission_ids'] ?? []),
            userIds: array_map('intval', $data['user_ids'] ?? []),
        );
    }
}
