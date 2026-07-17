<?php

namespace Tests\Feature\Client;

use App\Models\User;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Models\Client;
use Core\Orders\Models\CartItem;
use Core\Products\Enums\BillingCycle;
use Core\Products\Enums\ProductType;
use Core\Products\Models\Product;
use Core\Products\Models\ProductCategory;
use Core\Products\Support\HostnameValidator;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class HostnameDomainConfigureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.hostname-configure',
            'session.driver' => 'array',
        ]);
    }

    public function test_hostname_validator_accepts_fqdn_and_rejects_invalid(): void
    {
        $this->assertSame(
            'node-01.example.com',
            HostnameValidator::assertValid('Node-01.Example.COM.'),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('fully qualified domain name');

        HostnameValidator::assertValid('localhost');
    }

    public function test_vps_configure_shows_dedicated_hostname_field_without_product_option(): void
    {
        $user = User::factory()->withRole('client')->create();
        $product = $this->makeTypedProduct(ProductType::Vps, 'vps-host-only');

        $this->actingAs($user)
            ->get(route('client.catalog.products.configure', $product->slug))
            ->assertOk()
            ->assertSee(__('Hostname'))
            ->assertSee(__('Enter a fully qualified hostname, e.g. node-01.example.com'));
    }

    public function test_domain_product_configure_shows_domain_field(): void
    {
        $user = User::factory()->withRole('client')->create();
        $product = $this->makeTypedProduct(ProductType::Domain, 'domain-only');

        $this->actingAs($user)
            ->get(route('client.catalog.products.configure', $product->slug))
            ->assertOk()
            ->assertSee(__('Domain name'))
            ->assertSee(__('Enter the domain name to register or manage, e.g. example.com'));
    }

    public function test_vps_add_to_cart_requires_hostname_even_without_option_row(): void
    {
        $user = User::factory()->withRole('client')->create();
        $client = Client::factory()->create(['user_id' => $user->id]);
        $client->users()->attach($user->id, [
            'role' => ClientMembershipRole::Owner->value,
        ]);
        $product = $this->makeTypedProduct(ProductType::Vps, 'vps-require-host');

        $this->actingAs($user)
            ->from(route('client.catalog.products.configure', $product->slug))
            ->post(route('client.catalog.products.configure.store', $product->slug), [
                'billing_cycle' => BillingCycle::Monthly->value,
            ])
            ->assertRedirect(route('client.catalog.products.configure', $product->slug))
            ->assertSessionHasErrors('configurator');

        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_vps_rejects_invalid_hostname(): void
    {
        $user = User::factory()->withRole('client')->create();
        $product = $this->makeTypedProduct(ProductType::Vps, 'vps-bad-host');

        $this->actingAs($user)
            ->from(route('client.catalog.products.configure', $product->slug))
            ->post(route('client.catalog.products.configure.store', $product->slug), [
                'billing_cycle' => BillingCycle::Monthly->value,
                'options' => [
                    'hostname' => 'not a host',
                ],
            ])
            ->assertRedirect(route('client.catalog.products.configure', $product->slug))
            ->assertSessionHasErrors('configurator');
    }

    public function test_domain_product_stores_normalized_domain_option(): void
    {
        $user = User::factory()->withRole('client')->create();
        $client = Client::factory()->create(['user_id' => $user->id]);
        $client->users()->attach($user->id, [
            'role' => ClientMembershipRole::Owner->value,
        ]);
        $product = $this->makeTypedProduct(ProductType::Domain, 'domain-store');

        $this->actingAs($user)
            ->post(route('client.catalog.products.configure.store', $product->slug), [
                'billing_cycle' => BillingCycle::Monthly->value,
                'options' => [
                    'domain' => 'Example.COM.',
                ],
            ])
            ->assertRedirect(route('client.cart.index'));

        $item = CartItem::query()->firstOrFail();
        $this->assertSame(['domain' => 'example.com'], $item->options);
    }

    public function test_other_product_does_not_require_hostname(): void
    {
        $user = User::factory()->withRole('client')->create();
        $client = Client::factory()->create(['user_id' => $user->id]);
        $client->users()->attach($user->id, [
            'role' => ClientMembershipRole::Owner->value,
        ]);
        $product = $this->makeTypedProduct(ProductType::Other, 'other-no-host');

        $this->actingAs($user)
            ->get(route('client.catalog.products.configure', $product->slug))
            ->assertOk()
            ->assertDontSee(__('Enter a fully qualified hostname, e.g. node-01.example.com'));

        $this->actingAs($user)
            ->post(route('client.catalog.products.configure.store', $product->slug), [
                'billing_cycle' => BillingCycle::Monthly->value,
            ])
            ->assertRedirect(route('client.cart.index'));

        $this->assertSame(1, CartItem::query()->count());
        $this->assertNull(CartItem::query()->first()->options);
    }

    private function makeTypedProduct(ProductType $type, string $slug): Product
    {
        $category = ProductCategory::factory()->create([
            'slug' => 'host-cat-'.fake()->unique()->numerify('###'),
        ]);

        return Product::factory()->published()->withPricing(
            [BillingCycle::Monthly],
            '9.99',
            '0.00',
        )->create([
            'category_id' => $category->id,
            'name' => ucfirst($slug),
            'slug' => $slug,
            'type' => $type,
        ])->fresh(['options', 'pricing', 'category']);
    }
}
