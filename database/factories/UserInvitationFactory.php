<?php

namespace Database\Factories;

use Core\Auth\Models\UserInvitation;
use Core\Auth\Services\RegistrationGate;
use Core\Permissions\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserInvitation>
 */
class UserInvitationFactory extends Factory
{
    protected $model = UserInvitation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gate = app(RegistrationGate::class);

        return [
            'email' => fake()->unique()->safeEmail(),
            'token' => $gate->hashToken($gate->generateToken()),
            'role_id' => null,
            'invited_by' => null,
            'expires_at' => now()->addHours($gate->invitationTtlHours()),
            'accepted_at' => null,
            'created_at' => now(),
        ];
    }

    public function withRole(string $roleName): static
    {
        return $this->state(function () use ($roleName): array {
            $role = Role::query()->where('name', $roleName)->firstOrFail();

            return [
                'role_id' => $role->id,
            ];
        });
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subHour(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'accepted_at' => now(),
        ]);
    }
}
