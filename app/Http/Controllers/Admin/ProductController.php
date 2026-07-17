<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexProductRequest;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use Core\Products\Enums\BillingCycle;
use Core\Products\Enums\ProductStatus;
use Core\Products\Enums\ProductType;
use Core\Products\Models\Product;
use Core\Products\Models\ProductCategory;
use Core\Products\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
    ) {
    }

    public function index(IndexProductRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.products.index', [
            'products' => $this->productService->paginateForAdmin($filters),
            'filters' => $filters,
            'statuses' => ProductStatus::cases(),
            'types' => ProductType::cases(),
            'categories' => ProductCategory::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Product::class);

        return view('admin.products.create', $this->formData());
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        try {
            $product = $this->productService->create($request->productData());
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['product' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.products.show', $product)
            ->with('status', __('Product created successfully.'));
    }

    public function show(Product $product): View
    {
        Gate::authorize('view', $product);

        $product->load(['category', 'pricing', 'options', 'addons', 'provisioningRules.nodeGroup']);

        return view('admin.products.show', [
            'product' => $product,
        ]);
    }

    public function edit(Product $product): View
    {
        Gate::authorize('update', $product);

        $product->load(['pricing', 'options', 'addons', 'provisioningRules']);

        return view('admin.products.edit', array_merge($this->formData(), [
            'product' => $product,
        ]));
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        try {
            $product = $this->productService->update($product, $request->productData());
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return back()->withInput()->withErrors(['product' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.products.show', $product)
            ->with('status', __('Product updated successfully.'));
    }

    public function destroy(Product $product): RedirectResponse
    {
        Gate::authorize('delete', $product);

        $this->productService->delete($product);

        return redirect()
            ->route('admin.products.index')
            ->with('status', __('Product deleted successfully.'));
    }

    /**
     * @return array{
     *     categories: \Illuminate\Database\Eloquent\Collection<int, ProductCategory>,
     *     types: list<ProductType>,
     *     billingCycles: list<BillingCycle>
     * }
     */
    private function formData(): array
    {
        return [
            'categories' => ProductCategory::query()->orderBy('sort_order')->orderBy('name')->get(),
            'types' => ProductType::cases(),
            'billingCycles' => BillingCycle::cases(),
        ];
    }
}
