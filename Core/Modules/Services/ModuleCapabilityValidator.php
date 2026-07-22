<?php

namespace Core\Modules\Services;

use Core\Modules\DataTransferObjects\ModuleHookManifest;
use Core\Modules\Enums\ModuleCapability;
use Core\Modules\Exceptions\InvalidModuleManifestException;

/**
 * Validates capability combinations and hook manifest rules.
 */
class ModuleCapabilityValidator
{
    /**
     * @param  list<string>  $capabilities
     *
     * @throws InvalidModuleManifestException
     */
    public function assertValid(array $capabilities, ModuleHookManifest $hooks): void
    {
        if ($hooks->isEmpty()) {
            $this->assertExtensionDependents($capabilities);

            return;
        }

        if (! in_array(ModuleCapability::Extension->value, $capabilities, true)) {
            throw InvalidModuleManifestException::capabilityRule(
                'Modules declaring [hooks] must include the [extension] capability.',
            );
        }

        $this->assertExtensionDependents($capabilities);
    }

    /**
     * @param  list<string>  $capabilities
     *
     * @throws InvalidModuleManifestException
     */
    private function assertExtensionDependents(array $capabilities): void
    {
        if (! in_array(ModuleCapability::NotificationChannel->value, $capabilities, true)) {
            return;
        }

        if (! in_array(ModuleCapability::Extension->value, $capabilities, true)) {
            throw InvalidModuleManifestException::capabilityRule(
                'The [notification_channel] capability requires [extension].',
            );
        }
    }
}
