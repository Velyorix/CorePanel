<?php

namespace Tests\Feature\Marketplace;

use Core\Marketplace\DataTransferObjects\MarketplaceProduct;
use Core\Marketplace\DataTransferObjects\MarketplaceProductVersion;
use Core\Marketplace\Enums\MarketplaceCompatibilityReason;
use Core\Marketplace\Exceptions\MarketplaceCompatibilityException;
use Core\Marketplace\Services\MarketplaceCompatibilityGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceCompatibilityGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'corepanel.version' => '1.2.0',
            'corepanel.marketplace.compatibility.enforce' => true,
        ]);
    }

    public function test_guard_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(MarketplaceCompatibilityGuard::class),
            app(MarketplaceCompatibilityGuard::class),
        );
    }

    public function test_version_within_range_is_compatible(): void
    {
        $decision = app(MarketplaceCompatibilityGuard::class)->assertCompatible(
            $this->version(min: '1.0.0', max: '2.0.0'),
        );

        $this->assertTrue($decision->compatible);
        $this->assertSame(MarketplaceCompatibilityReason::Compatible, $decision->reason);
        $this->assertSame('1.2.0', $decision->cmsVersion);
    }

    public function test_cms_below_minimum_is_rejected(): void
    {
        $decision = app(MarketplaceCompatibilityGuard::class)->evaluate(
            $this->version(min: '2.0.0', max: null),
        );

        $this->assertFalse($decision->compatible);
        $this->assertSame(MarketplaceCompatibilityReason::BelowMinimum, $decision->reason);

        try {
            app(MarketplaceCompatibilityGuard::class)->assertCompatible(
                $this->version(min: '2.0.0', max: null),
            );
            $this->fail('Expected MarketplaceCompatibilityException was not thrown.');
        } catch (MarketplaceCompatibilityException $exception) {
            $this->assertSame(MarketplaceCompatibilityReason::BelowMinimum, $exception->decision->reason);
            $this->assertStringContainsString('2.0.0', $exception->getMessage());
            $this->assertStringContainsString('1.2.0', $exception->getMessage());
        }
    }

    public function test_cms_above_maximum_is_rejected(): void
    {
        $decision = app(MarketplaceCompatibilityGuard::class)->evaluate(
            $this->version(min: '1.0.0', max: '1.1.0'),
        );

        $this->assertFalse($decision->compatible);
        $this->assertSame(MarketplaceCompatibilityReason::AboveMaximum, $decision->reason);
        $this->assertStringContainsString('1.1.0', (string) $decision->message);
    }

    public function test_falls_back_to_product_level_compatibility(): void
    {
        $product = MarketplaceProduct::fromArray([
            'id' => '1',
            'sku' => 'MOD_A',
            'slug' => 'mod-a',
            'name' => 'Mod A',
            'product_type' => 'module',
            'pricing' => ['is_free' => true],
            'compatibility' => ['min_version' => '1.5.0', 'max_version' => null],
        ]);

        $decision = app(MarketplaceCompatibilityGuard::class)->evaluate(
            $this->version(min: null, max: null),
            $product,
        );

        $this->assertFalse($decision->compatible);
        $this->assertSame(MarketplaceCompatibilityReason::BelowMinimum, $decision->reason);
        $this->assertSame('1.5.0', $decision->minCmsVersion);
    }

    public function test_version_constraints_override_product_constraints(): void
    {
        $product = MarketplaceProduct::fromArray([
            'id' => '1',
            'sku' => 'MOD_A',
            'slug' => 'mod-a',
            'name' => 'Mod A',
            'product_type' => 'module',
            'pricing' => ['is_free' => true],
            'compatibility' => ['min_version' => '9.0.0'],
        ]);

        $decision = app(MarketplaceCompatibilityGuard::class)->evaluate(
            $this->version(min: '1.0.0', max: null),
            $product,
        );

        $this->assertTrue($decision->compatible);
        $this->assertSame('1.0.0', $decision->minCmsVersion);
    }

    public function test_missing_constraints_are_compatible(): void
    {
        $decision = app(MarketplaceCompatibilityGuard::class)->evaluate(
            $this->version(min: null, max: null),
        );

        $this->assertTrue($decision->compatible);
        $this->assertSame(MarketplaceCompatibilityReason::Compatible, $decision->reason);
    }

    public function test_dev_cms_version_normalizes_to_zero(): void
    {
        config(['corepanel.version' => '0.0.0-dev']);

        $decision = app(MarketplaceCompatibilityGuard::class)->evaluate(
            $this->version(min: '1.0.0', max: null),
        );

        $this->assertFalse($decision->compatible);
        $this->assertSame('0.0.0', $decision->cmsVersion);
        $this->assertSame(MarketplaceCompatibilityReason::BelowMinimum, $decision->reason);
    }

    public function test_enforcement_can_be_disabled(): void
    {
        config(['corepanel.marketplace.compatibility.enforce' => false]);

        $decision = app(MarketplaceCompatibilityGuard::class)->assertCompatible(
            $this->version(min: '9.9.9', max: null),
        );

        $this->assertTrue($decision->compatible);
        $this->assertSame(MarketplaceCompatibilityReason::EnforcementDisabled, $decision->reason);
    }

    private function version(?string $min, ?string $max): MarketplaceProductVersion
    {
        $compatibility = [];

        if ($min !== null) {
            $compatibility['min_version'] = $min;
        }

        if ($max !== null) {
            $compatibility['max_version'] = $max;
        }

        return MarketplaceProductVersion::fromArray([
            'id' => 'v1',
            'version' => '1.0.0',
            'is_latest' => true,
            'has_archive' => true,
            'compatibility' => $compatibility,
        ]);
    }
}
