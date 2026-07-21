<?php

namespace Core\Provisioning\Services;

use Core\Provisioning\Enums\ProviderResourceType;
use Core\Services\Models\Service;
use Illuminate\Support\Facades\DB;

/**
 * Clears local provisioning half-state after a failed create attempt.
 *
 * Does not call remote providers — only CorePanel-side fields and mappings.
 */
class ProvisioningRollbackService
{
    public function __construct(
        private readonly ProviderResourceMappingService $mappings,
    ) {
    }

    /**
     * @param  array{
     *     clear_node?: bool,
     *     clear_external_id?: bool,
     *     clear_mapping?: bool,
     *     clear_network_identity?: bool
     * }  $options
     * @return array{cleared: list<string>}
     */
    public function rollbackLocalState(Service $service, array $options = []): array
    {
        $clearNode = (bool) ($options['clear_node'] ?? false);
        $clearExternalId = (bool) ($options['clear_external_id'] ?? true);
        $clearMapping = (bool) ($options['clear_mapping'] ?? true);
        $clearNetworkIdentity = (bool) ($options['clear_network_identity'] ?? false);

        $cleared = [];

        DB::transaction(function () use (
            $service,
            $clearNode,
            $clearExternalId,
            $clearMapping,
            $clearNetworkIdentity,
            &$cleared,
        ): void {
            if ($clearMapping && $this->mappings->hasMapping($service)) {
                $this->mappings->forget($service, ProviderResourceType::Server);
                $cleared[] = 'mapping';
            }

            $updates = [];

            if ($clearExternalId && filled($service->external_id)) {
                $updates['external_id'] = null;
                $cleared[] = 'external_id';
            }

            if ($clearNode && $service->node_id !== null) {
                $updates['node_id'] = null;
                $cleared[] = 'node_id';
            }

            if ($clearNetworkIdentity) {
                if (filled($service->hostname)) {
                    $updates['hostname'] = null;
                    $cleared[] = 'hostname';
                }

                if (filled($service->ip_address)) {
                    $updates['ip_address'] = null;
                    $cleared[] = 'ip_address';
                }
            }

            if ($updates !== []) {
                $service->forceFill($updates)->save();
            }
        });

        return ['cleared' => array_values(array_unique($cleared))];
    }
}
