<?php

namespace Core\Products\DataTransferObjects;

use Core\Products\Enums\ProductCategoryStatus;
use Illuminate\Support\Str;
use InvalidArgumentException;

readonly class ProductCategoryData
{
    public function __construct(
        public string $name,
        public string $slug,
        public ?int $parentId = null,
        public ?string $description = null,
        public ProductCategoryStatus $status = ProductCategoryStatus::Active,
        public int $sortOrder = 0,
    ) {
    }

    /**
     * @param  array{
     *     name?: string|null,
     *     slug?: string|null,
     *     parent_id?: int|null,
     *     description?: string|null,
     *     status?: string|null,
     *     sort_order?: int|null
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        $name = self::requiredString($data['name'] ?? null, 'name');
        $slugInput = self::nullableString($data['slug'] ?? null);
        $slug = Str::slug($slugInput ?: $name);

        if ($slug === '') {
            throw new InvalidArgumentException('The slug is required.');
        }

        $statusValue = (string) ($data['status'] ?? ProductCategoryStatus::Active->value);
        $status = ProductCategoryStatus::tryFrom($statusValue);

        if ($status === null) {
            throw new InvalidArgumentException("Invalid category status [{$statusValue}].");
        }

        return new self(
            name: $name,
            slug: $slug,
            parentId: isset($data['parent_id']) && $data['parent_id'] !== ''
                ? (int) $data['parent_id']
                : null,
            description: self::nullableString($data['description'] ?? null),
            status: $status,
            sortOrder: max(0, (int) ($data['sort_order'] ?? 0)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'parent_id' => $this->parentId,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'status' => $this->status->value,
            'sort_order' => $this->sortOrder,
        ];
    }

    private static function requiredString(mixed $value, string $field): string
    {
        $string = self::nullableString($value);

        if ($string === null) {
            throw new InvalidArgumentException("The {$field} is required.");
        }

        return $string;
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
