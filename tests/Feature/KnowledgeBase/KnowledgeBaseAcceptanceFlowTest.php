<?php

namespace Tests\Feature\KnowledgeBase;

use App\Models\User;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Models\Client;
use Core\KnowledgeBase\Enums\KbArticleStatus;
use Core\KnowledgeBase\Models\KbArticle;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end knowledge base smoke: publish → client search / archive hidden.
 */
class KnowledgeBaseAcceptanceFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.kb-acceptance',
        ]);
    }

    public function test_admin_published_article_is_searchable_on_client_side(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        [$clientUser] = $this->makeClientUser();

        $this->actingAs($admin)
            ->post(route('admin.kb-articles.store'), [
                'title' => 'Reset API tokens safely',
                'slug' => 'reset-api-tokens-safely',
                'excerpt' => 'Token rotation basics',
                'body' => 'Follow these steps to rotate API tokens without downtime.',
                'sort_order' => 1,
            ])
            ->assertRedirect();

        $article = KbArticle::query()->where('slug', 'reset-api-tokens-safely')->firstOrFail();
        $this->assertSame(KbArticleStatus::Draft, $article->status);

        $this->actingAs($admin)
            ->post(route('admin.kb-articles.publish', $article))
            ->assertRedirect(route('admin.kb-articles.show', $article));

        $this->assertSame(KbArticleStatus::Published, $article->fresh()->status);

        $this->actingAs($clientUser)
            ->get(route('client.kb.index', ['q' => 'API tokens']))
            ->assertOk()
            ->assertSee('Reset API tokens safely');

        $this->actingAs($clientUser)
            ->get(route('client.kb.show', $article->slug))
            ->assertOk()
            ->assertSee('rotate API tokens without downtime');
    }

    public function test_archived_article_is_hidden_from_client_index_and_search(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        [$clientUser] = $this->makeClientUser();

        $article = KbArticle::factory()->published()->create([
            'title' => 'Legacy SSL guide',
            'slug' => 'legacy-ssl-guide',
            'body' => 'Deprecated SSL instructions.',
        ]);

        $this->actingAs($clientUser)
            ->get(route('client.kb.index', ['q' => 'Legacy SSL']))
            ->assertOk()
            ->assertSee('Legacy SSL guide');

        $this->actingAs($admin)
            ->post(route('admin.kb-articles.archive', $article))
            ->assertRedirect(route('admin.kb-articles.show', $article));

        $this->assertSame(KbArticleStatus::Archived, $article->fresh()->status);

        $this->actingAs($clientUser)
            ->get(route('client.kb.index', ['q' => 'Legacy SSL']))
            ->assertOk()
            ->assertDontSee('Legacy SSL guide');

        $this->actingAs($clientUser)
            ->get(route('client.kb.show', $article->slug))
            ->assertNotFound();
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
            ...$clientAttributes,
        ]);
        $client->users()->attach($user->id, [
            'role' => ClientMembershipRole::Owner->value,
        ]);

        return [$user, $client];
    }
}
