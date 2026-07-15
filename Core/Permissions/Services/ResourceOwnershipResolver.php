<?php

namespace Core\Permissions\Services;

use Core\Auth\Models\User;
use Core\Clients\Models\Client;

class ResourceOwnershipResolver
{
    public function userOwns(User $user, mixed $subject): bool
    {
        return match (true) {
            $subject instanceof User => $this->userOwnsUser($user, $subject),
            $subject instanceof Client => $this->userOwnsClient($user, $subject),
            default => false,
        };
    }

    private function userOwnsUser(User $user, User $subject): bool
    {
        return $user->is($subject);
    }

    private function userOwnsClient(User $user, Client $client): bool
    {
        if ($client->user_id === $user->id) {
            return true;
        }

        return $user->clients()->whereKey($client->getKey())->exists();
    }
}
