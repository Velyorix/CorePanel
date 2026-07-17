<?php

namespace Core\Products\Services;

use Core\Products\DataTransferObjects\ProductCategoryData;
use Core\Products\Enums\ProductCategoryStatus;
use Core\Products\Models\ProductCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class ProductCategoryService
{
    /**
     * @param  array{
     *     q?: string|null,
     *     status?: ProductCategoryStatus|null,
     *     sort?: string,
     *     dir?: string
     * }  $filters
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

        $query = ProductCategory::query()->with('parent')->withCount('products');

        if ($status instanceof ProductCategoryStatus) {
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

    public function create(ProductCategoryData $data): ProductCategory
    {
        $this->assertParentExists($data->parentId);
        $this->assertSlugIsUnique($data->slug);

        return ProductCategory::query()->create($data->toAttributes());
    }

    public function update(ProductCategory $category, ProductCategoryData $data): ProductCategory
    {
        $this->assertParentExists($data->parentId, $category->id);
        $this->assertSlugIsUnique($data->slug, $category->id);

        $category->update($data->toAttributes());

        return $category->fresh(['parent', 'children']) ?? $category;
    }

    public function delete(ProductCategory $category): void
    {
        if ($category->products()->exists()) {
            throw new InvalidArgumentException(
                'Cannot delete a category that still has products. Reassign or delete them first.',
            );
        }

        if ($category->children()->exists()) {
            throw new InvalidArgumentException(
                'Cannot delete a category that still has child categories.',
            );
        }

        $category->delete();
    }

    private function assertParentExists(?int $parentId, ?int $ignoreCategoryId = null): void
    {
        if ($parentId === null) {
            return;
        }

        if ($ignoreCategoryId !== null && $parentId === $ignoreCategoryId) {
            throw new InvalidArgumentException('A category cannot be its own parent.');
        }

        $parent = ProductCategory::query()->find($parentId);

        if ($parent === null) {
            throw new InvalidArgumentException('The selected parent category does not exist.');
        }

        if ($ignoreCategoryId !== null) {
            $cursor = $parent;

            while ($cursor !== null) {
                if ($cursor->id === $ignoreCategoryId) {
                    throw new InvalidArgumentException(
                        'Cannot set a descendant category as parent (cycle detected).',
                    );
                }

                $cursor = $cursor->parent;
            }
        }
    }

    private function assertSlugIsUnique(string $slug, ?int $ignoreCategoryId = null): void
    {
        $query = ProductCategory::query()->where('slug', $slug);

        if ($ignoreCategoryId !== null) {
            $query->whereKeyNot($ignoreCategoryId);
        }

        if ($query->exists()) {
            throw new InvalidArgumentException('A category with this slug already exists.');
        }
    }
}
