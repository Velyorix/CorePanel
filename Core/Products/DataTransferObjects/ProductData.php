<?php

namespace Core\Products\DataTransferObjects;

use Core\Products\Enums\ProductModuleCapability;
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
     * @param  list<string>  $moduleCapabilities
     */
    public function __construct(
        public string $name,
        public string $slug,
        public ?int $categoryId = null,
        public ?string $description = null,
        public ProductType $type = ProductType::Other,
        public ?string $module = null,
        public array $moduleCapabilities = [],
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
     *     module_capabilities?: list<string|ProductModuleCapability>|null,
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

        $module = self::normalizeModule($data['module'] ?? null);
        $moduleCapabilities = self::normalizeCapabilities($data['module_capabilities'] ?? []);

        if ($module === null && $moduleCapabilities !== []) {
            throw new InvalidArgumentException('Module capabilities require a provider module to be set.');
        }

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
            module: $module,
            moduleCapabilities: $moduleCapabilities,
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
            'module_capabilities' => $this->moduleCapabilities === [] ? null : $this->moduleCapabilities,
            'status' => $this->status->value,
            'sort_order' => $this->sortOrder,
        ];
    }

    private static function normalizeModule(mixed $value): ?string
    {
        $module = self::nullableString($value);

        if ($module === null) {
            return null;
        }

        $module = Str::lower($module);

        if (! preg_match('/^[a-z0-9]+(?:[_-][a-z0-9]+)*$/', $module)) {
            throw new InvalidArgumentException(
                'The module must be a lowercase slug (letters, numbers, hyphens, underscores).',
            );
        }

        return $module;
    }

    /**
     * @param  mixed  $capabilities
     * @return list<string>
     */
    private static function normalizeCapabilities(mixed $capabilities): array
    {
        if ($capabilities === null) {
            return [];
        }

        if (! is_array($capabilities)) {
            throw new InvalidArgumentException('Module capabilities must be an array.');
        }

        $normalized = [];
        $seen = [];

        foreach ($capabilities as $capability) {
            if ($capability instanceof ProductModuleCapability) {
                $value = $capability->value;
            } elseif (is_string($capability)) {
                $value = trim($capability);
            } else {
                throw new InvalidArgumentException('Each module capability must be a string.');
            }

            if ($value === '') {
                throw new InvalidArgumentException('Module capabilities cannot be empty.');
            }

            if (! preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $value)) {
                throw new InvalidArgumentException("Invalid module capability [{$value}].");
            }

            if (isset($seen[$value])) {
                throw new InvalidArgumentException("Duplicate module capability [{$value}].");
            }

            $seen[$value] = true;
            $normalized[] = $value;
        }

        return $normalized;
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
