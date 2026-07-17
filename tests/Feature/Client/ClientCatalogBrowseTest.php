<?php

namespace Tests\Feature\Client;

use App\Models\User;
use Core\Products\Enums\BillingCycle;
use Core\Products\Enums\ProductCategoryStatus;
use Core\Products\Enums\ProductStatus;
use Core\Products\Enums\ProductType;
use Core\Products\Models\Product;
use Core\Products\Models\ProductCategory;
use Core\Products\Services\CatalogService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientCatalogBrowseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.client-catalog',
        ]);
    }

    public function test_catalog_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(CatalogService::class),
            app(CatalogService::class),
        );
    }

    public function test_client_can_browse_active_categories_and_published_products(): void
    {
        $client = User::factory()->withRole('client')->create();

        $visible = ProductCategory::factory()->create([
            'name' => 'Visible VPS',
            'slug' => 'visible-vps',
            'status' => ProductCategoryStatus::Active,
        ]);
        ProductCategory::factory()->hidden()->create([
            'name' => 'Hidden Cat',
            'slug' => 'hidden-cat',
        ]);

        $published = Product::factory()->published()->withPricing(
            [BillingCycle::Monthly],
            '15.00',
            '0',
        )->create([
            'category_id' => $visible->id,
            'name' => 'Published Cloud',
            'slug' => 'published-cloud',
            'type' => ProductType::Vps,
        ]);

        Product::factory()->create([
            'category_id' => $visible->id,
            'name' => 'Draft Cloud',
            'slug' => 'draft-cloud',
            'status' => ProductStatus::Draft,
            'type' => ProductType::Vps,
        ]);

        $this->actingAs($client)
            ->get(route('client.catalog.index'))
            ->assertOk()
            ->assertSee('Visible VPS')
            ->assertDontSee('Hidden Cat');

        $this->actingAs($client)
            ->get(route('client.catalog.category', 'visible-vps'))
            ->assertOk()
            ->assertSee('Published Cloud')
            ->assertSee('15.00')
            ->assertDontSee('Draft Cloud');

        $this->actingAs($client)
            ->get(route('client.catalog.products.show', 'published-cloud'))
            ->assertOk()
            ->assertSee('Published Cloud')
            ->assertSee($published->type->label())
            ->assertSee(__('Configure'))
            ->assertSee(route('client.catalog.products.configure', 'published-cloud'), false);
    }

    public function test_hidden_category_and_draft_product_return_not_found(): void
    {
        $client = User::factory()->withRole('client')->create();

        ProductCategory::factory()->hidden()->create([
            'slug' => 'secret-cat',
        ]);

        $active = ProductCategory::factory()->create(['slug' => 'ok-cat']);
        Product::factory()->create([
            'category_id' => $active->id,
            'slug' => 'secret-product',
            'status' => ProductStatus::Draft,
        ]);

        $this->actingAs($client)
            ->get(route('client.catalog.category', 'secret-cat'))
            ->assertNotFound();

        $this->actingAs($client)
            ->get(route('client.catalog.products.show', 'secret-product'))
            ->assertNotFound();
    }

    public function test_published_product_in_hidden_category_is_not_visible(): void
    {
        $client = User::factory()->withRole('client')->create();
        $hidden = ProductCategory::factory()->hidden()->create(['slug' => 'hidden-parent']);

        Product::factory()->published()->withPricing()->create([
            'category_id' => $hidden->id,
            'name' => 'Leaked Product',
            'slug' => 'leaked-product',
        ]);

        $this->actingAs($client)
            ->get(route('client.catalog.products.show', 'leaked-product'))
            ->assertNotFound();
    }

    public function test_admin_without_client_access_cannot_browse_catalog(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('client.catalog.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_catalog(): void
    {
        $this->get(route('client.catalog.index'))
            ->assertRedirect(route('login'));
    }

    public function test_catalog_is_highlighted_in_client_navigation(): void
    {
        $client = User::factory()->withRole('client')->create();

        $this->actingAs($client)
            ->get(route('client.catalog.index'))
            ->assertOk()
            ->assertSee(route('client.catalog.index'), false)
            ->assertSee('aria-current="page"', false)
            ->assertSee(__('Catalog'), false);
    }
}
