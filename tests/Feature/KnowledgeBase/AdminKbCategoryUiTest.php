<?php

namespace Tests\Feature\KnowledgeBase;

use App\Models\User;
use Core\KnowledgeBase\Enums\KbCategoryStatus;
use Core\KnowledgeBase\Models\KbCategory;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminKbCategoryUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.admin-kb-categories',
        ]);
    }

    public function test_admin_can_view_categories_index(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        KbCategory::factory()->create([
            'name' => 'Account',
            'slug' => 'account',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.kb-categories.index'))
            ->assertOk()
            ->assertSee('Account')
            ->assertSee('account');
    }

    public function test_admin_can_create_update_and_delete_category(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->post(route('admin.kb-categories.store'), [
                'name' => 'Billing help',
                'slug' => 'billing-help',
                'description' => 'Payment and invoices',
                'status' => KbCategoryStatus::Active->value,
                'sort_order' => 5,
            ])
            ->assertRedirect(route('admin.kb-categories.index'))
            ->assertSessionHas('status');

        $category = KbCategory::query()->where('slug', 'billing-help')->firstOrFail();
        $this->assertSame('Billing help', $category->name);

        $this->actingAs($admin)
            ->put(route('admin.kb-categories.update', $category), [
                'name' => 'Billing guides',
                'slug' => 'billing-guides',
                'description' => 'Updated description',
                'status' => KbCategoryStatus::Hidden->value,
                'sort_order' => 2,
            ])
            ->assertRedirect(route('admin.kb-categories.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('kb_categories', [
            'id' => $category->id,
            'name' => 'Billing guides',
            'slug' => 'billing-guides',
            'status' => KbCategoryStatus::Hidden->value,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.kb-categories.destroy', $category))
            ->assertRedirect(route('admin.kb-categories.index'));

        $this->assertSoftDeleted('kb_categories', ['id' => $category->id]);
    }

    public function test_client_cannot_access_admin_kb_categories(): void
    {
        $client = User::factory()->withRole('client')->create();

        $this->actingAs($client)
            ->get(route('admin.kb-categories.index'))
            ->assertForbidden();
    }
}
