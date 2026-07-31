<?php

namespace Tests\Feature\Billing;

use App\Models\User;
use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Enums\PaymentStatus;
use Core\Billing\Gateways\ManualTransferGateway;
use Core\Billing\Models\Invoice;
use Core\Billing\Services\ClientCreditService;
use Core\Billing\Services\GatewayManager;
use Core\Billing\Services\PaymentGatewayInjector;
use Core\Billing\Services\PaymentService;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Models\Client;
use Core\Modules\Enums\ModuleCapability;
use Core\Modules\Services\InstalledModuleRepository;
use Core\Modules\Services\ModuleFactory;
use Core\Modules\Services\ModuleManager;
use Core\Modules\Services\ModulePackageHasher;
use Core\Modules\Services\ModuleRequirementChecker;
use Core\Modules\Services\ModuleResourceLoader;
use Core\Modules\Services\ModuleSandbox;
use Core\Modules\Services\ModuleServiceProviderRegistrar;
use Core\Modules\Services\ModuleSignatureVerifier;
use Core\Orders\DataTransferObjects\CartItemData;
use Core\Orders\Services\CartService;
use Core\Orders\Services\CheckoutDraftService;
use Core\Products\Enums\BillingCycle;
use Core\Products\Enums\ProductStatus;
use Core\Products\Enums\ProductType;
use Core\Products\Models\Product;
use Core\Products\Models\ProductCategory;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\Billing\FakePaymentGateway;
use Tests\Support\Modules\StubGatewayModuleServiceProvider;
use Tests\TestCase;

/**
 * Cross-cutting Feature smoke for payment gateway registry, injection, checkout,
 * webhooks, and client-credit priority.
 */
class PaymentGatewayAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        StubGatewayModuleServiceProvider::$booted = false;

        $this->seed(RoleAndPermissionSeeder::class);

        $this->modulesPath = storage_path('framework/testing/modules-gateway-acceptance-'.uniqid('', true));
        File::ensureDirectoryExists($this->modulesPath);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.gateway-acceptance',
            'corepanel.modules.path' => $this->modulesPath,
            'corepanel.modules.enabled' => [],
            'corepanel.modules.auto_load_enabled' => false,
            'corepanel.modules.sandbox.enabled' => true,
            'corepanel.modules.signature.required' => false,
            'corepanel.modules.signature.verify_on_load' => false,
            'corepanel.themes.auto_load_active' => false,
            'corepanel.billing.client_credit.auto_apply_on_pay' => true,
            'corepanel.billing.manual_transfer.enabled' => true,
            'session.driver' => 'array',
            'app.key' => 'base64:'.base64_encode(str_repeat('e', 32)),
        ]);

        $this->withoutVite();
        $this->rebindModuleStack();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);

        parent::tearDown();
    }

    public function test_manual_gateway_is_active_by_default_and_usable_without_config(): void
    {
        config([
            'corepanel.billing.manual_transfer.beneficiary' => null,
            'corepanel.billing.manual_transfer.iban' => null,
            'corepanel.billing.manual_transfer.bic' => null,
            'corepanel.billing.manual_transfer.bank_name' => null,
            'corepanel.billing.manual_transfer.instructions' => null,
            'corepanel.billing.manual_transfer.label' => null,
        ]);

        $gateways = app(GatewayManager::class);

        $this->assertTrue($gateways->has(ManualTransferGateway::KEY));
        $this->assertTrue($gateways->isEnabled(ManualTransferGateway::KEY));

        [$user, $client] = $this->makeClientUser();
        $this->addCartLine($client, $this->makeProduct('manual-default-vps'));

        $this->actingAs($user)
            ->get(route('client.checkout.index'))
            ->assertOk()
            ->assertSee(__('Bank transfer / cheque'));

        $invoice = Invoice::factory()->unpaid()->create([
            'client_id' => $client->id,
            'subtotal' => '20.00',
            'tax_amount' => '0.00',
            'total_amount' => '20.00',
        ]);

        $payment = app(PaymentService::class)->initiate($invoice, ManualTransferGateway::KEY);

        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(ManualTransferGateway::KEY, $payment->method);
    }

    public function test_module_injected_gateways_can_be_enabled_and_shown_on_checkout(): void
    {
        $this->writeModule('acceptance_gateway', [
            'name' => 'acceptance_gateway',
            'version' => '1.0.0',
            'capabilities' => [ModuleCapability::PaymentGateway->value],
            'gateways' => [FakePaymentGateway::class],
        ]);

        $this->writeModule('gateway_demo', [
            'name' => 'gateway_demo',
            'version' => '1.0.0',
            'capabilities' => [ModuleCapability::PaymentGateway->value],
            'providers' => [StubGatewayModuleServiceProvider::class],
        ]);

        app(ModuleManager::class)->enable('acceptance_gateway');
        app(ModuleManager::class)->enable('gateway_demo');

        $gateways = app(GatewayManager::class);
        $gateways->sync();

        $this->assertTrue($gateways->has('fake'));
        $this->assertTrue($gateways->has('provider_fake'));
        $this->assertFalse($gateways->isEnabled('fake'));
        $this->assertFalse($gateways->isEnabled('provider_fake'));

        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.gateways.index'))
            ->assertOk()
            ->assertSee('fake')
            ->assertSee('provider_fake');

        $this->actingAs($admin)
            ->post(route('admin.gateways.enable', 'fake'))
            ->assertRedirect(route('admin.gateways.show', 'fake'));

        $gateways->enable('provider_fake');

        $this->assertTrue($gateways->isEnabled('fake'));
        $this->assertTrue($gateways->isEnabled('provider_fake'));

        [$user, $client] = $this->makeClientUser();
        $this->addCartLine($client, $this->makeProduct('injected-gateway-vps'));

        $this->actingAs($user)
            ->get(route('client.checkout.index'))
            ->assertOk()
            ->assertSee('value="fake"', false)
            ->assertSee('value="provider_fake"', false)
            ->assertSee(__('Bank transfer / cheque'));

        $this->actingAs($admin)
            ->post(route('admin.gateways.disable', 'fake'))
            ->assertRedirect(route('admin.gateways.show', 'fake'));

        $methods = collect(app(CheckoutDraftService::class)->paymentMethods())->pluck('key')->all();

        $this->assertNotContains('fake', $methods);
        $this->assertContains('provider_fake', $methods);
        $this->assertContains(ManualTransferGateway::KEY, $methods);

        $this->actingAs($user)
            ->get(route('client.checkout.index'))
            ->assertOk()
            ->assertDontSee('value="fake"', false)
            ->assertSee('value="provider_fake"', false)
            ->assertSee('Provider Fake Gateway');
    }

    public function test_webhook_is_routed_to_resolved_gateway_with_signature_check(): void
    {
        $fake = (new FakePaymentGateway)->withWebhookSupport('accept-signature');
        $gateways = app(GatewayManager::class);
        $gateways->flush();
        $gateways->register(app(ManualTransferGateway::class));
        $gateways->register($fake);
        $gateways->enable(ManualTransferGateway::KEY);
        $gateways->enable('fake');

        $invoice = Invoice::factory()->unpaid()->create([
            'subtotal' => '33.00',
            'tax_amount' => '0.00',
            'total_amount' => '33.00',
        ]);
        $payment = app(PaymentService::class)->initiate($invoice, 'fake');

        $this->postJson(route('webhooks.payments', ['gateway' => 'fake']), [
            'payment_id' => $payment->id,
            'transaction_id' => 'accept-txn',
        ], [
            'X-Signature' => 'accept-signature',
        ])
            ->assertOk()
            ->assertJsonPath('status', PaymentStatus::Completed->value);

        $this->assertSame(1, $fake->webhookCalls);
        $this->assertSame(PaymentStatus::Completed, $payment->fresh()->status);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);

        $this->postJson(route('webhooks.payments', ['gateway' => ManualTransferGateway::KEY]), [
            'payment_id' => $payment->id,
        ])->assertNotFound();
    }

    public function test_client_credit_is_applied_before_gateway_charge(): void
    {
        $fake = new FakePaymentGateway;
        $gateways = app(GatewayManager::class);
        $gateways->flush();
        $gateways->register($fake);
        $gateways->enable('fake');

        $client = Client::factory()->create(['credit_balance' => '0.00']);
        app(ClientCreditService::class)->add($client, '40.00');

        $invoice = Invoice::factory()->unpaid()->create([
            'client_id' => $client->id,
            'subtotal' => '100.00',
            'tax_amount' => '0.00',
            'total_amount' => '100.00',
        ]);

        $result = app(PaymentService::class)->collect($invoice, 'fake');

        $this->assertSame('40.00', $result->creditPayment?->amount);
        $this->assertSame(PaymentStatus::Completed, $result->creditPayment?->status);
        $this->assertSame('60.00', $result->gatewayPayment?->amount);
        $this->assertSame(1, $fake->createCalls);
        $this->assertSame('0.00', app(ClientCreditService::class)->balance($client));
        $this->assertTrue(
            $result->creditPayment !== null
            && $result->gatewayPayment !== null
            && $result->creditPayment->id < $result->gatewayPayment->id,
        );
    }

    private function rebindModuleStack(): void
    {
        $this->app->forgetInstance(ModulePackageHasher::class);
        $this->app->forgetInstance(ModuleSignatureVerifier::class);
        $this->app->forgetInstance(InstalledModuleRepository::class);
        $this->app->forgetInstance(ModuleFactory::class);
        $this->app->forgetInstance(ModuleRequirementChecker::class);
        $this->app->forgetInstance(ModuleServiceProviderRegistrar::class);
        $this->app->forgetInstance(ModuleResourceLoader::class);
        $this->app->forgetInstance(PaymentGatewayInjector::class);
        $this->app->forgetInstance(ModuleManager::class);

        $this->app->singleton(PaymentGatewayInjector::class);
        $this->app->singleton(ModuleManager::class, fn (): ModuleManager => new ModuleManager(
            app(InstalledModuleRepository::class),
            app(ModuleFactory::class),
            app(ModuleRequirementChecker::class),
            app(ModuleSandbox::class),
            app(ModuleServiceProviderRegistrar::class),
            app(ModuleResourceLoader::class),
            $this->modulesPath,
            app(PaymentGatewayInjector::class),
        ));
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function writeModule(string $directory, array $manifest): void
    {
        $path = $this->modulesPath.'/'.$directory;
        File::ensureDirectoryExists($path);
        File::put(
            $path.'/module.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );
    }

    /**
     * @param  array<string, mixed>  $clientAttributes
     * @return array{0: User, 1: Client}
     */
    private function makeClientUser(array $clientAttributes = []): array
    {
        $user = User::factory()->withRole('client')->create();
        $client = Client::factory()->create([
            'user_id' => $user->id,
            'country' => 'FR',
            ...$clientAttributes,
        ]);
        $client->users()->attach($user->id, [
            'role' => ClientMembershipRole::Owner->value,
        ]);

        return [$user, $client];
    }

    private function makeProduct(string $slug): Product
    {
        $category = ProductCategory::factory()->create([
            'slug' => 'gw-accept-cat-'.fake()->unique()->numerify('###'),
        ]);

        return Product::factory()->published()->withPricing(
            [BillingCycle::Monthly],
            '10.00',
            '0.00',
        )->create([
            'category_id' => $category->id,
            'name' => 'Gateway '.$slug,
            'slug' => $slug,
            'type' => ProductType::Vps,
            'status' => ProductStatus::Published,
        ]);
    }

    private function addCartLine(Client $client, Product $product): void
    {
        $cart = app(CartService::class)->getOrCreate($client, null);
        app(CartService::class)->addItem($cart, CartItemData::fromArray([
            'product_id' => $product->id,
            'billing_cycle' => BillingCycle::Monthly->value,
            'quantity' => 1,
        ]));
    }
}
