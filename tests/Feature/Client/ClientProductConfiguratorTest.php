<?php

namespace Tests\Feature\Client;

use App\Models\User;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Models\Client;
use Core\Orders\Models\Cart;
use Core\Orders\Models\CartItem;
use Core\Products\Enums\BillingCycle;
use Core\Products\Enums\ProductOptionType;
use Core\Products\Enums\ProductStatus;
use Core\Products\Enums\ProductType;
use Core\Products\Models\Product;
use Core\Products\Models\ProductAddon;
use Core\Products\Models\ProductCategory;
use Core\Products\Models\ProductOption;
use Core\Products\Models\ProductPricing;
use Core\Products\Services\ConfiguratorService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientProductConfiguratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.client-configurator',
            'session.driver' => 'array',
        ]);
    }

    public function test_configurator_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(ConfiguratorService::class),
            app(ConfiguratorService::class),
        );
    }

    public function test_client_can_view_configurator_with_options_and_addons(): void
    {
        $user = User::factory()->withRole('client')->create();
        $product = $this->makeConfigurableProduct();

        ProductAddon::factory()->disabled()->create([
            'product_id' => $product->id,
            'key' => 'hidden_addon',
            'name' => 'Hidden Addon',
        ]);

        $this->actingAs($user)
            ->get(route('client.catalog.products.configure', $product->slug))
            ->assertOk()
            ->assertSee('Hostname')
            ->assertSee('RAM Size')
            ->assertSee('Daily Backup')
            ->assertDontSee('Hidden Addon')
            ->assertSee(__('Add to cart'));
    }

    public function test_show_page_links_to_configurator(): void
    {
        $user = User::factory()->withRole('client')->create();
        $product = $this->makeConfigurableProduct();

        $this->actingAs($user)
            ->get(route('client.catalog.products.show', $product->slug))
            ->assertOk()
            ->assertSee(route('client.catalog.products.configure', $product->slug), false)
            ->assertDontSee(__('Product configuration and add to cart will be available soon.'));
    }

    public function test_client_can_add_configured_product_to_cart(): void
    {
        $user = User::factory()->withRole('client')->create();
        $client = Client::factory()->create(['user_id' => $user->id]);
        $client->users()->attach($user->id, [
            'role' => ClientMembershipRole::Owner->value,
        ]);

        $product = $this->makeConfigurableProduct();

        $this->actingAs($user)
            ->post(route('client.catalog.products.configure.store', $product->slug), [
                'billing_cycle' => BillingCycle::Monthly->value,
                'quantity' => 1,
                'options' => [
                    'hostname' => 'node-01.example.test',
                    'ram_size' => '8gb',
                ],
                'addons' => ['backup'],
            ])
            ->assertRedirect(route('client.catalog.products.show', $product->slug))
            ->assertSessionHas('status');

        $cart = Cart::query()->where('client_id', $client->id)->firstOrFail();
        $item = CartItem::query()->where('cart_id', $cart->id)->firstOrFail();

        $this->assertSame($product->id, $item->product_id);
        $this->assertSame(BillingCycle::Monthly, $item->billing_cycle);
        $this->assertSame('32.99', $item->unit_price);
        $this->assertSame('6.00', $item->setup_fee);
        $this->assertSame([
            'hostname' => 'node-01.example.test',
            'ram_size' => '8gb',
        ], $item->options);
        $this->assertSame(['backup'], $item->addons);
    }

    public function test_price_preview_includes_options_addons_and_tax_stub(): void
    {
        $user = User::factory()->withRole('client')->create();
        $product = $this->makeConfigurableProduct();

        config([
            'corepanel.billing.tax_preview_rate' => 0.20,
            'corepanel.billing.tax_preview_label' => 'VAT (estimate)',
        ]);

        $this->actingAs($user)
            ->postJson(route('client.catalog.products.configure.preview', $product->slug), [
                'billing_cycle' => BillingCycle::Monthly->value,
                'quantity' => 2,
                'options' => [
                    'hostname' => 'node-01.example.test',
                    'ram_size' => '8gb',
                ],
                'addons' => ['backup'],
            ])
            ->assertOk()
            ->assertJson([
                'base_price' => '19.99',
                'option_deltas' => '10.00',
                'addons_recurring' => '3.00',
                'unit_price' => '32.99',
                'product_setup_fee' => '5.00',
                'addons_setup_fee' => '1.00',
                'setup_fee' => '6.00',
                'quantity' => 2,
                'recurring_subtotal' => '65.98',
                'first_payment_subtotal' => '71.98',
                'tax_rate' => '0.2000',
                'tax_label' => 'VAT (estimate)',
                'tax_is_estimate' => true,
                'tax_engine' => 'stub',
                'first_payment_tax' => '14.40',
                'first_payment_total' => '86.38',
            ]);
    }

    public function test_price_preview_allows_incomplete_required_options(): void
    {
        $user = User::factory()->withRole('client')->create();
        $product = $this->makeConfigurableProduct();

        $this->actingAs($user)
            ->postJson(route('client.catalog.products.configure.preview', $product->slug), [
                'billing_cycle' => BillingCycle::Monthly->value,
                'quantity' => 1,
            ])
            ->assertOk()
            ->assertJson([
                'base_price' => '19.99',
                'option_deltas' => '0.00',
                'unit_price' => '19.99',
                'setup_fee' => '5.00',
                'first_payment_subtotal' => '24.99',
            ]);
    }

    public function test_configurator_page_shows_live_summary_labels(): void
    {
        $user = User::factory()->withRole('client')->create();
        $product = $this->makeConfigurableProduct();

        $this->actingAs($user)
            ->get(route('client.catalog.products.configure', $product->slug))
            ->assertOk()
            ->assertSee(__('Unit price'))
            ->assertSee(__('First payment total'))
            ->assertSee(__('Tax is an estimate; final VAT is calculated at checkout.'));
    }

    public function test_missing_required_option_is_rejected(): void
    {
        $user = User::factory()->withRole('client')->create();
        $product = $this->makeConfigurableProduct();

        $this->actingAs($user)
            ->from(route('client.catalog.products.configure', $product->slug))
            ->post(route('client.catalog.products.configure.store', $product->slug), [
                'billing_cycle' => BillingCycle::Monthly->value,
                'options' => [
                    'ram_size' => '4gb',
                ],
            ])
            ->assertRedirect(route('client.catalog.products.configure', $product->slug))
            ->assertSessionHasErrors('configurator');

        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_invalid_select_value_is_rejected(): void
    {
        $user = User::factory()->withRole('client')->create();
        $product = $this->makeConfigurableProduct();

        $this->actingAs($user)
            ->from(route('client.catalog.products.configure', $product->slug))
            ->post(route('client.catalog.products.configure.store', $product->slug), [
                'billing_cycle' => BillingCycle::Monthly->value,
                'options' => [
                    'hostname' => 'ok.example.test',
                    'ram_size' => '64gb',
                ],
            ])
            ->assertRedirect(route('client.catalog.products.configure', $product->slug))
            ->assertSessionHasErrors('configurator');
    }

    public function test_unknown_addon_is_rejected(): void
    {
        $user = User::factory()->withRole('client')->create();
        $product = $this->makeConfigurableProduct();

        $this->actingAs($user)
            ->from(route('client.catalog.products.configure', $product->slug))
            ->post(route('client.catalog.products.configure.store', $product->slug), [
                'billing_cycle' => BillingCycle::Monthly->value,
                'options' => [
                    'hostname' => 'ok.example.test',
                    'ram_size' => '4gb',
                ],
                'addons' => ['missing'],
            ])
            ->assertRedirect(route('client.catalog.products.configure', $product->slug))
            ->assertSessionHasErrors('configurator');
    }

    public function test_disabled_billing_cycle_is_rejected(): void
    {
        $user = User::factory()->withRole('client')->create();
        $product = $this->makeConfigurableProduct();

        ProductPricing::factory()->disabled()->create([
            'product_id' => $product->id,
            'billing_cycle' => BillingCycle::Annual,
            'price' => '199.00',
        ]);

        $this->actingAs($user)
            ->post(route('client.catalog.products.configure.store', $product->slug), [
                'billing_cycle' => BillingCycle::Annual->value,
                'options' => [
                    'hostname' => 'ok.example.test',
                    'ram_size' => '4gb',
                ],
            ])
            ->assertSessionHasErrors('billing_cycle');
    }

    public function test_session_cart_is_used_when_user_has_no_client_account(): void
    {
        $user = User::factory()->withRole('client')->create();
        $product = $this->makeConfigurableProduct();

        $this->actingAs($user)
            ->post(route('client.catalog.products.configure.store', $product->slug), [
                'billing_cycle' => BillingCycle::Monthly->value,
                'options' => [
                    'hostname' => 'guest-style.example.test',
                    'ram_size' => '4gb',
                ],
            ])
            ->assertRedirect();

        $cart = Cart::query()->firstOrFail();
        $this->assertNull($cart->client_id);
        $this->assertNotNull($cart->session_id);
    }

    public function test_draft_product_configure_returns_not_found(): void
    {
        $user = User::factory()->withRole('client')->create();
        $category = ProductCategory::factory()->create(['slug' => 'cfg-draft-cat']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'slug' => 'draft-cfg',
            'status' => ProductStatus::Draft,
            'type' => ProductType::Vps,
        ]);

        $this->actingAs($user)
            ->get(route('client.catalog.products.configure', $product->slug))
            ->assertNotFound();
    }

    public function test_guest_is_redirected_from_configurator(): void
    {
        $product = $this->makeConfigurableProduct();

        $this->get(route('client.catalog.products.configure', $product->slug))
            ->assertRedirect(route('login'));
    }

    public function test_admin_without_client_access_cannot_configure(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $product = $this->makeConfigurableProduct();

        $this->actingAs($admin)
            ->get(route('client.catalog.products.configure', $product->slug))
            ->assertForbidden();
    }

    private function makeConfigurableProduct(): Product
    {
        $category = ProductCategory::factory()->create([
            'slug' => 'cfg-vps-'.fake()->unique()->numerify('###'),
        ]);

        $product = Product::factory()->published()->withPricing(
            [BillingCycle::Monthly],
            '19.99',
            '5.00',
        )->create([
            'category_id' => $category->id,
            'name' => 'Configurable Cloud',
            'slug' => 'configurable-cloud-'.fake()->unique()->numerify('###'),
            'type' => ProductType::Vps,
        ]);

        ProductOption::factory()->required()->create([
            'product_id' => $product->id,
            'key' => 'hostname',
            'name' => 'Hostname',
            'type' => ProductOptionType::Text,
            'config' => ['max_length' => 64],
            'sort_order' => 1,
        ]);

        ProductOption::factory()->required()->select([
            ['value' => '4gb', 'label' => '4 GB', 'price_delta' => 0],
            ['value' => '8gb', 'label' => '8 GB', 'price_delta' => 10],
        ])->create([
            'product_id' => $product->id,
            'key' => 'ram_size',
            'name' => 'RAM Size',
            'sort_order' => 2,
        ]);

        ProductAddon::factory()->create([
            'product_id' => $product->id,
            'key' => 'backup',
            'name' => 'Daily Backup',
            'price' => '3.00',
            'setup_fee' => '1.00',
            'billing_cycle' => BillingCycle::Monthly,
            'is_enabled' => true,
        ]);

        return $product->fresh(['options', 'addons', 'pricing', 'category']);
    }
}
