<?php

namespace Database\Factories;

use App\Models\User;
use Core\Auth\Models\UserSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserSession>
 */
class UserSessionFactory extends Factory
{
    protected $model = UserSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'session_id' => fake()->uuid(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'device_name' => fake()->randomElement(['Chrome on Windows', 'Safari on macOS', 'Firefox on Linux']),
            'last_activity_at' => now(),
            'expires_at' => now()->addHours(2),
            'created_at' => now(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subHour(),
        ]);
    }
}
