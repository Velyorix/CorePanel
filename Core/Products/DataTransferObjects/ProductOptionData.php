<?php

namespace Core\Products\DataTransferObjects;

use Core\Products\Enums\ProductOptionType;
use Illuminate\Support\Str;
use InvalidArgumentException;

readonly class ProductOptionData
{
    /**
     * @param  array<string, mixed>|null  $config
     */
    public function __construct(
        public string $key,
        public string $name,
        public ProductOptionType $type,
        public bool $required = false,
        public int $sortOrder = 0,
        public ?array $config = null,
    ) {
    }

    /**
     * @param  array{
     *     key?: string|null,
     *     name?: string|null,
     *     type?: string|null,
     *     required?: mixed,
     *     sort_order?: int|null,
     *     config?: array<string, mixed>|null
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        $name = self::requiredString($data['name'] ?? null, 'name');
        $keyInput = self::nullableString($data['key'] ?? null);
        $key = Str::slug($keyInput ?: $name, '_');

        if ($key === '') {
            throw new InvalidArgumentException('The option key is required.');
        }

        $typeValue = (string) ($data['type'] ?? '');
        $type = ProductOptionType::tryFrom($typeValue);

        if ($type === null) {
            throw new InvalidArgumentException("Invalid product option type [{$typeValue}].");
        }

        $config = $data['config'] ?? null;

        if ($config !== null && ! is_array($config)) {
            throw new InvalidArgumentException('The option config must be an array.');
        }

        self::assertConfigForType($type, $config);

        return new self(
            key: $key,
            name: $name,
            type: $type,
            required: filter_var($data['required'] ?? false, FILTER_VALIDATE_BOOLEAN),
            sortOrder: max(0, (int) ($data['sort_order'] ?? 0)),
            config: $config,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'type' => $this->type->value,
            'required' => $this->required,
            'sort_order' => $this->sortOrder,
            'config' => $this->config,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $config
     */
    private static function assertConfigForType(ProductOptionType $type, ?array $config): void
    {
        if ($type === ProductOptionType::Select) {
            $choices = $config['choices'] ?? null;

            if (! is_array($choices) || $choices === []) {
                throw new InvalidArgumentException('Select options require a non-empty choices config.');
            }

            foreach ($choices as $index => $choice) {
                if (! is_array($choice) || ! array_key_exists('value', $choice)) {
                    throw new InvalidArgumentException("Select choice [{$index}] must include a value.");
                }
            }
        }

        if ($type === ProductOptionType::Quantity && is_array($config)) {
            $min = $config['min'] ?? null;
            $max = $config['max'] ?? null;

            if ($min !== null && $max !== null && (float) $min > (float) $max) {
                throw new InvalidArgumentException('Quantity option min cannot be greater than max.');
            }
        }
    }

    private static function requiredString(mixed $value, string $field): string
    {
        $string = self::nullableString($value);

        if ($string === null) {
            throw new InvalidArgumentException("The option {$field} is required.");
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
