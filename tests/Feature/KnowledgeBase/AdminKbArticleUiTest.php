<?php

namespace Tests\Feature\KnowledgeBase;

use App\Models\User;
use Core\KnowledgeBase\Enums\KbArticleStatus;
use Core\KnowledgeBase\Models\KbArticle;
use Core\KnowledgeBase\Models\KbCategory;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminKbArticleUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.admin-kb-articles',
        ]);
    }

    public function test_admin_can_view_articles_index_and_show(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $category = KbCategory::factory()->create(['name' => 'Getting started']);
        $article = KbArticle::factory()->forCategory($category)->authoredBy($admin)->create([
            'title' => 'Reset your password',
            'slug' => 'reset-your-password',
            'body' => 'Follow these steps to reset your password.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.kb-articles.index'))
            ->assertOk()
            ->assertSee('Reset your password')
            ->assertSee('Getting started');

        $this->actingAs($admin)
            ->get(route('admin.kb-articles.show', $article))
            ->assertOk()
            ->assertSee('Reset your password')
            ->assertSee('Follow these steps to reset your password.');
    }

    public function test_admin_can_create_publish_and_archive_article(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $category = KbCategory::factory()->create(['name' => 'Billing']);

        $this->actingAs($admin)
            ->post(route('admin.kb-articles.store'), [
                'title' => 'How invoices work',
                'slug' => 'how-invoices-work',
                'category_id' => $category->id,
                'excerpt' => 'Invoice basics',
                'body' => 'Invoices are generated after payment.',
                'sort_order' => 1,
            ])
            ->assertRedirect();

        $article = KbArticle::query()->where('slug', 'how-invoices-work')->firstOrFail();
        $this->assertSame(KbArticleStatus::Draft, $article->status);

        $this->actingAs($admin)
            ->post(route('admin.kb-articles.publish', $article))
            ->assertRedirect(route('admin.kb-articles.show', $article))
            ->assertSessionHas('status');

        $article->refresh();
        $this->assertSame(KbArticleStatus::Published, $article->status);
        $this->assertNotNull($article->published_at);

        $this->actingAs($admin)
            ->post(route('admin.kb-articles.archive', $article))
            ->assertRedirect(route('admin.kb-articles.show', $article));

        $this->assertSame(KbArticleStatus::Archived, $article->fresh()->status);
    }

    public function test_admin_can_update_and_delete_article(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $article = KbArticle::factory()->create([
            'title' => 'Old title',
            'slug' => 'old-title',
            'body' => 'Old body',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.kb-articles.update', $article), [
                'title' => 'New title',
                'slug' => 'new-title',
                'body' => 'Updated body content',
                'sort_order' => 2,
            ])
            ->assertRedirect(route('admin.kb-articles.show', $article))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('kb_articles', [
            'id' => $article->id,
            'title' => 'New title',
            'slug' => 'new-title',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.kb-articles.destroy', $article))
            ->assertRedirect(route('admin.kb-articles.index'));

        $this->assertSoftDeleted('kb_articles', ['id' => $article->id]);
    }

    public function test_client_cannot_access_admin_kb_articles(): void
    {
        $client = User::factory()->withRole('client')->create();
        $article = KbArticle::factory()->create();

        $this->actingAs($client)
            ->get(route('admin.kb-articles.index'))
            ->assertForbidden();

        $this->actingAs($client)
            ->get(route('admin.kb-articles.show', $article))
            ->assertForbidden();
    }
}
