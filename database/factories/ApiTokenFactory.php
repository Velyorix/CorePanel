<?php

namespace Database\Factories;

use App\Models\User;
use Core\API\Models\ApiToken;
use Core\API\Services\ApiTokenService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiToken>
 */
class ApiTokenFactory extends Factory
{
    protected $model = ApiToken::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $service = app(ApiTokenService::class);
        $plainText = $service->generatePlainText();

        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'token_prefix' => $service->prefixOf($plainText),
            'token_hash' => $service->hash($plainText),
            'permissions' => null,
            'expires_at' => null,
            'last_used_at' => null,
            'revoked_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subHour(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => [
            'revoked_at' => now(),
        ]);
    }

    /**
     * @param  list<string>  $scopes
     */
    public function withScopes(array $scopes): static
    {
        return $this->state(fn (): array => [
            'permissions' => $scopes,
        ]);
    }

    public function unrestricted(): static
    {
        return $this->state(fn (): array => [
            'permissions' => null,
        ]);
    }
}
