<?php

namespace Core\Nodes\Enums;

enum NodeCredentialField: string
{
    case ApiKey = 'api_key';
    case ApiToken = 'api_token';
    case SshUsername = 'ssh_username';
    case SshPassword = 'ssh_password';
    case SshPrivateKey = 'ssh_private_key';

    public function label(): string
    {
        return match ($this) {
            self::ApiKey => __('API key'),
            self::ApiToken => __('API token'),
            self::SshUsername => __('SSH username'),
            self::SshPassword => __('SSH password'),
            self::SshPrivateKey => __('SSH private key'),
        };
    }

    public function isSecret(): bool
    {
        return $this !== self::SshUsername;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
