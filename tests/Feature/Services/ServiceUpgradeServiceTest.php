<?php

namespace Tests\Feature\Services;

use Carbon\Carbon;
use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Enums\ProrataDirection;
use Core\Billing\Models\Invoice;
use Core\Billing\Services\ClientCreditService;
use Core\Products\Enums\BillingCycle;
use Core\Products\Enums\ProductStatus;
use Core\Products\Enums\ProductType;
use Core\Products\Models\Product;
use Core\Products\Models\ProductCategory;
use Core\Services\DataTransferObjects\ServicePlanChangeData;
use Core\Services\Enums\ServiceAction;
use Core\Services\Enums\ServiceActionLogStatus;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Exceptions\InvalidServicePlanChangeException;
use Core\Services\Models\Service;
use Core\Services\Models\ServiceActionLog;
use Core\Services\Services\ServiceUpgradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceUpgradeServiceTest extends TestCase
{
    use RefreshDatabase;

    private ServiceUpgradeService $upgrades;

    private ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->upgrades = app(ServiceUpgradeService::class);
        $this->category = ProductCategory::factory()->create();

        Carbon::setTestNow(Carbon::parse('2026-01-16 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_upgrade_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(ServiceUpgradeService::class),
            app(ServiceUpgradeService::class),
        );
    }

    public function test_upgrade_updates_product_creates_charge_invoice_and_logs(): void
    {
        $current = $this->makeProduct('100.00', 'vps-basic');
        $target = $this->makeProduct('200.00', 'vps-pro');

        $service = Service::factory()->active()->create([
            'product_id' => $current->id,
            'module' => 'pterodactyl',
            'billing_cycle' => BillingCycle::Monthly,
            'next_billing_date' => Carbon::parse('2026-02-01'),
        ]);

        $result = $this->upgrades->changePlan($service, new ServicePlanChangeData(
            targetProductId: $target->id,
        ));

        $this->assertTrue($result->applied);
        $this->assertSame(ServiceAction::Upgrade, $result->action);
        $this->assertSame(ProrataDirection::Charge, $result->prorata->direction);
        $this->assertSame($target->id, $result->service->product_id);
        $this->assertSame(ServiceStatus::Active, $result->service->status);
        $this->assertNotNull($result->chargeInvoice);
        $this->assertSame(InvoiceStatus::Draft, $result->chargeInvoice->status);
        $this->assertSame($service->id, $result->chargeInvoice->items->first()->service_id);
        $this->assertSame($result->prorata->netAmount, $result->chargeInvoice->items->first()->unit_price);

        $log = ServiceActionLog::query()->where('service_id', $service->id)->latest('id')->firstOrFail();
        $this->assertSame(ServiceAction::Upgrade, $log->action);
        $this->assertSame(ServiceActionLogStatus::Success, $log->status);
        $this->assertSame($current->id, $log->response['from_product_id']);
        $this->assertSame($target->id, $log->response['to_product_id']);
    }

    public function test_downgrade_credits_wallet_and_logs(): void
    {
        $current = $this->makeProduct('200.00', 'vps-pro');
        $target = $this->makeProduct('100.00', 'vps-basic');

        $service = Service::factory()->active()->create([
            'product_id' => $current->id,
            'module' => 'pterodactyl',
            'billing_cycle' => BillingCycle::Monthly,
            'next_billing_date' => Carbon::parse('2026-02-01'),
            'client_id' => \Core\Clients\Models\Client::factory()->create(['credit_balance' => '0.00'])->id,
        ]);

        $result = $this->upgrades->changePlan($service, new ServicePlanChangeData(
            targetProductId: $target->id,
        ));

        $this->assertSame(ServiceAction::Downgrade, $result->action);
        $this->assertSame(ProrataDirection::Credit, $result->prorata->direction);
        $this->assertNull($result->chargeInvoice);
        $this->assertNotNull($result->creditTransaction);
        $this->assertSame(
            $result->prorata->netAmount,
            app(ClientCreditService::class)->balance($service->client),
        );
        $this->assertSame(0, Invoice::query()->count());

        $log = ServiceActionLog::query()->where('service_id', $service->id)->latest('id')->firstOrFail();
        $this->assertSame(ServiceAction::Downgrade, $log->action);
        $this->assertSame(ServiceActionLogStatus::Success, $log->status);
        $this->assertSame($current->id, $log->response['from_product_id']);
        $this->assertSame($target->id, $log->response['to_product_id']);
    }

    public function test_preview_does_not_persist_changes(): void
    {
        $current = $this->makeProduct('100.00', 'vps-a');
        $target = $this->makeProduct('200.00', 'vps-b');

        $service = Service::factory()->active()->create([
            'product_id' => $current->id,
            'module' => 'pterodactyl',
            'billing_cycle' => BillingCycle::Monthly,
            'next_billing_date' => Carbon::parse('2026-02-01'),
        ]);

        $result = $this->upgrades->preview($service, new ServicePlanChangeData(
            targetProductId: $target->id,
        ));

        $this->assertFalse($result->applied);
        $this->assertSame(ServiceAction::Upgrade, $result->action);
        $this->assertSame($current->id, $service->fresh()->product_id);
        $this->assertSame(0, ServiceActionLog::query()->count());
        $this->assertSame(0, Invoice::query()->count());
    }

    public function test_rejects_pending_service(): void
    {
        $target = $this->makeProduct('200.00', 'vps-pro');
        $service = Service::factory()->create([
            'product_id' => $this->makeProduct('100.00', 'vps-basic')->id,
            'module' => 'pterodactyl',
            'next_billing_date' => Carbon::parse('2026-02-01'),
        ]);

        $this->expectException(InvalidServicePlanChangeException::class);
        $this->expectExceptionMessage('Only active or suspended services can change plan.');

        $this->upgrades->changePlan($service, new ServicePlanChangeData(targetProductId: $target->id));
    }

    public function test_rejects_different_module(): void
    {
        $current = $this->makeProduct('100.00', 'vps-a', module: 'pterodactyl');
        $target = $this->makeProduct('200.00', 'vps-b', module: 'proxmox');

        $service = Service::factory()->active()->create([
            'product_id' => $current->id,
            'module' => 'pterodactyl',
            'next_billing_date' => Carbon::parse('2026-02-01'),
        ]);

        $this->expectException(InvalidServicePlanChangeException::class);
        $this->expectExceptionMessage('Target product must use the same module.');

        $this->upgrades->changePlan($service, new ServicePlanChangeData(targetProductId: $target->id));
    }

    public function test_rejects_unpublished_target(): void
    {
        $current = $this->makeProduct('100.00', 'vps-a');
        $target = $this->makeProduct('200.00', 'vps-b', status: ProductStatus::Draft);

        $service = Service::factory()->active()->create([
            'product_id' => $current->id,
            'module' => 'pterodactyl',
            'next_billing_date' => Carbon::parse('2026-02-01'),
        ]);

        $this->expectException(InvalidServicePlanChangeException::class);
        $this->expectExceptionMessage('Target product must be published.');

        $this->upgrades->changePlan($service, new ServicePlanChangeData(targetProductId: $target->id));
    }

    public function test_rejects_identical_plan(): void
    {
        $product = $this->makeProduct('100.00', 'vps-same');

        $service = Service::factory()->active()->create([
            'product_id' => $product->id,
            'module' => 'pterodactyl',
            'billing_cycle' => BillingCycle::Monthly,
            'next_billing_date' => Carbon::parse('2026-02-01'),
        ]);

        $this->expectException(InvalidServicePlanChangeException::class);
        $this->expectExceptionMessage('Service is already on the requested plan.');

        $this->upgrades->changePlan($service, new ServicePlanChangeData(targetProductId: $product->id));
    }

    public function test_apply_billing_false_skips_invoice_and_credit(): void
    {
        $current = $this->makeProduct('100.00', 'vps-a');
        $target = $this->makeProduct('200.00', 'vps-b');

        $service = Service::factory()->active()->create([
            'product_id' => $current->id,
            'module' => 'pterodactyl',
            'next_billing_date' => Carbon::parse('2026-02-01'),
        ]);

        $result = $this->upgrades->changePlan($service, new ServicePlanChangeData(
            targetProductId: $target->id,
            applyBilling: false,
        ));

        $this->assertTrue($result->applied);
        $this->assertSame($target->id, $result->service->product_id);
        $this->assertNull($result->chargeInvoice);
        $this->assertSame(0, Invoice::query()->count());
    }

    private function makeProduct(
        string $price,
        string $slug,
        string $module = 'pterodactyl',
        ProductStatus $status = ProductStatus::Published,
    ): Product {
        return Product::factory()
            ->ofType(ProductType::Vps)
            ->withModule($module)
            ->withPricing([BillingCycle::Monthly], $price)
            ->create([
                'category_id' => $this->category->id,
                'slug' => $slug.'-'.fake()->unique()->numerify('###'),
                'status' => $status,
            ]);
    }
}
