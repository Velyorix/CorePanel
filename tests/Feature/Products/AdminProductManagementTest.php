<?php

namespace Tests\Feature\Products;

use App\Models\User;
use Core\Products\Enums\BillingCycle;
use Core\Products\Enums\ProductCategoryStatus;
use Core\Products\Enums\ProductStatus;
use Core\Products\Enums\ProductType;
use Core\Products\Models\Product;
use Core\Products\Models\ProductCategory;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProductManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.admin-products',
        ]);
    }

    public function test_admin_can_view_products_index(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $product = Product::factory()->create([
            'name' => 'VPS Starter',
            'slug' => 'vps-starter',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('VPS Starter')
            ->assertSee($product->slug);
    }

    public function test_support_can_view_but_cannot_create_products(): void
    {
        $support = User::factory()->withRole('support')->create();

        $this->actingAs($support)
            ->get(route('admin.products.index'))
            ->assertOk();

        $this->actingAs($support)
            ->get(route('admin.products.create'))
            ->assertForbidden();

        $this->actingAs($support)
            ->post(route('admin.products.store'), [
                'name' => 'Blocked Product',
                'type' => ProductType::Vps->value,
            ])
            ->assertForbidden();
    }

    public function test_client_role_cannot_access_admin_products(): void
    {
        $clientUser = User::factory()->withRole('client')->create();

        $this->actingAs($clientUser)
            ->get(route('admin.products.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_admin_products(): void
    {
        $this->get(route('admin.products.index'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_create_update_publish_and_delete_product(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $category = ProductCategory::factory()->create([
            'name' => 'VPS',
            'slug' => 'vps',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.products.store'), [
                'name' => 'Cloud VPS',
                'slug' => 'cloud-vps',
                'category_id' => $category->id,
                'type' => ProductType::Vps->value,
                'description' => 'A starter VPS plan',
                'sort_order' => 10,
                'pricing' => [
                    BillingCycle::Monthly->value => [
                        'enabled' => '1',
                        'price' => '19.99',
                        'setup_fee' => '5.00',
                    ],
                ],
            ])
            ->assertRedirect();

        $product = Product::query()->where('slug', 'cloud-vps')->firstOrFail();

        $this->assertSame('Cloud VPS', $product->name);
        $this->assertSame($category->id, $product->category_id);
        $this->assertTrue($product->status === ProductStatus::Draft);
        $this->assertDatabaseHas('product_pricing', [
            'product_id' => $product->id,
            'billing_cycle' => BillingCycle::Monthly->value,
            'price' => '19.99',
            'setup_fee' => '5.00',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.products.show', $product))
            ->assertOk()
            ->assertSee('Cloud VPS')
            ->assertSee('19.99');

        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), [
                'name' => 'Cloud VPS Pro',
                'slug' => 'cloud-vps-pro',
                'category_id' => $category->id,
                'type' => ProductType::Vps->value,
                'sort_order' => 5,
                'pricing' => [
                    BillingCycle::Monthly->value => [
                        'enabled' => '1',
                        'price' => '29.99',
                        'setup_fee' => '0',
                    ],
                    BillingCycle::Annual->value => [
                        'enabled' => '1',
                        'price' => '299.00',
                        'setup_fee' => '0',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.products.show', $product));

        $product->refresh();

        $this->assertSame('Cloud VPS Pro', $product->name);
        $this->assertSame('cloud-vps-pro', $product->slug);
        $this->assertTrue($product->status === ProductStatus::Draft);
        $this->assertCount(2, $product->pricing);

        $this->actingAs($admin)
            ->post(route('admin.products.publish', $product))
            ->assertRedirect(route('admin.products.show', $product));

        $this->assertTrue($product->fresh()->status === ProductStatus::Published);

        $this->actingAs($admin)
            ->delete(route('admin.products.destroy', $product))
            ->assertRedirect(route('admin.products.index'));

        $this->assertSoftDeleted($product);
    }

    public function test_admin_cannot_change_status_via_update_payload(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $product = Product::factory()->create([
            'status' => ProductStatus::Published,
            'name' => 'Published Plan',
            'slug' => 'published-plan',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), [
                'name' => 'Published Plan',
                'slug' => 'published-plan',
                'type' => $product->type->value,
                'status' => ProductStatus::Archived->value,
                'pricing' => [],
            ])
            ->assertRedirect(route('admin.products.show', $product));

        $this->assertTrue($product->fresh()->status === ProductStatus::Published);
    }

    public function test_admin_can_unpublish_archive_and_restore_product(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $product = Product::factory()->create([
            'status' => ProductStatus::Draft,
            'name' => 'Lifecycle Plan',
            'slug' => 'lifecycle-plan',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.products.publish', $product))
            ->assertRedirect(route('admin.products.show', $product));
        $this->assertTrue($product->fresh()->status === ProductStatus::Published);

        $this->actingAs($admin)
            ->post(route('admin.products.unpublish', $product))
            ->assertRedirect(route('admin.products.show', $product));
        $this->assertTrue($product->fresh()->status === ProductStatus::Draft);

        $this->actingAs($admin)
            ->post(route('admin.products.publish', $product))
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.products.archive', $product))
            ->assertRedirect(route('admin.products.show', $product));
        $this->assertTrue($product->fresh()->status === ProductStatus::Archived);

        $this->actingAs($admin)
            ->post(route('admin.products.restore', $product))
            ->assertRedirect(route('admin.products.show', $product));
        $this->assertTrue($product->fresh()->status === ProductStatus::Draft);
    }

    public function test_invalid_status_transition_returns_error(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $product = Product::factory()->create([
            'status' => ProductStatus::Archived,
            'slug' => 'archived-plan',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.products.show', $product))
            ->post(route('admin.products.publish', $product))
            ->assertRedirect(route('admin.products.show', $product))
            ->assertSessionHasErrors('status');

        $this->assertTrue($product->fresh()->status === ProductStatus::Archived);
    }

    public function test_support_cannot_publish_products(): void
    {
        $support = User::factory()->withRole('support')->create();
        $product = Product::factory()->create([
            'status' => ProductStatus::Draft,
            'slug' => 'support-blocked',
        ]);

        $this->actingAs($support)
            ->post(route('admin.products.publish', $product))
            ->assertForbidden();

        $this->assertTrue($product->fresh()->status === ProductStatus::Draft);
    }

    public function test_admin_can_manage_categories(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->post(route('admin.product-categories.store'), [
                'name' => 'Hosting',
                'slug' => 'hosting',
                'status' => ProductCategoryStatus::Active->value,
                'sort_order' => 1,
            ])
            ->assertRedirect();

        $category = ProductCategory::query()->where('slug', 'hosting')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.product-categories.show', $category))
            ->assertOk()
            ->assertSee('Hosting');

        $this->actingAs($admin)
            ->put(route('admin.product-categories.update', $category), [
                'name' => 'Web Hosting',
                'slug' => 'web-hosting',
                'status' => ProductCategoryStatus::Hidden->value,
                'sort_order' => 2,
            ])
            ->assertRedirect(route('admin.product-categories.show', $category));

        $category->refresh();

        $this->assertSame('Web Hosting', $category->name);
        $this->assertTrue($category->status === ProductCategoryStatus::Hidden);

        $this->actingAs($admin)
            ->delete(route('admin.product-categories.destroy', $category))
            ->assertRedirect(route('admin.product-categories.index'));

        $this->assertSoftDeleted($category);
    }

    public function test_cannot_delete_category_with_products(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $category = ProductCategory::factory()->create(['slug' => 'busy-cat']);
        Product::factory()->create([
            'category_id' => $category->id,
            'slug' => 'child-product',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.product-categories.show', $category))
            ->delete(route('admin.product-categories.destroy', $category))
            ->assertRedirect(route('admin.product-categories.show', $category))
            ->assertSessionHasErrors('category');

        $this->assertDatabaseHas('product_categories', [
            'id' => $category->id,
            'deleted_at' => null,
        ]);
    }
}
