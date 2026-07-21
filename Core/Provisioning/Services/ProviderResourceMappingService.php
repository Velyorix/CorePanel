<?php

namespace Core\Provisioning\Services;

use Core\Providers\DataTransferObjects\ProvisioningResponse;
use Core\Provisioning\Enums\ProviderResourceType;
use Core\Provisioning\Exceptions\ProviderResourceMappingException;
use Core\Provisioning\Models\ProviderResourceMapping;
use Core\Services\Models\Service;
use Illuminate\Support\Facades\DB;

/**
 * Persists and resolves service ↔ provider resource identity mappings.
 */
class ProviderResourceMappingService
{
    /**
     * Upsert the primary server mapping from a successful provisioning response.
     *
     * Also keeps services.external_id in sync as a denormalized cache.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function syncFromProvisioningResponse(
        Service $service,
        ProvisioningResponse $response,
        ProviderResourceType $resourceType = ProviderResourceType::Server,
        array $metadata = [],
    ): ?ProviderResourceMapping {
        $externalId = $response->externalId;

        if ($externalId === null || trim($externalId) === '') {
            return $this->findForService($service, $resourceType);
        }

        $mergedMetadata = array_filter([
            ...$metadata,
            'hostname' => $response->hostname,
            'ip_address' => $response->ipAddress,
            'node_id' => $response->nodeId,
            'message' => $response->message,
            'payload' => $response->payload === [] ? null : $response->payload,
        ], static fn (mixed $value): bool => $value !== null);

        return $this->upsert(
            service: $service,
            externalId: $externalId,
            resourceType: $resourceType,
            metadata: $mergedMetadata,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function upsert(
        Service $service,
        string $externalId,
        ?string $module = null,
        ProviderResourceType $resourceType = ProviderResourceType::Server,
        array $metadata = [],
    ): ProviderResourceMapping {
        $module = trim((string) ($module ?? $service->module ?? ''));
        $externalId = trim($externalId);

        if ($module === '') {
            throw ProviderResourceMappingException::missingModule((int) $service->id);
        }

        if ($externalId === '') {
            throw ProviderResourceMappingException::missingExternalId((int) $service->id);
        }

        return DB::transaction(function () use ($service, $externalId, $module, $resourceType, $metadata): ProviderResourceMapping {
            $conflict = ProviderResourceMapping::query()
                ->where('module', $module)
                ->where('external_id', $externalId)
                ->where('resource_type', $resourceType)
                ->where('service_id', '!=', $service->id)
                ->first();

            if ($conflict !== null) {
                throw ProviderResourceMappingException::conflict($module, $externalId, $resourceType->value);
            }

            $mapping = ProviderResourceMapping::query()->updateOrCreate(
                [
                    'service_id' => $service->id,
                    'resource_type' => $resourceType,
                ],
                [
                    'module' => $module,
                    'external_id' => $externalId,
                    'metadata' => $metadata === [] ? null : $metadata,
                    'synced_at' => now(),
                ],
            );

            if ($resourceType === ProviderResourceType::Server && $service->external_id !== $externalId) {
                $service->forceFill(['external_id' => $externalId])->save();
            }

            return $mapping->fresh(['service']) ?? $mapping;
        });
    }

    public function findForService(
        Service|int $service,
        ProviderResourceType $resourceType = ProviderResourceType::Server,
    ): ?ProviderResourceMapping {
        $serviceId = $service instanceof Service ? (int) $service->id : $service;

        return ProviderResourceMapping::query()
            ->where('service_id', $serviceId)
            ->where('resource_type', $resourceType)
            ->first();
    }

    public function findByExternalId(
        string $module,
        string $externalId,
        ProviderResourceType $resourceType = ProviderResourceType::Server,
    ): ?ProviderResourceMapping {
        return ProviderResourceMapping::query()
            ->where('module', trim($module))
            ->where('external_id', trim($externalId))
            ->where('resource_type', $resourceType)
            ->with('service')
            ->first();
    }

    public function findServiceByExternalId(
        string $module,
        string $externalId,
        ProviderResourceType $resourceType = ProviderResourceType::Server,
    ): ?Service {
        return $this->findByExternalId($module, $externalId, $resourceType)?->service;
    }

    public function hasMapping(
        Service|int $service,
        ProviderResourceType $resourceType = ProviderResourceType::Server,
    ): bool {
        return $this->findForService($service, $resourceType) !== null;
    }

    public function forget(
        Service|int $service,
        ProviderResourceType $resourceType = ProviderResourceType::Server,
    ): bool {
        $serviceId = $service instanceof Service ? (int) $service->id : $service;

        $deleted = ProviderResourceMapping::query()
            ->where('service_id', $serviceId)
            ->where('resource_type', $resourceType)
            ->delete();

        return $deleted > 0;
    }
}
