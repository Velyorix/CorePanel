<?php

namespace Core\Products\Services;

use Core\Nodes\Models\NodeGroup;
use Core\Products\DataTransferObjects\ProductAddonData;
use Core\Products\DataTransferObjects\ProductData;
use Core\Products\DataTransferObjects\ProductOptionData;
use Core\Products\DataTransferObjects\ProductPricingData;
use Core\Products\DataTransferObjects\ProductProvisioningRulesData;
use Core\Products\Enums\ProductStatus;
use Core\Products\Models\Product;
use Core\Products\Models\ProductAddon;
use Core\Products\Models\ProductCategory;
use Core\Products\Models\ProductOption;
use Core\Products\Models\ProductPricing;
use Core\Products\Models\ProductProvisioningRules;
use Core\Products\Enums\ProductType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ProductService
{
    private const RELATIONS = ['category', 'pricing', 'options', 'addons', 'provisioningRules'];

    /**
     * @param  array{
     *     q?: string|null,
     *     status?: ProductStatus|null,
     *     type?: ProductType|null,
     *     category_id?: int|null,
     *     sort?: string,
     *     dir?: string
     * }  $filters
     */
    public function paginateForAdmin(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $search = $filters['q'] ?? null;
        $status = $filters['status'] ?? null;
        $type = $filters['type'] ?? null;
        $categoryId = $filters['category_id'] ?? null;
        $sort = $filters['sort'] ?? 'sort_order';
        $dir = ($filters['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        if (! in_array($sort, ['name', 'slug', 'type', 'status', 'sort_order', 'created_at'], true)) {
            $sort = 'sort_order';
        }

        $query = Product::query()->with('category');

        if ($status instanceof ProductStatus) {
            $query->where('status', $status->value);
        }

        if ($type instanceof ProductType) {
            $query->where('type', $type->value);
        }

        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        if (filled($search)) {
            $term = '%'.$search.'%';

            $query->where(function ($builder) use ($search, $term): void {
                $builder
                    ->where('name', 'like', $term)
                    ->orWhere('slug', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('module', 'like', $term);

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

    public function create(ProductData $data): Product
    {
        $this->assertCategoryExists($data->categoryId);
        $this->assertSlugIsUnique($data->slug);

        return DB::transaction(function () use ($data): Product {
            $product = Product::query()->create($data->toAttributes());

            $this->syncPricing($product, $data->pricing);
            $this->syncOptions($product, $data->options);
            $this->syncAddons($product, $data->addons);
            $this->syncProvisioningRules($product, $data->provisioningRules);

            return $product->fresh(self::RELATIONS) ?? $product;
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
            $this->syncAddons($product, $data->addons);
            $this->syncProvisioningRules($product, $data->provisioningRules);

            return $product->fresh(self::RELATIONS) ?? $product;
        });
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    public function publish(Product $product): Product
    {
        if ($product->status === ProductStatus::Published) {
            return $product->fresh(self::RELATIONS) ?? $product;
        }

        if (! $product->status->canTransitionTo(ProductStatus::Published)) {
            throw new RuntimeException('Only draft products can be published.');
        }

        return $this->transition($product, ProductStatus::Published);
    }

    public function unpublish(Product $product): Product
    {
        if ($product->status === ProductStatus::Draft) {
            return $product->fresh(self::RELATIONS) ?? $product;
        }

        if ($product->status !== ProductStatus::Published) {
            throw new RuntimeException('Only published products can be unpublished.');
        }

        return $this->transition($product, ProductStatus::Draft);
    }

    public function archive(Product $product): Product
    {
        if ($product->status === ProductStatus::Archived) {
            return $product->fresh(self::RELATIONS) ?? $product;
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
            return $product->fresh(self::RELATIONS) ?? $product;
        }

        if ($product->status !== ProductStatus::Archived) {
            throw new RuntimeException('Only archived products can be restored to draft.');
        }

        return $this->transition($product, ProductStatus::Draft);
    }

    private function transition(Product $product, ProductStatus $target): Product
    {
        $product->forceFill(['status' => $target])->save();

        return $product->fresh(self::RELATIONS) ?? $product;
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

    /**
     * @param  list<ProductAddonData>  $addons
     */
    private function syncAddons(Product $product, array $addons): void
    {
        $keepKeys = [];

        foreach ($addons as $addon) {
            $keepKeys[] = $addon->key;

            ProductAddon::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'key' => $addon->key,
                ],
                $addon->toAttributes(),
            );
        }

        $query = ProductAddon::query()->where('product_id', $product->id);

        if ($keepKeys === []) {
            $query->delete();

            return;
        }

        $query->whereNotIn('key', $keepKeys)->delete();
    }

    private function syncProvisioningRules(Product $product, ?ProductProvisioningRulesData $rules): void
    {
        if ($rules === null) {
            // Keep existing rules on partial updates that omit provisioning_rules.
            // Create defaults only when the product has none yet.
            if (! $product->provisioningRules()->exists()) {
                ProductProvisioningRules::query()->create([
                    'product_id' => $product->id,
                    ...ProductProvisioningRulesData::defaults()->toAttributes(),
                ]);
            }

            return;
        }

        $resolved = $this->resolveNodeGroupAssignment($rules);

        ProductProvisioningRules::query()->updateOrCreate(
            ['product_id' => $product->id],
            $resolved->toAttributes(),
        );
    }

    private function resolveNodeGroupAssignment(ProductProvisioningRulesData $rules): ProductProvisioningRulesData
    {
        if ($rules->nodeGroupId === null && $rules->nodeGroupKey === null) {
            return $rules->withNodeGroup(null, null);
        }

        $group = null;

        if ($rules->nodeGroupId !== null) {
            $group = NodeGroup::query()->find($rules->nodeGroupId);

            if ($group === null) {
                throw new InvalidArgumentException('The selected node group does not exist.');
            }

            if ($rules->nodeGroupKey !== null && $rules->nodeGroupKey !== $group->key) {
                throw new InvalidArgumentException('The node group id and key do not match.');
            }
        } else {
            $group = NodeGroup::query()->where('key', $rules->nodeGroupKey)->first();

            if ($group === null) {
                throw new InvalidArgumentException(
                    "The node group [{$rules->nodeGroupKey}] does not exist.",
                );
            }
        }

        return $rules->withNodeGroup($group->id, $group->key);
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
