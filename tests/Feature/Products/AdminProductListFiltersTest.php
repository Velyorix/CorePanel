<?php

namespace Tests\Feature\Products;

use App\Models\User;
use Core\Products\Enums\ProductStatus;
use Core\Products\Enums\ProductType;
use Core\Products\Models\Product;
use Core\Products\Models\ProductCategory;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProductListFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.admin-product-list',
        ]);
    }

    public function test_admin_can_search_products_by_name_and_slug(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        Product::factory()->create([
            'name' => 'Alpha VPS',
            'slug' => 'alpha-vps',
        ]);
        Product::factory()->create([
            'name' => 'Beta Hosting',
            'slug' => 'beta-hosting',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.products.index', ['q' => 'Alpha']))
            ->assertOk()
            ->assertSee('Alpha VPS')
            ->assertDontSee('Beta Hosting');

        $this->actingAs($admin)
            ->get(route('admin.products.index', ['q' => 'beta-hosting']))
            ->assertOk()
            ->assertSee('Beta Hosting')
            ->assertDontSee('Alpha VPS');
    }

    public function test_admin_can_filter_products_by_status_type_and_category(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $hosting = ProductCategory::factory()->create(['slug' => 'filter-hosting', 'name' => 'Hosting Cat']);
        $vps = ProductCategory::factory()->create(['slug' => 'filter-vps', 'name' => 'VPS Cat']);

        Product::factory()->create([
            'name' => 'Draft Hosting',
            'slug' => 'draft-hosting',
            'status' => ProductStatus::Draft,
            'type' => ProductType::Hosting,
            'category_id' => $hosting->id,
        ]);
        Product::factory()->create([
            'name' => 'Published VPS',
            'slug' => 'published-vps',
            'status' => ProductStatus::Published,
            'type' => ProductType::Vps,
            'category_id' => $vps->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.products.index', ['status' => ProductStatus::Published->value]))
            ->assertOk()
            ->assertSee('Published VPS')
            ->assertDontSee('Draft Hosting');

        $this->actingAs($admin)
            ->get(route('admin.products.index', ['type' => ProductType::Hosting->value]))
            ->assertOk()
            ->assertSee('Draft Hosting')
            ->assertDontSee('Published VPS');

        $this->actingAs($admin)
            ->get(route('admin.products.index', ['category_id' => $vps->id]))
            ->assertOk()
            ->assertSee('Published VPS')
            ->assertDontSee('Draft Hosting');
    }

    public function test_admin_can_sort_products_by_name(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        Product::factory()->create(['name' => 'Zebra Plan', 'slug' => 'zebra-plan']);
        Product::factory()->create(['name' => 'Alpha Plan', 'slug' => 'alpha-plan']);

        $response = $this->actingAs($admin)
            ->get(route('admin.products.index', [
                'sort' => 'name',
                'dir' => 'asc',
            ]))
            ->assertOk();

        $content = $response->getContent();
        $alphaPos = strpos($content, 'Alpha Plan');
        $zebraPos = strpos($content, 'Zebra Plan');

        $this->assertNotFalse($alphaPos);
        $this->assertNotFalse($zebraPos);
        $this->assertLessThan($zebraPos, $alphaPos);
    }

    public function test_invalid_filter_values_are_rejected(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.products.index', [
                'status' => 'unknown',
                'type' => 'spaceship',
                'sort' => 'password',
                'dir' => 'sideways',
            ]))
            ->assertSessionHasErrors(['status', 'type', 'sort', 'dir']);
    }

    public function test_filter_form_is_rendered_on_index(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('name="q"', false)
            ->assertSee('name="status"', false)
            ->assertSee('name="type"', false)
            ->assertSee('name="category_id"', false);
    }
}
