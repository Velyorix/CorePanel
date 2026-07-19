<?php

namespace Core\Services\Services;

use Core\Services\Models\Service;
use Core\Services\Models\ServiceConfig;
use Illuminate\Support\Facades\DB;

/**
 * Encrypted per-service runtime config (credentials, IP, hostname, metadata).
 * Commercial options stay on services.config_data (plain JSON).
 */
class ServiceConfigService
{
    /**
     * @return array<string, mixed>
     */
    public function get(Service $service): array
    {
        $config = $service->config()->first();

        if ($config === null || ! is_array($config->data)) {
            return [];
        }

        return $config->data;
    }

    /**
     * Replace the full encrypted payload.
     *
     * @param  array<string, mixed>  $data
     */
    public function set(Service $service, array $data): ServiceConfig
    {
        return DB::transaction(function () use ($service, $data): ServiceConfig {
            $config = ServiceConfig::query()->updateOrCreate(
                ['service_id' => $service->id],
                ['data' => $data],
            );

            $this->syncDenormalizedFields($service, $data);

            return $config->fresh() ?? $config;
        });
    }

    /**
     * Top-level merge into the encrypted payload.
     *
     * @param  array<string, mixed>  $patch
     */
    public function merge(Service $service, array $patch): ServiceConfig
    {
        $merged = array_replace($this->get($service), $patch);

        return $this->set($service, $merged);
    }

    public function forget(Service $service): void
    {
        DB::transaction(function () use ($service): void {
            ServiceConfig::query()->where('service_id', $service->id)->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncDenormalizedFields(Service $service, array $data): void
    {
        $updates = [];

        if (array_key_exists('ip_address', $data)) {
            $ip = $data['ip_address'];
            $updates['ip_address'] = is_string($ip) && $ip !== '' ? $ip : null;
        }

        if (array_key_exists('hostname', $data)) {
            $hostname = $data['hostname'];
            $updates['hostname'] = is_string($hostname) && $hostname !== '' ? $hostname : null;
        }

        if ($updates === []) {
            return;
        }

        $service->forceFill($updates)->save();
    }
}
