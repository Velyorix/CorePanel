<?php

namespace Database\Factories;

use App\Models\User;
use Core\KnowledgeBase\Enums\KbArticleStatus;
use Core\KnowledgeBase\Models\KbArticle;
use Core\KnowledgeBase\Models\KbCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KbArticle>
 */
class KbArticleFactory extends Factory
{
    protected $model = KbArticle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'category_id' => null,
            'title' => rtrim($title, '.'),
            'slug' => Str::slug($title).'-'.fake()->unique()->numerify('###'),
            'excerpt' => fake()->optional()->sentence(),
            'body' => fake()->paragraphs(3, true),
            'status' => KbArticleStatus::Draft,
            'author_id' => null,
            'published_at' => null,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function forCategory(KbCategory $category): static
    {
        return $this->state(fn (): array => [
            'category_id' => $category->id,
        ]);
    }

    public function authoredBy(User $user): static
    {
        return $this->state(fn (): array => [
            'author_id' => $user->id,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => KbArticleStatus::Published,
            'published_at' => now()->subHour(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => KbArticleStatus::Archived,
            'published_at' => now()->subDays(7),
        ]);
    }
}
