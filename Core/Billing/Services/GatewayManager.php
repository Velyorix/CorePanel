<?php

namespace Core\Billing\Services;

use Core\Billing\Contracts\PaymentGateway as PaymentGatewayContract;
use Core\Billing\Exceptions\DisabledPaymentGatewayException;
use Core\Billing\Exceptions\UnknownPaymentGatewayException;
use Core\Billing\Gateways\ManualTransferGateway;
use Core\Billing\Models\PaymentGateway;
use Illuminate\Support\Facades\Schema;

/**
 * Runtime registry + DB persistence for payment gateways.
 */
class GatewayManager
{
    /** @var array<string, PaymentGatewayContract> */
    private array $gateways = [];

    public function register(PaymentGatewayContract $gateway): void
    {
        $this->gateways[$gateway->key()] = $gateway;
    }

    public function has(string $key): bool
    {
        return isset($this->gateways[$key]);
    }

    /**
     * Resolve a registered gateway implementation.
     *
     * @throws UnknownPaymentGatewayException
     * @throws DisabledPaymentGatewayException
     */
    public function resolve(string $key, bool $onlyEnabled = true): PaymentGatewayContract
    {
        if (! isset($this->gateways[$key])) {
            throw UnknownPaymentGatewayException::forKey($key);
        }

        $this->sync();

        if ($onlyEnabled && ! $this->isEnabled($key)) {
            throw DisabledPaymentGatewayException::forKey($key);
        }

        return $this->gateways[$key];
    }

    /**
     * @deprecated Use resolve() — kept for transitional call sites.
     */
    public function get(string $key): PaymentGatewayContract
    {
        return $this->resolve($key, onlyEnabled: false);
    }

    /**
     * @return list<PaymentGatewayContract>
     */
    public function all(): array
    {
        return array_values($this->gateways);
    }

    /**
     * Registered gateways that are enabled in the database, ordered by sort_order.
     *
     * @return list<PaymentGatewayContract>
     */
    public function enabled(): array
    {
        $this->sync();

        if (! $this->ready()) {
            return array_values($this->gateways);
        }

        $order = PaymentGateway::query()
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('key')
            ->pluck('key')
            ->all();

        $enabled = [];

        foreach ($order as $key) {
            if (isset($this->gateways[$key])) {
                $enabled[] = $this->gateways[$key];
            }
        }

        return $enabled;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->gateways);
    }

    public function isEnabled(string $key): bool
    {
        $this->sync();

        $record = $this->record($key);

        if ($record !== null) {
            return (bool) $record->enabled;
        }

        return isset($this->gateways[$key]) && ! $this->ready();
    }

    public function record(string $key): ?PaymentGateway
    {
        if (! $this->ready()) {
            return null;
        }

        return PaymentGateway::query()->where('key', $key)->first();
    }

    public function enable(string $key): PaymentGateway
    {
        $record = $this->ensureRecord($key);
        $record->enabled = true;
        $record->save();

        return $record->refresh();
    }

    public function disable(string $key): PaymentGateway
    {
        $record = $this->ensureRecord($key);
        $record->enabled = false;
        $record->save();

        return $record->refresh();
    }

    /**
     * @param  array<string, mixed>|null  $config
     */
    public function configure(string $key, ?array $config, bool $merge = true): PaymentGateway
    {
        $record = $this->ensureRecord($key);

        if ($config === null) {
            $record->config = null;
        } elseif ($merge) {
            $existing = is_array($record->config) ? $record->config : [];
            $record->config = array_replace_recursive($existing, $config);
        } else {
            $record->config = $config;
        }

        $record->save();

        return $record->refresh();
    }

    /**
     * Upsert DB rows for every registered gateway without changing existing enabled/config.
     */
    public function sync(): void
    {
        if (! $this->ready() || $this->gateways === []) {
            return;
        }

        $nextSort = (int) PaymentGateway::query()->max('sort_order');

        foreach ($this->gateways as $key => $gateway) {
            if (PaymentGateway::query()->where('key', $key)->exists()) {
                continue;
            }

            $nextSort++;

            PaymentGateway::query()->create([
                'key' => $key,
                'enabled' => $this->shouldEnableByDefault($key),
                'config' => null,
                'sort_order' => $nextSort,
            ]);
        }
    }

    /**
     * Persist display order for registered gateways (1-based sort_order).
     *
     * @param  list<string>  $orderedKeys
     */
    public function reorder(array $orderedKeys): void
    {
        if (! $this->ready()) {
            return;
        }

        $this->sync();

        $position = 0;

        foreach ($orderedKeys as $key) {
            $key = trim((string) $key);

            if ($key === '' || ! $this->has($key)) {
                throw UnknownPaymentGatewayException::forKey($key !== '' ? $key : 'unknown');
            }

            $record = $this->ensureRecord($key);
            $record->sort_order = ++$position;
            $record->save();
        }
    }

    /**
     * Move a registered gateway one step up or down in display order.
     *
     * @param  'up'|'down'  $direction
     */
    public function move(string $key, string $direction): void
    {
        $ordered = $this->orderedKeys();
        $index = array_search($key, $ordered, true);

        if ($index === false) {
            throw UnknownPaymentGatewayException::forKey($key);
        }

        $swapWith = $direction === 'up' ? $index - 1 : $index + 1;

        if ($swapWith < 0 || $swapWith >= count($ordered)) {
            return;
        }

        [$ordered[$index], $ordered[$swapWith]] = [$ordered[$swapWith], $ordered[$index]];

        $this->reorder($ordered);
    }

    /**
     * @return list<string>
     */
    public function orderedKeys(): array
    {
        $this->sync();

        if (! $this->ready()) {
            return $this->keys();
        }

        $keys = PaymentGateway::query()
            ->whereIn('key', $this->keys())
            ->orderBy('sort_order')
            ->orderBy('key')
            ->pluck('key')
            ->all();

        foreach ($this->keys() as $key) {
            if (! in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    public function flush(): void
    {
        $this->gateways = [];
    }

    public function ready(): bool
    {
        try {
            return Schema::hasTable('payment_gateways');
        } catch (\Throwable) {
            return false;
        }
    }

    private function shouldEnableByDefault(string $key): bool
    {
        if ($key !== ManualTransferGateway::KEY) {
            return false;
        }

        return (bool) config('corepanel.billing.manual_transfer.enabled', true);
    }

    private function ensureRecord(string $key): PaymentGateway
    {
        if (! $this->ready()) {
            throw UnknownPaymentGatewayException::forKey($key);
        }

        if (! isset($this->gateways[$key])) {
            throw UnknownPaymentGatewayException::forKey($key);
        }

        $this->sync();

        $record = PaymentGateway::query()->where('key', $key)->first();

        if ($record === null) {
            throw UnknownPaymentGatewayException::forKey($key);
        }

        return $record;
    }
}
