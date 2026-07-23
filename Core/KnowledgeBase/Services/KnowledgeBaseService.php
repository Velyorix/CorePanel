<?php

namespace Core\KnowledgeBase\Services;

use Core\Auth\Models\User;
use Core\KnowledgeBase\DataTransferObjects\KbArticleData;
use Core\KnowledgeBase\Enums\KbArticleStatus;
use Core\KnowledgeBase\Models\KbArticle;
use Core\KnowledgeBase\Models\KbCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Knowledge base articles lifecycle and public search.
 */
class KnowledgeBaseService
{
    /**
     * @param  array{
     *     q?: string|null,
     *     status?: KbArticleStatus|null,
     *     category_id?: int|null,
     *     sort?: string,
     *     dir?: string
     * }  $filters
     * @return LengthAwarePaginator<int, KbArticle>
     */
    public function paginateForAdmin(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $search = $filters['q'] ?? null;
        $status = $filters['status'] ?? null;
        $categoryId = $filters['category_id'] ?? null;
        $sort = $filters['sort'] ?? 'updated_at';
        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (! in_array($sort, [
            'title',
            'slug',
            'status',
            'published_at',
            'sort_order',
            'created_at',
            'updated_at',
        ], true)) {
            $sort = 'updated_at';
        }

        $query = KbArticle::query()->with(['category', 'author']);

        if ($status instanceof KbArticleStatus) {
            $query->where('status', $status->value);
        }

        if (is_int($categoryId)) {
            $query->where('category_id', $categoryId);
        }

        if (filled($search)) {
            $this->applySearch($query, (string) $search);
        }

        return $query
            ->orderBy($sort, $dir)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Search published articles for the public knowledge base.
     *
     * @param  array{
     *     q?: string|null,
     *     category_id?: int|null,
     *     sort?: string,
     *     dir?: string
     * }  $filters
     * @return LengthAwarePaginator<int, KbArticle>
     */
    public function searchPublished(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $search = $filters['q'] ?? null;
        $categoryId = $filters['category_id'] ?? null;
        $sort = $filters['sort'] ?? 'published_at';
        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (! in_array($sort, [
            'title',
            'published_at',
            'sort_order',
            'created_at',
        ], true)) {
            $sort = 'published_at';
        }

        $query = KbArticle::query()
            ->published()
            ->with(['category']);

        if (is_int($categoryId)) {
            $query->where('category_id', $categoryId);
        }

        if (filled($search)) {
            $this->applySearch($query, (string) $search);
        }

        return $query
            ->orderBy($sort, $dir)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findPublishedBySlug(string $slug): ?KbArticle
    {
        return KbArticle::query()
            ->published()
            ->where('slug', $slug)
            ->with(['category', 'author'])
            ->first();
    }

    public function create(KbArticleData $data, ?User $author = null): KbArticle
    {
        $this->assertCategoryExists($data->categoryId);
        $this->assertSlugIsUnique($data->slug);

        $attributes = $data->toAttributes();

        if ($author !== null && $attributes['author_id'] === null) {
            $attributes['author_id'] = $author->id;
        }

        if ($data->status === KbArticleStatus::Published) {
            $attributes['published_at'] = now();
        } else {
            $attributes['published_at'] = null;
        }

        $article = KbArticle::query()->create($attributes);

        return $article->fresh(['category', 'author']) ?? $article;
    }

    public function update(KbArticle $article, KbArticleData $data): KbArticle
    {
        $this->assertCategoryExists($data->categoryId);
        $this->assertSlugIsUnique($data->slug, $article->id);

        $attributes = $data->toAttributes();

        if ($data->status === KbArticleStatus::Published) {
            $attributes['published_at'] = $article->published_at ?? now();
        } elseif ($data->status !== KbArticleStatus::Archived) {
            $attributes['published_at'] = null;
        }

        $article->update($attributes);

        return $article->fresh(['category', 'author']) ?? $article;
    }

    public function publish(KbArticle $article): KbArticle
    {
        return $this->transition($article, KbArticleStatus::Published, [
            'published_at' => $article->published_at ?? now(),
        ]);
    }

    public function unpublish(KbArticle $article): KbArticle
    {
        return $this->transition($article, KbArticleStatus::Draft, [
            'published_at' => null,
        ]);
    }

    public function archive(KbArticle $article): KbArticle
    {
        return $this->transition($article, KbArticleStatus::Archived);
    }

    public function delete(KbArticle $article): void
    {
        $article->delete();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function transition(KbArticle $article, KbArticleStatus $target, array $extra = []): KbArticle
    {
        if ($article->status === $target) {
            return $article->fresh(['category', 'author']) ?? $article;
        }

        if (! $article->status->canTransitionTo($target)) {
            throw new RuntimeException(sprintf(
                'Cannot transition knowledge base article from %s to %s.',
                $article->status->value,
                $target->value,
            ));
        }

        return DB::transaction(function () use ($article, $target, $extra): KbArticle {
            $article->forceFill([
                'status' => $target,
                ...$extra,
            ])->save();

            return $article->fresh(['category', 'author']) ?? $article;
        });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<KbArticle>  $query
     */
    private function applySearch($query, string $search): void
    {
        $term = '%'.$search.'%';

        $query->where(function ($builder) use ($search, $term): void {
            $builder
                ->where('title', 'like', $term)
                ->orWhere('slug', 'like', $term)
                ->orWhere('excerpt', 'like', $term)
                ->orWhere('body', 'like', $term);

            if (ctype_digit($search)) {
                $builder->orWhere('id', (int) $search);
            }
        });
    }

    private function assertCategoryExists(?int $categoryId): void
    {
        if ($categoryId === null) {
            return;
        }

        if (! KbCategory::query()->whereKey($categoryId)->exists()) {
            throw new InvalidArgumentException("Knowledge base category [{$categoryId}] does not exist.");
        }
    }

    private function assertSlugIsUnique(string $slug, ?int $ignoreId = null): void
    {
        $query = KbArticle::query()->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw new InvalidArgumentException("A knowledge base article with slug [{$slug}] already exists.");
        }
    }
}
