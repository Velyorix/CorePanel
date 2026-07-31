<?php

namespace Core\Clients\DataTransferObjects;

use Core\Clients\Enums\ClientMembershipRole;

readonly class ClientMembershipData
{
    /**
     * @param  list<string>|null  $permissions
     */
    public function __construct(
        public int $userId,
        public ClientMembershipRole $role,
        public ?array $permissions = null,
    ) {
    }

    /**
     * @param  array{
     *     user_id: int,
     *     role: string,
     *     permissions?: list<string>|null
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        $role = ClientMembershipRole::tryFrom((string) $data['role'])
            ?? throw new \InvalidArgumentException('Invalid client membership role.');

        $permissions = $data['permissions'] ?? null;

        if (is_array($permissions)) {
            $permissions = array_values(array_filter(
                array_map(static fn (mixed $value): string => trim((string) $value), $permissions),
                static fn (string $value): bool => $value !== '',
            ));

            $permissions = $permissions === [] ? null : $permissions;
        } else {
            $permissions = null;
        }

        return new self(
            userId: (int) $data['user_id'],
            role: $role,
            permissions: $permissions,
        );
    }
}
