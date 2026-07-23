<?php

namespace Tests\Feature\KnowledgeBase;

use App\Models\User;
use Core\KnowledgeBase\DataTransferObjects\KbArticleData;
use Core\KnowledgeBase\Enums\KbArticleStatus;
use Core\KnowledgeBase\Models\KbArticle;
use Core\KnowledgeBase\Models\KbCategory;
use Core\KnowledgeBase\Services\KnowledgeBaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class KnowledgeBaseServiceTest extends TestCase
{
    use RefreshDatabase;

    private KnowledgeBaseService $kb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kb = app(KnowledgeBaseService::class);
    }

    public function test_knowledge_base_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(KnowledgeBaseService::class),
            app(KnowledgeBaseService::class),
        );
    }

    public function test_create_draft_and_publish_article(): void
    {
        $author = User::factory()->create();
        $category = KbCategory::factory()->create(['slug' => 'howto']);

        $article = $this->kb->create(KbArticleData::fromArray([
            'title' => 'Reset your password',
            'slug' => 'reset-your-password',
            'body' => 'Open the profile page and choose reset password.',
            'category_id' => $category->id,
            'excerpt' => 'How to reset a password',
        ]), $author);

        $this->assertSame(KbArticleStatus::Draft, $article->status);
        $this->assertNull($article->published_at);
        $this->assertTrue($article->author->is($author));
        $this->assertTrue($article->category->is($category));

        $published = $this->kb->publish($article);

        $this->assertSame(KbArticleStatus::Published, $published->status);
        $this->assertNotNull($published->published_at);
        $this->assertNotNull($this->kb->findPublishedBySlug('reset-your-password'));
    }

    public function test_search_published_matches_title_and_body(): void
    {
        KbArticle::factory()->published()->create([
            'title' => 'DNS configuration guide',
            'slug' => 'dns-configuration-guide',
            'body' => 'Point your A record to the server IP.',
        ]);
        KbArticle::factory()->published()->create([
            'title' => 'Billing FAQ',
            'slug' => 'billing-faq',
            'body' => 'Invoices are issued monthly.',
        ]);
        KbArticle::factory()->create([
            'title' => 'Draft DNS tips',
            'slug' => 'draft-dns-tips',
            'body' => 'This draft mentions DNS but must stay private.',
            'status' => KbArticleStatus::Draft,
        ]);

        $results = $this->kb->searchPublished(['q' => 'DNS']);

        $this->assertCount(1, $results);
        $this->assertSame('DNS configuration guide', $results->first()->title);
    }

    public function test_search_published_can_filter_by_category(): void
    {
        $networking = KbCategory::factory()->create(['slug' => 'networking']);
        $billing = KbCategory::factory()->create(['slug' => 'billing']);

        KbArticle::factory()->published()->forCategory($networking)->create([
            'title' => 'PTR records',
            'slug' => 'ptr-records',
        ]);
        KbArticle::factory()->published()->forCategory($billing)->create([
            'title' => 'Refund policy',
            'slug' => 'refund-policy',
        ]);

        $results = $this->kb->searchPublished([
            'category_id' => $networking->id,
        ]);

        $this->assertCount(1, $results);
        $this->assertSame('PTR records', $results->first()->title);
    }

    public function test_update_and_archive_article(): void
    {
        $article = $this->kb->create(KbArticleData::fromArray([
            'title' => 'Old title',
            'slug' => 'old-title',
            'body' => 'Old body content.',
        ]));

        $updated = $this->kb->update($article, KbArticleData::fromArray([
            'title' => 'New title',
            'slug' => 'new-title',
            'body' => 'Updated body content.',
            'status' => KbArticleStatus::Draft->value,
        ]));

        $this->assertSame('New title', $updated->title);
        $this->assertSame('new-title', $updated->slug);

        $published = $this->kb->publish($updated);
        $archived = $this->kb->archive($published);

        $this->assertSame(KbArticleStatus::Archived, $archived->status);
        $this->assertNull($this->kb->findPublishedBySlug('new-title'));
    }

    public function test_slug_must_be_unique(): void
    {
        $this->kb->create(KbArticleData::fromArray([
            'title' => 'Unique',
            'slug' => 'unique-article',
            'body' => 'Body one.',
        ]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('slug [unique-article] already exists');

        $this->kb->create(KbArticleData::fromArray([
            'title' => 'Unique again',
            'slug' => 'unique-article',
            'body' => 'Body two.',
        ]));
    }

    public function test_delete_soft_deletes_article(): void
    {
        $article = KbArticle::factory()->create(['slug' => 'to-delete']);

        $this->kb->delete($article);

        $this->assertSoftDeleted($article);
    }

    public function test_article_status_transition_graph(): void
    {
        $this->assertTrue(KbArticleStatus::Draft->canTransitionTo(KbArticleStatus::Published));
        $this->assertTrue(KbArticleStatus::Published->canTransitionTo(KbArticleStatus::Archived));
        $this->assertTrue(KbArticleStatus::Archived->canTransitionTo(KbArticleStatus::Draft));
        $this->assertFalse(KbArticleStatus::Draft->canTransitionTo(KbArticleStatus::Draft));
    }
}
