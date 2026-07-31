<?php

namespace Core\Modules\Support;

use Core\Modules\Exceptions\InvalidModuleManifestException;
use Core\Modules\Exceptions\ModuleScaffoldException;
use JsonException;

/**
 * Reads and updates module.json manifest files.
 */
final class ModuleManifestWriter
{
    /**
     * @return array<string, mixed>
     */
    public static function read(string $moduleDirectory): array
    {
        $manifestPath = $moduleDirectory.DIRECTORY_SEPARATOR.'module.json';

        if (! is_file($manifestPath)) {
            throw InvalidModuleManifestException::missing($moduleDirectory);
        }

        $contents = file_get_contents($manifestPath);

        if ($contents === false) {
            throw InvalidModuleManifestException::unreadable($manifestPath);
        }

        try {
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw InvalidModuleManifestException::invalidField('module.json', $exception->getMessage());
        }

        if (! is_array($data)) {
            throw InvalidModuleManifestException::invalidField('module.json', 'must be a JSON object.');
        }

        return $data;
    }

    public static function write(string $moduleDirectory, array $data): void
    {
        $manifestPath = $moduleDirectory.DIRECTORY_SEPARATOR.'module.json';
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($encoded === false || file_put_contents($manifestPath, $encoded.PHP_EOL) === false) {
            throw ModuleScaffoldException::writeFailed($manifestPath);
        }
    }

    public static function addGateway(string $moduleDirectory, string $gatewayClass): bool
    {
        $gatewayClass = ltrim(trim($gatewayClass), '\\');

        if ($gatewayClass === '') {
            throw new \InvalidArgumentException('Gateway class cannot be empty.');
        }

        $data = self::read($moduleDirectory);
        $gateways = is_array($data['gateways'] ?? null) ? $data['gateways'] : [];

        if (in_array($gatewayClass, $gateways, true)) {
            return false;
        }

        $gateways[] = $gatewayClass;
        sort($gateways);
        $data['gateways'] = array_values($gateways);

        self::write($moduleDirectory, $data);

        return true;
    }

    public static function addProvider(string $moduleDirectory, string $providerClass): bool
    {
        $providerClass = ltrim(trim($providerClass), '\\');

        if ($providerClass === '') {
            throw new \InvalidArgumentException('Provider class cannot be empty.');
        }

        $data = self::read($moduleDirectory);
        $providers = is_array($data['providers'] ?? null) ? $data['providers'] : [];

        if (in_array($providerClass, $providers, true)) {
            return false;
        }

        $providers[] = $providerClass;
        sort($providers);
        $data['providers'] = array_values($providers);

        self::write($moduleDirectory, $data);

        return true;
    }

    /**
     * @param  array{name: string, description?: string|null}  $permission
     */
    public static function addPermission(string $moduleDirectory, array $permission): bool
    {
        $name = trim($permission['name'] ?? '');

        if ($name === '') {
            throw new \InvalidArgumentException('Permission name cannot be empty.');
        }

        $data = self::read($moduleDirectory);
        $permissions = is_array($data['permissions'] ?? null) ? $data['permissions'] : [];

        foreach ($permissions as $entry) {
            $existing = is_string($entry)
                ? $entry
                : (is_array($entry) ? ($entry['name'] ?? null) : null);

            if ($existing === $name) {
                return false;
            }
        }

        $entry = ['name' => $name];

        if (array_key_exists('description', $permission)) {
            $entry['description'] = $permission['description'];
        }

        $permissions[] = $entry;
        $data['permissions'] = $permissions;

        self::write($moduleDirectory, $data);

        return true;
    }
}
