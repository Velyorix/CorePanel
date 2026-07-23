<?php

namespace Core\KnowledgeBase\DataTransferObjects;

use Core\KnowledgeBase\Enums\KbArticleStatus;
use Illuminate\Support\Str;
use InvalidArgumentException;

readonly class KbArticleData
{
    public function __construct(
        public string $title,
        public string $slug,
        public string $body,
        public ?int $categoryId = null,
        public ?string $excerpt = null,
        public KbArticleStatus $status = KbArticleStatus::Draft,
        public ?int $authorId = null,
        public int $sortOrder = 0,
    ) {
    }

    /**
     * @param  array{
     *     title?: string|null,
     *     slug?: string|null,
     *     body?: string|null,
     *     category_id?: int|null,
     *     excerpt?: string|null,
     *     status?: string|null,
     *     author_id?: int|null,
     *     sort_order?: int|null
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        $title = trim((string) ($data['title'] ?? ''));

        if ($title === '') {
            throw new InvalidArgumentException('The article title cannot be empty.');
        }

        $body = trim((string) ($data['body'] ?? ''));

        if ($body === '') {
            throw new InvalidArgumentException('The article body cannot be empty.');
        }

        $slugInput = trim((string) ($data['slug'] ?? ''));
        $slug = Str::slug($slugInput !== '' ? $slugInput : $title);

        if ($slug === '') {
            throw new InvalidArgumentException('The article slug is required.');
        }

        $status = KbArticleStatus::tryFrom((string) ($data['status'] ?? KbArticleStatus::Draft->value))
            ?? KbArticleStatus::Draft;

        $excerpt = isset($data['excerpt']) ? trim((string) $data['excerpt']) : null;

        return new self(
            title: $title,
            slug: $slug,
            body: $body,
            categoryId: isset($data['category_id']) ? (int) $data['category_id'] : null,
            excerpt: $excerpt === '' ? null : $excerpt,
            status: $status,
            authorId: isset($data['author_id']) ? (int) $data['author_id'] : null,
            sortOrder: max(0, (int) ($data['sort_order'] ?? 0)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'title' => $this->title,
            'slug' => $this->slug,
            'body' => $this->body,
            'category_id' => $this->categoryId,
            'excerpt' => $this->excerpt,
            'status' => $this->status->value,
            'author_id' => $this->authorId,
            'sort_order' => $this->sortOrder,
        ];
    }
}
