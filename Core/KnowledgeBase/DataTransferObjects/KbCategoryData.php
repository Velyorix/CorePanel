<?php

namespace Core\KnowledgeBase\DataTransferObjects;

use Core\KnowledgeBase\Enums\KbCategoryStatus;
use Illuminate\Support\Str;
use InvalidArgumentException;

readonly class KbCategoryData
{
    public function __construct(
        public string $name,
        public string $slug,
        public ?string $description = null,
        public int $sortOrder = 0,
        public KbCategoryStatus $status = KbCategoryStatus::Active,
    ) {
    }

    /**
     * @param  array{
     *     name?: string|null,
     *     slug?: string|null,
     *     description?: string|null,
     *     sort_order?: int|null,
     *     status?: string|null
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw new InvalidArgumentException('The category name cannot be empty.');
        }

        $slugInput = trim((string) ($data['slug'] ?? ''));
        $slug = Str::slug($slugInput !== '' ? $slugInput : $name);

        if ($slug === '') {
            throw new InvalidArgumentException('The category slug is required.');
        }

        $status = KbCategoryStatus::tryFrom((string) ($data['status'] ?? KbCategoryStatus::Active->value))
            ?? KbCategoryStatus::Active;

        $description = isset($data['description']) ? trim((string) $data['description']) : null;

        return new self(
            name: $name,
            slug: $slug,
            description: $description === '' ? null : $description,
            sortOrder: max(0, (int) ($data['sort_order'] ?? 0)),
            status: $status,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'sort_order' => $this->sortOrder,
            'status' => $this->status->value,
        ];
    }
}
