<?php

namespace Tests\Feature\Marketplace;

use Core\License\Models\LicenseActivation;
use Core\License\Services\LicenseSettings;
use Core\Marketplace\DataTransferObjects\MarketplaceProduct;
use Core\Marketplace\Enums\MarketplaceEntitlementReason;
use Core\Marketplace\Enums\MarketplaceInstallAction;
use Core\Marketplace\Exceptions\MarketplaceEntitlementException;
use Core\Marketplace\Services\MarketplaceEntitlementGuard;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketplaceEntitlementGuardTest extends TestCase
{
    use RefreshDatabase;

    protected bool $configureValidLicenseByDefault = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.org.api_url' => 'https://corepanel.org/api/v1',
            'corepanel.org.api_token' => 'cpat_test_token_12345678901234567890',
            'corepanel.marketplace.enabled' => true,
            'corepanel.marketplace.cache.enabled' => false,
            'corepanel.marketplace.entitlements.enforce' => true,
            'corepanel.marketplace.entitlements.allow_free_without_entitlement' => true,
        ]);
    }

    public function test_guard_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(MarketplaceEntitlementGuard::class),
            app(MarketplaceEntitlementGuard::class),
        );
    }

    public function test_free_module_can_be_installed_without_entitlement(): void
    {
        $decision = app(MarketplaceEntitlementGuard::class)->evaluate(
            $this->product(slug: 'free-tools', sku: 'MOD_FREE_TOOLS', free: true),
        );

        $this->assertTrue($decision->canInstall());
        $this->assertSame(MarketplaceInstallAction::Install, $decision->action);
        $this->assertSame(MarketplaceEntitlementReason::AllowedFree, $decision->reason);
    }

    public function test_paid_module_is_blocked_without_license(): void
    {
        $decision = app(MarketplaceEntitlementGuard::class)->evaluate(
            $this->product(slug: 'stripe-billing-pro', sku: 'MOD_STRIPE_BILLING_PRO', free: false),
        );

        $this->assertFalse($decision->canInstall());
        $this->assertSame(MarketplaceInstallAction::Purchase, $decision->action);
        $this->assertSame(MarketplaceEntitlementReason::MissingLicense, $decision->reason);

        $this->expectException(MarketplaceEntitlementException::class);
        app(MarketplaceEntitlementGuard::class)->assertCanInstall(
            $this->product(slug: 'stripe-billing-pro', sku: 'MOD_STRIPE_BILLING_PRO', free: false),
        );
    }

    public function test_paid_module_is_blocked_when_entitlement_is_missing(): void
    {
        $this->activateLicense([
            [
                'product_type' => 'module',
                'product_sku' => 'other-module',
                'product_name' => 'Other Module',
            ],
        ]);

        $product = $this->product(slug: 'stripe-billing-pro', sku: 'MOD_STRIPE_BILLING_PRO', free: false);
        $decision = app(MarketplaceEntitlementGuard::class)->evaluate($product);

        $this->assertFalse($decision->canInstall());
        $this->assertSame(MarketplaceEntitlementReason::MissingEntitlement, $decision->reason);
        $this->assertSame(MarketplaceInstallAction::Purchase, $decision->action);

        try {
            app(MarketplaceEntitlementGuard::class)->assertCanInstall($product);
            $this->fail('Expected MarketplaceEntitlementException was not thrown.');
        } catch (MarketplaceEntitlementException $exception) {
            $this->assertSame(MarketplaceEntitlementReason::MissingEntitlement, $exception->decision->reason);
            $this->assertStringContainsString('stripe-billing-pro', $exception->getMessage());
        }
    }

    public function test_paid_module_install_allowed_when_sku_is_entitled(): void
    {
        $this->activateLicense([
            [
                'product_type' => 'module',
                'product_sku' => 'MOD_STRIPE_BILLING_PRO',
                'product_name' => 'Stripe Billing Pro',
            ],
        ]);

        $decision = app(MarketplaceEntitlementGuard::class)->assertCanInstall(
            $this->product(slug: 'stripe-billing-pro', sku: 'MOD_STRIPE_BILLING_PRO', free: false),
        );

        $this->assertTrue($decision->canInstall());
        $this->assertSame(MarketplaceEntitlementReason::AllowedEntitled, $decision->reason);
    }

    public function test_paid_theme_install_allowed_when_slug_matches_entitlement_sku(): void
    {
        $this->activateLicense([
            [
                'product_type' => 'theme',
                'product_sku' => 'ocean-theme',
                'product_name' => 'Ocean Theme',
            ],
        ]);

        $decision = app(MarketplaceEntitlementGuard::class)->evaluate(
            $this->product(
                slug: 'ocean-theme',
                sku: 'THM_OCEAN',
                free: false,
                productType: 'theme',
            ),
        );

        $this->assertTrue($decision->canInstall());
        $this->assertSame(MarketplaceEntitlementReason::AllowedEntitled, $decision->reason);
        $this->assertSame(MarketplaceInstallAction::Install, $decision->action);
    }

    public function test_assert_can_install_slug_fetches_product_and_enforces_entitlement(): void
    {
        $this->activateLicense([
            [
                'product_type' => 'module',
                'product_sku' => 'analytics-pro',
                'product_name' => 'Analytics Pro',
            ],
        ]);

        Http::fake([
            'https://corepanel.org/api/v1/marketplace/products/analytics-pro' => Http::response([
                'data' => [
                    'id' => '1',
                    'sku' => 'MOD_ANALYTICS_PRO',
                    'slug' => 'analytics-pro',
                    'name' => 'Analytics Pro',
                    'product_type' => 'module',
                    'pricing' => ['is_free' => false, 'amount' => 1990, 'currency' => 'EUR'],
                    'current_version' => '1.0.0',
                ],
            ], 200),
        ]);

        $product = app(MarketplaceEntitlementGuard::class)->assertCanInstallSlug('analytics-pro');

        $this->assertSame('analytics-pro', $product->slug);
        $this->assertTrue($product->isModule());
    }

    public function test_assert_can_install_slug_blocks_paid_product_without_entitlement(): void
    {
        $this->activateLicense([]);

        Http::fake([
            'https://corepanel.org/api/v1/marketplace/products/stripe-billing-pro' => Http::response([
                'data' => [
                    'id' => '1',
                    'sku' => 'MOD_STRIPE_BILLING_PRO',
                    'slug' => 'stripe-billing-pro',
                    'name' => 'Stripe Billing Pro',
                    'product_type' => 'module',
                    'pricing' => ['is_free' => false, 'amount' => 2990, 'currency' => 'EUR'],
                    'current_version' => '1.2.0',
                ],
            ], 200),
        ]);

        $this->expectException(MarketplaceEntitlementException::class);

        app(MarketplaceEntitlementGuard::class)->assertCanInstallSlug('stripe-billing-pro');
    }

    public function test_enforcement_can_be_disabled(): void
    {
        config(['corepanel.marketplace.entitlements.enforce' => false]);

        $decision = app(MarketplaceEntitlementGuard::class)->evaluate(
            $this->product(slug: 'stripe-billing-pro', sku: 'MOD_STRIPE', free: false),
        );

        $this->assertTrue($decision->canInstall());
        $this->assertSame(MarketplaceEntitlementReason::EnforcementDisabled, $decision->reason);
    }

    public function test_unsupported_product_type_cannot_be_installed(): void
    {
        $decision = app(MarketplaceEntitlementGuard::class)->evaluate(
            $this->product(
                slug: 'addon-pack',
                sku: 'ADDON_PACK',
                free: true,
                productType: 'addon',
            ),
        );

        $this->assertFalse($decision->canInstall());
        $this->assertSame(MarketplaceEntitlementReason::UnsupportedProductType, $decision->reason);
        $this->assertSame(MarketplaceInstallAction::View, $decision->action);
    }

    /**
     * @param  list<array<string, mixed>>  $entitlements
     */
    private function activateLicense(array $entitlements): void
    {
        app(LicenseSettings::class)->setInstanceId('cms-marketplace-01');

        LicenseActivation::query()->create([
            'instance_id' => 'cms-marketplace-01',
            'status' => 'active',
            'entitlements' => $entitlements,
            'updated_at' => now(),
        ]);
    }

    private function product(
        string $slug,
        string $sku,
        bool $free,
        string $productType = 'module',
    ): MarketplaceProduct {
        return MarketplaceProduct::fromArray([
            'id' => '11111111-1111-1111-1111-111111111111',
            'sku' => $sku,
            'slug' => $slug,
            'name' => $slug,
            'product_type' => $productType,
            'pricing' => [
                'is_free' => $free,
                'amount' => $free ? 0 : 2990,
                'currency' => 'EUR',
            ],
            'current_version' => '1.0.0',
        ]);
    }
}
