<?php

namespace Tests\Feature\KnowledgeBase;

use App\Models\User;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Models\Client;
use Core\KnowledgeBase\Models\KbArticle;
use Core\KnowledgeBase\Models\KbCategory;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientKnowledgeBaseUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.client-kb',
        ]);
    }

    public function test_client_can_browse_and_read_published_articles(): void
    {
        [$user] = $this->makeClientUser();
        $category = KbCategory::factory()->create(['name' => 'Getting started']);
        $published = KbArticle::factory()->forCategory($category)->published()->create([
            'title' => 'Welcome guide',
            'slug' => 'welcome-guide',
            'excerpt' => 'Start here',
            'body' => 'This is the welcome article body.',
        ]);
        KbArticle::factory()->create([
            'title' => 'Draft only',
            'slug' => 'draft-only',
        ]);

        $this->actingAs($user)
            ->get(route('client.kb.index'))
            ->assertOk()
            ->assertSee('Welcome guide')
            ->assertSee('Getting started')
            ->assertDontSee('Draft only');

        $this->actingAs($user)
            ->get(route('client.kb.show', $published->slug))
            ->assertOk()
            ->assertSee('Welcome guide')
            ->assertSee('This is the welcome article body.');
    }

    public function test_client_can_search_published_articles(): void
    {
        [$user] = $this->makeClientUser();
        KbArticle::factory()->published()->create([
            'title' => 'DNS troubleshooting',
            'slug' => 'dns-troubleshooting',
        ]);
        KbArticle::factory()->published()->create([
            'title' => 'Billing FAQ',
            'slug' => 'billing-faq',
        ]);

        $this->actingAs($user)
            ->get(route('client.kb.index', ['q' => 'DNS']))
            ->assertOk()
            ->assertSee('DNS troubleshooting')
            ->assertDontSee('Billing FAQ');
    }

    public function test_draft_article_is_not_readable_by_slug(): void
    {
        [$user] = $this->makeClientUser();
        $draft = KbArticle::factory()->create([
            'title' => 'Secret draft',
            'slug' => 'secret-draft',
        ]);

        $this->actingAs($user)
            ->get(route('client.kb.show', $draft->slug))
            ->assertNotFound();
    }

    public function test_admin_without_client_kb_permission_cannot_browse(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('client.kb.index'))
            ->assertForbidden();
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
