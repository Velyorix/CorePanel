<?php

namespace Core\Products\DataTransferObjects;

use Illuminate\Support\Str;
use InvalidArgumentException;

readonly class ProductProvisioningRulesData
{
    /**
     * @param  array<string, mixed>|null  $config
     */
    public function __construct(
        public bool $autoProvision = false,
        public bool $sendWelcomeEmail = true,
        public ?string $welcomeEmailTemplate = null,
        public ?string $nodeGroupKey = null,
        public ?array $config = null,
    ) {
    }

    /**
     * @param  array{
     *     auto_provision?: mixed,
     *     send_welcome_email?: mixed,
     *     welcome_email_template?: string|null,
     *     node_group_key?: string|null,
     *     config?: array<string, mixed>|null
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        $config = $data['config'] ?? null;

        if ($config !== null && ! is_array($config)) {
            throw new InvalidArgumentException('Provisioning rules config must be an array.');
        }

        return new self(
            autoProvision: filter_var($data['auto_provision'] ?? false, FILTER_VALIDATE_BOOLEAN),
            sendWelcomeEmail: filter_var($data['send_welcome_email'] ?? true, FILTER_VALIDATE_BOOLEAN),
            welcomeEmailTemplate: self::normalizeSlug($data['welcome_email_template'] ?? null, 'welcome email template', allowDots: true),
            nodeGroupKey: self::normalizeSlug($data['node_group_key'] ?? null, 'node group key'),
            config: $config,
        );
    }

    public static function defaults(): self
    {
        return new self;
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'auto_provision' => $this->autoProvision,
            'send_welcome_email' => $this->sendWelcomeEmail,
            'welcome_email_template' => $this->welcomeEmailTemplate,
            'node_group_key' => $this->nodeGroupKey,
            'config' => $this->config,
        ];
    }

    private static function normalizeSlug(mixed $value, string $field, bool $allowDots = false): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        if ($string === '') {
            return null;
        }

        $string = Str::lower($string);
        $pattern = $allowDots
            ? '/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/'
            : '/^[a-z0-9]+(?:[_-][a-z0-9]+)*$/';

        if (! preg_match($pattern, $string)) {
            throw new InvalidArgumentException("Invalid {$field} [{$string}].");
        }

        return $string;
    }
}
