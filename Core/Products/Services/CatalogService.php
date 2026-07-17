<?php

namespace Core\Products\Services;

use Core\Products\Enums\ProductCategoryStatus;
use Core\Products\Enums\ProductStatus;
use Core\Products\Enums\ProductType;
use Core\Products\Models\Product;
use Core\Products\Models\ProductCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Client-facing catalog browse (roadmap 10.3).
 * Only Active categories and Published products are visible.
 */
class CatalogService
{
    /**
     * @return Collection<int, ProductCategory>
     */
    public function listActiveCategories(): Collection
    {
        return ProductCategory::query()
            ->where('status', ProductCategoryStatus::Active->value)
            ->whereNull('parent_id')
            ->with([
                'children' => fn ($query) => $query
                    ->where('status', ProductCategoryStatus::Active->value)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->withCount(['products as published_products_count' => fn (Builder $products) => $this->scopePublishedCatalogProducts($products)]),
            ])
            ->withCount(['products as published_products_count' => fn (Builder $products) => $this->scopePublishedCatalogProducts($products)])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function findActiveCategoryBySlug(string $slug): ProductCategory
    {
        $category = ProductCategory::query()
            ->where('slug', $slug)
            ->where('status', ProductCategoryStatus::Active->value)
            ->first();

        if ($category === null) {
            throw (new ModelNotFoundException)->setModel(ProductCategory::class, [$slug]);
        }

        return $category;
    }

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginatePublishedProductsInCategory(ProductCategory $category, int $perPage = 12): LengthAwarePaginator
    {
        if ($category->status !== ProductCategoryStatus::Active) {
            throw (new ModelNotFoundException)->setModel(ProductCategory::class, [$category->id]);
        }

        return $this->publishedCatalogQuery()
            ->where('category_id', $category->id)
            ->with(['pricing' => fn ($query) => $query->where('is_enabled', true)->orderBy('billing_cycle')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findPublishedProductBySlug(string $slug): Product
    {
        $product = $this->publishedCatalogQuery()
            ->where('slug', $slug)
            ->with([
                'category',
                'pricing' => fn ($query) => $query->where('is_enabled', true)->orderBy('billing_cycle'),
            ])
            ->first();

        if ($product === null) {
            throw (new ModelNotFoundException)->setModel(Product::class, [$slug]);
        }

        if (
            $product->category !== null
            && $product->category->status !== ProductCategoryStatus::Active
        ) {
            throw (new ModelNotFoundException)->setModel(Product::class, [$slug]);
        }

        return $product;
    }

    public function findPublishedProductForConfigure(string $slug): Product
    {
        $product = $this->publishedCatalogQuery()
            ->where('slug', $slug)
            ->with([
                'category',
                'options' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                'addons' => fn ($query) => $query
                    ->where('is_enabled', true)
                    ->orderBy('sort_order')
                    ->orderBy('id'),
                'pricing' => fn ($query) => $query
                    ->where('is_enabled', true)
                    ->orderBy('billing_cycle'),
            ])
            ->first();

        if ($product === null) {
            throw (new ModelNotFoundException)->setModel(Product::class, [$slug]);
        }

        if (
            $product->category !== null
            && $product->category->status !== ProductCategoryStatus::Active
        ) {
            throw (new ModelNotFoundException)->setModel(Product::class, [$slug]);
        }

        if ($product->pricing->isEmpty()) {
            throw (new ModelNotFoundException)->setModel(Product::class, [$slug]);
        }

        return $product;
    }

    /**
     * @return Builder<Product>
     */
    private function publishedCatalogQuery(): Builder
    {
        return Product::query()
            ->where('status', ProductStatus::Published->value)
            ->whereIn(
                'type',
                array_map(
                    static fn (ProductType $type): string => $type->value,
                    ProductType::catalogTypes(),
                ),
            )
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('category_id')
                    ->orWhereHas('category', function (Builder $category): void {
                        $category->where('status', ProductCategoryStatus::Active->value);
                    });
            });
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    private function scopePublishedCatalogProducts(Builder $query): Builder
    {
        return $query
            ->where('status', ProductStatus::Published->value)
            ->whereIn(
                'type',
                array_map(
                    static fn (ProductType $type): string => $type->value,
                    ProductType::catalogTypes(),
                ),
            );
    }
}
