<?php

namespace App\Http\Requests\Admin\Concerns;

use Core\Products\Enums\BillingCycle;
use Core\Products\Models\Product;
use Core\Products\Models\ProductAddon;
use Core\Products\Models\ProductOption;

trait BuildsProductFormData
{
    /**
     * @return list<array<string, mixed>>
     */
    protected function pricingPayloadFromRequest(): array
    {
        $pricingInput = $this->input('pricing', []);

        if (! is_array($pricingInput)) {
            return [];
        }

        $tiers = [];

        foreach (BillingCycle::standard() as $cycle) {
            $row = $pricingInput[$cycle->value] ?? null;

            if (! is_array($row)) {
                continue;
            }

            $enabled = filter_var($row['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if (! $enabled) {
                continue;
            }

            $tiers[] = [
                'billing_cycle' => $cycle->value,
                'price' => $row['price'] ?? '0',
                'setup_fee' => $row['setup_fee'] ?? '0',
                'is_enabled' => true,
            ];
        }

        $custom = $pricingInput[BillingCycle::Custom->value] ?? null;

        if (is_array($custom) && filter_var($custom['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $tiers[] = [
                'billing_cycle' => BillingCycle::Custom->value,
                'custom_interval_days' => $custom['custom_interval_days'] ?? null,
                'price' => $custom['price'] ?? '0',
                'setup_fee' => $custom['setup_fee'] ?? '0',
                'is_enabled' => true,
            ];
        }

        return $tiers;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function existingOptionsPayload(Product $product): array
    {
        $product->loadMissing('options');

        return $product->options->map(function (ProductOption $option): array {
            return [
                'key' => $option->key,
                'name' => $option->name,
                'type' => $option->type->value,
                'required' => $option->required,
                'sort_order' => $option->sort_order,
                'config' => $option->config,
            ];
        })->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function existingAddonsPayload(Product $product): array
    {
        $product->loadMissing('addons');

        return $product->addons->map(function (ProductAddon $addon): array {
            return [
                'key' => $addon->key,
                'name' => $addon->name,
                'description' => $addon->description,
                'price' => $addon->price,
                'setup_fee' => $addon->setup_fee,
                'billing_cycle' => $addon->billing_cycle->value,
                'custom_interval_days' => $addon->custom_interval_days,
                'is_enabled' => $addon->is_enabled,
                'sort_order' => $addon->sort_order,
            ];
        })->values()->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function existingProvisioningRulesPayload(Product $product): ?array
    {
        $product->loadMissing('provisioningRules');

        $rules = $product->provisioningRules;

        if ($rules === null) {
            return null;
        }

        return [
            'auto_provision' => $rules->auto_provision,
            'send_welcome_email' => $rules->send_welcome_email,
            'welcome_email_template' => $rules->welcome_email_template,
            'node_group_key' => $rules->node_group_key,
            'node_group_id' => $rules->node_group_id,
            'config' => $rules->config,
        ];
    }
}
