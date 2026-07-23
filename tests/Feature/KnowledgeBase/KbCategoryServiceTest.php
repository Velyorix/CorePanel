<?php

namespace Tests\Feature\KnowledgeBase;

use Core\KnowledgeBase\DataTransferObjects\KbCategoryData;
use Core\KnowledgeBase\Enums\KbCategoryStatus;
use Core\KnowledgeBase\Models\KbArticle;
use Core\KnowledgeBase\Models\KbCategory;
use Core\KnowledgeBase\Services\KbCategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class KbCategoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private KbCategoryService $categories;

    protected function setUp(): void
    {
        parent::setUp();

        $this->categories = app(KbCategoryService::class);
    }

    public function test_kb_category_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(KbCategoryService::class),
            app(KbCategoryService::class),
        );
    }

    public function test_create_and_update_category(): void
    {
        $category = $this->categories->create(KbCategoryData::fromArray([
            'name' => 'Getting Started',
            'slug' => 'getting-started',
            'description' => 'Basics',
            'sort_order' => 1,
        ]));

        $this->assertSame(KbCategoryStatus::Active, $category->status);
        $this->assertSame('getting-started', $category->slug);

        $updated = $this->categories->update($category, KbCategoryData::fromArray([
            'name' => 'Getting Started Guides',
            'slug' => 'getting-started',
            'sort_order' => 2,
            'status' => KbCategoryStatus::Hidden->value,
        ]));

        $this->assertSame('Getting Started Guides', $updated->name);
        $this->assertSame(KbCategoryStatus::Hidden, $updated->status);
        $this->assertSame(2, $updated->sort_order);
    }

    public function test_delete_rejects_category_with_articles(): void
    {
        $category = KbCategory::factory()->create();
        KbArticle::factory()->forCategory($category)->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot delete a category that still has articles');

        $this->categories->delete($category);
    }

    public function test_delete_empty_category(): void
    {
        $category = KbCategory::factory()->create(['slug' => 'empty-cat']);

        $this->categories->delete($category);

        $this->assertSoftDeleted($category);
    }

    public function test_active_categories_excludes_hidden(): void
    {
        KbCategory::factory()->create(['slug' => 'active-one']);
        KbCategory::factory()->hidden()->create(['slug' => 'hidden-one']);

        $active = $this->categories->activeCategories();

        $this->assertTrue($active->contains(fn (KbCategory $category): bool => $category->slug === 'active-one'));
        $this->assertFalse($active->contains(fn (KbCategory $category): bool => $category->slug === 'hidden-one'));
    }

    public function test_slug_must_be_unique(): void
    {
        $this->categories->create(KbCategoryData::fromArray([
            'name' => 'Billing',
            'slug' => 'billing',
        ]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('slug [billing] already exists');

        $this->categories->create(KbCategoryData::fromArray([
            'name' => 'Billing again',
            'slug' => 'billing',
        ]));
    }
}
