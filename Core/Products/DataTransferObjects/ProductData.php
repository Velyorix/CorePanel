<?php

namespace Core\Products\DataTransferObjects;

use Core\Products\Enums\ProductStatus;
use Core\Products\Enums\ProductType;
use Illuminate\Support\Str;
use InvalidArgumentException;

readonly class ProductData
{
    /**
     * @param  list<ProductPricingData>  $pricing
     * @param  list<ProductOptionData>  $options
     * @param  list<ProductAddonData>  $addons
     */
    public function __construct(
        public string $name,
        public string $slug,
        public ?int $categoryId = null,
        public ?string $description = null,
        public ProductType $type = ProductType::Other,
        public ?string $module = null,
        public ProductStatus $status = ProductStatus::Draft,
        public int $sortOrder = 0,
        public array $pricing = [],
        public array $options = [],
        public array $addons = [],
    ) {
    }

    /**
     * @param  array{
     *     name?: string|null,
     *     slug?: string|null,
     *     category_id?: int|null,
     *     description?: string|null,
     *     type?: string|null,
     *     module?: string|null,
     *     status?: string|null,
     *     sort_order?: int|null,
     *     pricing?: list<array<string, mixed>>|null,
     *     options?: list<array<string, mixed>>|null,
     *     addons?: list<array<string, mixed>>|null
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

        $typeValue = self::nullableString($data['type'] ?? null) ?? ProductType::Other->value;
        $type = ProductType::tryFrom($typeValue);

        if ($type === null) {
            throw new InvalidArgumentException("Invalid product type [{$typeValue}].");
        }

        $status = ProductStatus::tryFrom((string) ($data['status'] ?? ProductStatus::Draft->value))
            ?? ProductStatus::Draft;

        $pricing = [];
        $seenCycles = [];

        foreach ($data['pricing'] ?? [] as $tier) {
            if (! is_array($tier)) {
                throw new InvalidArgumentException('Each pricing tier must be an array.');
            }

            $pricingData = ProductPricingData::fromArray($tier);
            $cycle = $pricingData->billingCycle->value;

            if (isset($seenCycles[$cycle])) {
                throw new InvalidArgumentException("Duplicate billing cycle [{$cycle}].");
            }

            $seenCycles[$cycle] = true;
            $pricing[] = $pricingData;
        }

        $options = [];
        $seenOptionKeys = [];

        foreach ($data['options'] ?? [] as $option) {
            if (! is_array($option)) {
                throw new InvalidArgumentException('Each product option must be an array.');
            }

            $optionData = ProductOptionData::fromArray($option);

            if (isset($seenOptionKeys[$optionData->key])) {
                throw new InvalidArgumentException("Duplicate option key [{$optionData->key}].");
            }

            $seenOptionKeys[$optionData->key] = true;
            $options[] = $optionData;
        }

        $addons = [];
        $seenAddonKeys = [];

        foreach ($data['addons'] ?? [] as $addon) {
            if (! is_array($addon)) {
                throw new InvalidArgumentException('Each product addon must be an array.');
            }

            $addonData = ProductAddonData::fromArray($addon);

            if (isset($seenAddonKeys[$addonData->key])) {
                throw new InvalidArgumentException("Duplicate addon key [{$addonData->key}].");
            }

            $seenAddonKeys[$addonData->key] = true;
            $addons[] = $addonData;
        }

        return new self(
            name: $name,
            slug: $slug,
            categoryId: isset($data['category_id']) ? (int) $data['category_id'] : null,
            description: self::nullableString($data['description'] ?? null),
            type: $type,
            module: self::nullableString($data['module'] ?? null),
            status: $status,
            sortOrder: max(0, (int) ($data['sort_order'] ?? 0)),
            pricing: $pricing,
            options: $options,
            addons: $addons,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'category_id' => $this->categoryId,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'type' => $this->type->value,
            'module' => $this->module,
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
