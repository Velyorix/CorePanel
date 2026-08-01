<?php

namespace Database\Factories;

use Core\Auth\Models\User;
use Core\Webhooks\Enums\WebhookEvent;
use Core\Webhooks\Models\Webhook;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Webhook>
 */
class WebhookFactory extends Factory
{
    protected $model = Webhook::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'url' => 'https://hooks.example.test/corepanel',
            'secret' => 'cwhsec_'.Str::random(40),
            'events' => [WebhookEvent::InvoicePaid->value],
            'is_active' => true,
            'last_delivery_at' => null,
        ];
    }

    /**
     * @param  list<string>  $events
     */
    public function listening(array $events): static
    {
        return $this->state(fn (): array => [
            'events' => $events,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
