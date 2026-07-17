<?php

namespace Core\Products\Services;

use Core\Products\DataTransferObjects\ProductData;
use Core\Products\DataTransferObjects\ProductOptionData;
use Core\Products\DataTransferObjects\ProductPricingData;
use Core\Products\Enums\ProductStatus;
use Core\Products\Models\Product;
use Core\Products\Models\ProductCategory;
use Core\Products\Models\ProductOption;
use Core\Products\Models\ProductPricing;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ProductService
{
    public function create(ProductData $data): Product
    {
        $this->assertCategoryExists($data->categoryId);
        $this->assertSlugIsUnique($data->slug);

        return DB::transaction(function () use ($data): Product {
            $product = Product::query()->create($data->toAttributes());

            $this->syncPricing($product, $data->pricing);
            $this->syncOptions($product, $data->options);

            return $product->fresh(['category', 'pricing', 'options']) ?? $product;
        });
    }

    public function update(Product $product, ProductData $data): Product
    {
        $this->assertCategoryExists($data->categoryId);
        $this->assertSlugIsUnique($data->slug, $product->id);

        if ($data->status !== $product->status) {
            throw new RuntimeException('Product status cannot be changed via update. Use a dedicated transition.');
        }

        return DB::transaction(function () use ($product, $data): Product {
            $product->update($data->toAttributes());

            $this->syncPricing($product, $data->pricing);
            $this->syncOptions($product, $data->options);

            return $product->fresh(['category', 'pricing', 'options']) ?? $product;
        });
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    public function publish(Product $product): Product
    {
        if ($product->status === ProductStatus::Published) {
            return $product->fresh(['category', 'pricing', 'options']) ?? $product;
        }

        if (! $product->status->canTransitionTo(ProductStatus::Published)) {
            throw new RuntimeException('Only draft products can be published.');
        }

        return $this->transition($product, ProductStatus::Published);
    }

    public function unpublish(Product $product): Product
    {
        if ($product->status === ProductStatus::Draft) {
            return $product->fresh(['category', 'pricing', 'options']) ?? $product;
        }

        if ($product->status !== ProductStatus::Published) {
            throw new RuntimeException('Only published products can be unpublished.');
        }

        return $this->transition($product, ProductStatus::Draft);
    }

    public function archive(Product $product): Product
    {
        if ($product->status === ProductStatus::Archived) {
            return $product->fresh(['category', 'pricing', 'options']) ?? $product;
        }

        if (! $product->status->canTransitionTo(ProductStatus::Archived)) {
            throw new RuntimeException('This product cannot be archived from its current status.');
        }

        return $this->transition($product, ProductStatus::Archived);
    }

    /**
     * Restore an archived product back to draft.
     */
    public function restore(Product $product): Product
    {
        if ($product->status === ProductStatus::Draft) {
            return $product->fresh(['category', 'pricing', 'options']) ?? $product;
        }

        if ($product->status !== ProductStatus::Archived) {
            throw new RuntimeException('Only archived products can be restored to draft.');
        }

        return $this->transition($product, ProductStatus::Draft);
    }

    private function transition(Product $product, ProductStatus $target): Product
    {
        $product->forceFill(['status' => $target])->save();

        return $product->fresh(['category', 'pricing', 'options']) ?? $product;
    }

    /**
     * @param  list<ProductPricingData>  $tiers
     */
    private function syncPricing(Product $product, array $tiers): void
    {
        $keepCycles = [];

        foreach ($tiers as $tier) {
            $keepCycles[] = $tier->billingCycle->value;

            ProductPricing::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'billing_cycle' => $tier->billingCycle->value,
                ],
                $tier->toAttributes(),
            );
        }

        $query = ProductPricing::query()->where('product_id', $product->id);

        if ($keepCycles === []) {
            $query->delete();

            return;
        }

        $query->whereNotIn('billing_cycle', $keepCycles)->delete();
    }

    /**
     * @param  list<ProductOptionData>  $options
     */
    private function syncOptions(Product $product, array $options): void
    {
        $keepKeys = [];

        foreach ($options as $option) {
            $keepKeys[] = $option->key;

            ProductOption::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'key' => $option->key,
                ],
                $option->toAttributes(),
            );
        }

        $query = ProductOption::query()->where('product_id', $product->id);

        if ($keepKeys === []) {
            $query->delete();

            return;
        }

        $query->whereNotIn('key', $keepKeys)->delete();
    }

    private function assertCategoryExists(?int $categoryId): void
    {
        if ($categoryId === null) {
            return;
        }

        if (! ProductCategory::query()->whereKey($categoryId)->exists()) {
            throw new InvalidArgumentException('The selected product category does not exist.');
        }
    }

    private function assertSlugIsUnique(string $slug, ?int $ignoreProductId = null): void
    {
        $query = Product::query()->where('slug', $slug);

        if ($ignoreProductId !== null) {
            $query->whereKeyNot($ignoreProductId);
        }

        if ($query->exists()) {
            throw new InvalidArgumentException('A product with this slug already exists.');
        }
    }
}
