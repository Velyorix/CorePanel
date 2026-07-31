<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexProductCategoryRequest;
use App\Http\Requests\Admin\StoreProductCategoryRequest;
use App\Http\Requests\Admin\UpdateProductCategoryRequest;
use Core\Products\Enums\ProductCategoryStatus;
use Core\Products\Models\ProductCategory;
use Core\Products\Services\ProductCategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;

class ProductCategoryController extends Controller
{
    public function __construct(
        private readonly ProductCategoryService $categoryService,
    ) {
    }

    public function index(IndexProductCategoryRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.product-categories.index', [
            'categories' => $this->categoryService->paginateForAdmin($filters),
            'filters' => $filters,
            'statuses' => ProductCategoryStatus::cases(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', ProductCategory::class);

        return view('admin.product-categories.create', $this->formData());
    }

    public function store(StoreProductCategoryRequest $request): RedirectResponse
    {
        try {
            $category = $this->categoryService->create($request->categoryData());
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['category' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.product-categories.show', $category)
            ->with('status', __('Category created successfully.'));
    }

    public function show(ProductCategory $productCategory): View
    {
        Gate::authorize('view', $productCategory);

        $productCategory->load(['parent', 'children'])->loadCount('products');

        return view('admin.product-categories.show', [
            'category' => $productCategory,
        ]);
    }

    public function edit(ProductCategory $productCategory): View
    {
        Gate::authorize('update', $productCategory);

        return view('admin.product-categories.edit', array_merge($this->formData($productCategory), [
            'category' => $productCategory,
        ]));
    }

    public function update(UpdateProductCategoryRequest $request, ProductCategory $productCategory): RedirectResponse
    {
        try {
            $category = $this->categoryService->update($productCategory, $request->categoryData());
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['category' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.product-categories.show', $category)
            ->with('status', __('Category updated successfully.'));
    }

    public function destroy(ProductCategory $productCategory): RedirectResponse
    {
        Gate::authorize('delete', $productCategory);

        try {
            $this->categoryService->delete($productCategory);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['category' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.product-categories.index')
            ->with('status', __('Category deleted successfully.'));
    }

    /**
     * @return array{parentCategories: \Illuminate\Database\Eloquent\Collection<int, ProductCategory>, statuses: list<ProductCategoryStatus>}
     */
    private function formData(?ProductCategory $current = null): array
    {
        $parentCategories = ProductCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->when($current !== null, fn ($query) => $query->whereKeyNot($current->id))
            ->get();

        return [
            'parentCategories' => $parentCategories,
            'statuses' => ProductCategoryStatus::cases(),
        ];
    }
}
