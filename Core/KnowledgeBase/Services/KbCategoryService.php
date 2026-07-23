<?php

namespace Core\KnowledgeBase\Services;

use Core\KnowledgeBase\DataTransferObjects\KbCategoryData;
use Core\KnowledgeBase\Enums\KbCategoryStatus;
use Core\KnowledgeBase\Models\KbCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use InvalidArgumentException;

class KbCategoryService
{
    /**
     * @param  array{
     *     q?: string|null,
     *     status?: KbCategoryStatus|null,
     *     sort?: string,
     *     dir?: string
     * }  $filters
     * @return LengthAwarePaginator<int, KbCategory>
     */
    public function paginateForAdmin(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $search = $filters['q'] ?? null;
        $status = $filters['status'] ?? null;
        $sort = $filters['sort'] ?? 'sort_order';
        $dir = ($filters['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        if (! in_array($sort, ['name', 'slug', 'status', 'sort_order', 'created_at'], true)) {
            $sort = 'sort_order';
        }

        $query = KbCategory::query()->withCount('articles');

        if ($status instanceof KbCategoryStatus) {
            $query->where('status', $status->value);
        }

        if (filled($search)) {
            $term = '%'.$search.'%';

            $query->where(function ($builder) use ($search, $term): void {
                $builder
                    ->where('name', 'like', $term)
                    ->orWhere('slug', 'like', $term)
                    ->orWhere('description', 'like', $term);

                if (ctype_digit($search)) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        return $query
            ->orderBy($sort, $dir)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return EloquentCollection<int, KbCategory>
     */
    public function activeCategories(): EloquentCollection
    {
        return KbCategory::query()
            ->where('status', KbCategoryStatus::Active->value)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function create(KbCategoryData $data): KbCategory
    {
        $this->assertSlugIsUnique($data->slug);

        return KbCategory::query()->create($data->toAttributes());
    }

    public function update(KbCategory $category, KbCategoryData $data): KbCategory
    {
        $this->assertSlugIsUnique($data->slug, $category->id);

        $category->update($data->toAttributes());

        return $category->fresh() ?? $category;
    }

    public function delete(KbCategory $category): void
    {
        if ($category->articles()->exists()) {
            throw new InvalidArgumentException(
                'Cannot delete a category that still has articles. Reassign or delete them first.',
            );
        }

        $category->delete();
    }

    private function assertSlugIsUnique(string $slug, ?int $ignoreId = null): void
    {
        $query = KbCategory::query()->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw new InvalidArgumentException("A knowledge base category with slug [{$slug}] already exists.");
        }
    }
}
