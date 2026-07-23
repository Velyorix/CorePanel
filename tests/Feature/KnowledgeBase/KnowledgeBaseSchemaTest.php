<?php

namespace Tests\Feature\KnowledgeBase;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class KnowledgeBaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_knowledge_base_tables_exist(): void
    {
        foreach (['kb_categories', 'kb_articles'] as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Expected table [{$table}] to exist.",
            );
        }
    }

    public function test_kb_categories_have_expected_columns(): void
    {
        foreach ([
            'id',
            'name',
            'slug',
            'description',
            'sort_order',
            'status',
            'created_at',
            'updated_at',
            'deleted_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('kb_categories', $column),
                "Expected kb_categories.{$column} to exist.",
            );
        }
    }

    public function test_kb_articles_have_expected_columns(): void
    {
        foreach ([
            'id',
            'category_id',
            'title',
            'slug',
            'excerpt',
            'body',
            'status',
            'author_id',
            'published_at',
            'sort_order',
            'created_at',
            'updated_at',
            'deleted_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('kb_articles', $column),
                "Expected kb_articles.{$column} to exist.",
            );
        }
    }
}
